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
