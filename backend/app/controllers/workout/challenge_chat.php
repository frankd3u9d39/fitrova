<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

// Self-Heal Database: Ensure parent_id column exists in challenge_messages
try {
    $columnExists = $pdo->query("SHOW COLUMNS FROM challenge_messages LIKE 'parent_id'")->fetch();
    if (!$columnExists) {
        ob_start();
        require_once __DIR__ . '/../../../scripts/setup_production_db.php';
        ob_end_clean();
    }
} catch (PDOException $e) {
    error_log("Database self-healing failed in challenge_chat.php: " . $e->getMessage());
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'send'; // 'send' or 'get'
        
        if ($action === 'get') {
            $challengeKey = $input['challenge_key'] ?? null;
            if (!$challengeKey) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Challenge key is required']);
                exit();
            }
            
            $stmt = $pdo->prepare("
                SELECT cm.id, cm.challenge_key, cm.user_id, cm.message, cm.created_at, cm.parent_id,
                       u.first_name, u.last_name, up.profile_picture,
                       parent.message AS parent_message,
                       pu.first_name AS parent_first_name,
                       pu.last_name AS parent_last_name
                FROM challenge_messages cm
                JOIN users u ON u.id = cm.user_id
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN challenge_messages parent ON parent.id = cm.parent_id
                LEFT JOIN users pu ON pu.id = parent.user_id
                WHERE cm.challenge_key = ?
                ORDER BY cm.created_at ASC
                LIMIT 100
            ");
            $stmt->execute([$challengeKey]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Map formatted relative/short times and initials
            $formattedMessages = [];
            foreach ($messages as $m) {
                $initials = strtoupper(substr($m['first_name'] ?? 'U', 0, 1) . substr($m['last_name'] ?? '', 0, 1));
                
                $senderId = intval($m['user_id']);
                $colors = ['#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899'];
                $color = $colors[$senderId % count($colors)];
                
                $formattedMessages[] = [
                    'id' => intval($m['id']),
                    'user_id' => $senderId,
                    'first_name' => $m['first_name'],
                    'last_name' => $m['last_name'],
                    'initials' => $initials,
                    'color' => $color,
                    'profile_picture' => $m['profile_picture'],
                    'message' => $m['message'],
                    'created_at' => $m['created_at'],
                    'parent_id' => $m['parent_id'] !== null ? intval($m['parent_id']) : null,
                    'parent_message' => $m['parent_message'],
                    'parent_first_name' => $m['parent_first_name'],
                    'parent_last_name' => $m['parent_last_name']
                ];
            }
            
            echo json_encode([
                'status' => 'success',
                'messages' => $formattedMessages
            ]);
            exit();
        } else {
            // Send message
            $userId = $input['user_id'] ?? null;
            $challengeKey = $input['challenge_key'] ?? null;
            $message = trim($input['message'] ?? '');
            $parentId = isset($input['parent_id']) && $input['parent_id'] !== '' ? intval($input['parent_id']) : null;
            
            if (!$userId || !$challengeKey || $message === '') {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'User ID, challenge key, and message are required']);
                exit();
            }
            
            // Optional check: Is user actually in this challenge?
            $checkStmt = $pdo->prepare("SELECT 1 FROM user_challenges WHERE user_id = ? AND challenge_key = ?");
            $checkStmt->execute([$userId, $challengeKey]);
            if (!$checkStmt->fetchColumn()) {
                // To keep the UX simple, we auto-join the user if they try to chat.
                $joinStmt = $pdo->prepare("INSERT IGNORE INTO user_challenges (user_id, challenge_key) VALUES (?, ?)");
                $joinStmt->execute([$userId, $challengeKey]);
            }
            
            $insertStmt = $pdo->prepare("INSERT INTO challenge_messages (challenge_key, user_id, message, parent_id) VALUES (?, ?, ?, ?)");
            $insertStmt->execute([$challengeKey, $userId, $message, $parentId]);
            $userMessageId = $pdo->lastInsertId();
            
            // Check/Create Gemma AI Coach user
            $gemmaEmail = 'gemma.ai@fitrova.com';
            $gemmaStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $gemmaStmt->execute([$gemmaEmail]);
            $gemmaId = $gemmaStmt->fetchColumn();

            if (!$gemmaId) {
                $dummyHash = password_hash('gemma_bot_secret_123', PASSWORD_BCRYPT);
                $insertUser = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, is_verified) VALUES (?, ?, 'Gemma', 'AI Coach', 1)");
                $insertUser->execute([$gemmaEmail, $dummyHash]);
                $gemmaId = $pdo->lastInsertId();
                
                $insertProfile = $pdo->prepare("INSERT INTO user_profiles (user_id, motto, profile_picture) VALUES (?, 'Your AI Fitness & Nutrition Coach', 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=150')");
                $insertProfile->execute([$gemmaId]);
            }

            // Only trigger AI response if the message is NOT from the AI coach itself
            if (intval($userId) !== intval($gemmaId)) {
                // Fetch Hugging Face token from settings
                $tokenStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'hf_token'");
                $tokenStmt->execute();
                $hfToken = $tokenStmt->fetchColumn() ?: '';

                if (!empty($hfToken)) {
                    try {
                        // Get sender details
                        $userStmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                        $userStmt->execute([$userId]);
                        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                        $senderName = $user ? ($user['first_name'] . ' ' . $user['last_name']) : 'User';

                        // Retrieve last 5 messages in this challenge for context
                        $contextStmt = $pdo->prepare("
                            SELECT cm.message, u.first_name, u.last_name 
                            FROM challenge_messages cm
                            JOIN users u ON u.id = cm.user_id
                            WHERE cm.challenge_key = ?
                            ORDER BY cm.created_at DESC
                            LIMIT 5
                        ");
                        $contextStmt->execute([$challengeKey]);
                        $recentChat = array_reverse($contextStmt->fetchAll(PDO::FETCH_ASSOC));
                        
                        $chatContextStr = "";
                        foreach ($recentChat as $c) {
                            $chatContextStr .= "{$c['first_name']}: {$c['message']}\n";
                        }
                        
                        $challengeName = str_replace('_', ' ', $challengeKey);
                        $challengeName = ucwords($challengeName);
                        
                        $prompt = "You are Gemma AI Coach, an expert fitness trainer participating in a group chat for the community challenge named '{$challengeName}'.\n";
                        $prompt .= "Here is the recent conversation in the group:\n";
                        $prompt .= $chatContextStr;
                        $prompt .= "\nNow, the user {$senderName} just sent the following message:\n";
                        $prompt .= "\"{$message}\"\n\n";
                        $prompt .= "Generate a brief, natural, and highly motivational response (1-2 sentences maximum) directly replying to {$senderName}. You can give a quick fitness tip, congratulate them, or encourage them. Keep it friendly and concise. Do not output JSON or quotes around your response. Output only the message content.";
                        
                        $gemmaReply = callHuggingFaceAPI($prompt, $hfToken);
                        $gemmaReply = trim($gemmaReply);
                        
                        // Clean up response
                        if (strpos($gemmaReply, "Gemma AI Coach:") !== false) {
                            $gemmaReply = explode("Gemma AI Coach:", $gemmaReply);
                            $gemmaReply = trim(end($gemmaReply));
                        }
                        $gemmaReply = trim($gemmaReply, "\"'");
                        
                        if (!empty($gemmaReply)) {
                            // Ensure Gemma AI Coach is joined to the challenge
                            $checkStmt = $pdo->prepare("SELECT 1 FROM user_challenges WHERE user_id = ? AND challenge_key = ?");
                            $checkStmt->execute([$gemmaId, $challengeKey]);
                            if (!$checkStmt->fetchColumn()) {
                                $joinStmt = $pdo->prepare("INSERT IGNORE INTO user_challenges (user_id, challenge_key) VALUES (?, ?)");
                                $joinStmt->execute([$gemmaId, $challengeKey]);
                            }
                            
                            // Insert AI message as a direct reply to the user's message
                            $insertAIStmt = $pdo->prepare("INSERT INTO challenge_messages (challenge_key, user_id, message, parent_id) VALUES (?, ?, ?, ?)");
                            $insertAIStmt->execute([$challengeKey, $gemmaId, $gemmaReply, $userMessageId]);
                        }
                    } catch (Exception $e) {
                        error_log("Failed to generate Gemma AI response: " . $e->getMessage());
                    }
                }
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Message sent successfully'
            ]);
            exit();
        }
    } else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

function callHuggingFaceAPI($prompt, $hfToken, $model = 'google/gemma-2-9b-it') {
    $url = "https://api-inference.huggingface.co/models/" . $model;
    
    $payload = [
        'inputs' => $prompt,
        'parameters' => [
            'max_new_tokens' => 150,
            'temperature' => 0.7
        ],
        'options' => [
            'wait_for_model' => true
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT,        15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $hfToken
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Hugging Face API Error (HTTP $httpCode): " . $response);
    }

    $result = json_decode($response, true);
    
    if (is_array($result) && isset($result[0]['generated_text'])) {
        $genText = $result[0]['generated_text'];
        if (strpos($genText, $prompt) === 0) {
            $genText = substr($genText, strlen($prompt));
        }
        return trim($genText);
    }
    
    throw new Exception("Unexpected response format from Hugging Face: " . $response);
}
