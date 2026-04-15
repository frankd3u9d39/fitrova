<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Prevent PHP warnings/notices from breaking JSON output
error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db_config.php';

// API Configuration
$GEMINI_API_KEY = 'AQ.Ab8RN6K04-jc_xK7I1yOSz291VKJ1pwm0j5izQMReuOhalV7uA';
$PEXELS_API_KEY = 'NqL4xIyERsx6gMoHiqiarppte5zJdfiu34fNarEsSv97AA7FOyGFFszT'; // Your Pexels API Key for HD clips

// Verified Video Library (Professional Fitness Fallbacks)
$VERIFIED_VIDEOS = [
    'general'  => 'https://videos.pexels.com/video-files/5510141/5510141-hd_1080_1920_25fps.mp4', // Stretching
    'cardio'   => 'https://videos.pexels.com/video-files/8857692/8857692-hd_1280_720_25fps.mp4',   // Jumping Jacks
    'strength' => 'https://videos.pexels.com/video-files/8401319/8401319-hd_1920_1080_30fps.mp4',  // Weight Training
    'core'     => 'https://videos.pexels.com/video-files/4366624/4366624-hd_1080_1920_25fps.mp4',  // Plank/Core
    'recovery' => 'https://videos.pexels.com/video-files/5510141/5510141-hd_1080_1920_25fps.mp4'   // Cool down
];

/**
 * Fetches a high-quality fitness video from Pexels based on exercise name
 */
function fetchVideoFromPexels($query, $apiKey) {
    if (empty($apiKey)) return null;

    $url = "https://api.pexels.com/videos/search?query=" . urlencode($query . " demonstration fitness") . "&per_page=1";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: $apiKey"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (!empty($result['videos'])) {
            // Return the link to the smallest HD file for fast loading
            foreach ($result['videos'][0]['video_files'] as $file) {
                if ($file['quality'] === 'hd' || $file['quality'] === 'sd') {
                    return $file['link'];
                }
            }
        }
    }
    return null;
}

function callGemini($prompt, $apiKey) {

    // Using Gemini Flash Latest for maximum compatibility and generous free-tier quotas
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . $apiKey;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 4096,
            'response_mime_type' => 'application/json'
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Gemini API error: ' . $response);
    }
    
    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'];
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? null;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'User ID required']);
        exit();
    }
    
    // Get user profile
    $stmt = $pdo->prepare("
        SELECT up.*, u.first_name 
        FROM user_profiles up
        JOIN users u ON up.user_id = u.id
        WHERE up.user_id = ?
    ");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get workout history
    $historyStmt = $pdo->prepare("
        SELECT COUNT(*) as total_workouts,
               MAX(completed_date) as last_workout
        FROM workout_logs
        WHERE user_id = ? AND completed_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $historyStmt->execute([$userId]);
    $history = $historyStmt->fetch(PDO::FETCH_ASSOC);
    
    // 1. PERSISTENCE CHECK: Load existing plan for today if it exists
    $planStmt = $pdo->prepare("
        SELECT plan_data 
        FROM workout_plans 
        WHERE user_id = ? AND plan_date = CURDATE() 
        LIMIT 1
    ");
    $planStmt->execute([$userId]);
    $existingPlan = $planStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingPlan) {
        $workoutData = json_decode($existingPlan['plan_data'], true);
        $ai_provider = 'Database (Previously Generated)';
        goto finalize_response; // Skip AI generation
    }

    // Calculate days since last workout for the AI prompt
    $daysSince = 3;
    if ($history['last_workout']) {
        $lastDate = new DateTime($history['last_workout']);
        $today = new DateTime();
        $daysSince = $today->diff($lastDate)->days;
    }

    // 2. Build AI prompt
    $prompt = "You are a professional fitness trainer. Generate a personalized workout plan.\n\n";
    $prompt .= "User Profile:\n";
    $prompt .= "- Name: " . ($profile['first_name'] ?? 'User') . "\n";
    $prompt .= "- Fitness Goal: " . ($profile['fitness_goal'] ?? 'general fitness') . "\n";
    $prompt .= "- Activity Level: " . ($profile['activity_level'] ?? 'moderate') . "\n";
    $prompt .= "- Workouts in last 30 days: " . ($history['total_workouts'] ?? 0) . "\n";
    $prompt .= "- Days since last workout: " . $daysSince . "\n";
    $prompt .= "- Age: " . ($profile['age'] ?? 'not specified') . "\n\n";
    
    $prompt .= "Generate a workout plan and return ONLY valid JSON.\n";
    $prompt .= "VIDEO STRATEGY: For each exercise, provide a 'search_term' which is a standard fitness name for finding a video (e.g., 'jumping jacks', 'pushups').\n";
    $prompt .= "Instructions should be 1-2 powerful cues for correct form.\n\n";
    
    $prompt .= "JSON FORMAT:\n";
    $prompt .= "{\n";
    $prompt .= '  "todays_workout": {';
    $prompt .= '    "name": "Workout Title",';
    $prompt .= '    "exercises": [';
    $prompt .= '      {"name": "Exercise Name", "search_term": "standard name", "sets": 3, "reps": 10, "instructions": "Short tip"}';
    $prompt .= '    ],';
    $prompt .= '    "exercises_count": 1,';
    $prompt .= '    "duration": 30,';
    $prompt .= '    "difficulty": "beginner",';
    $prompt .= '    "type": "strength"';
    $prompt .= '  },';
    $prompt .= '  "recovery_score": 90,';
    $prompt .= '  "status": "READY FOR SESSION",';
    $prompt .= '  "missed_workouts": [],';
    $prompt .= '  "upcoming_workouts": []';
    $prompt .= "\n}";
    
    // Call Gemini
    $aiResponse = callGemini($prompt, $GEMINI_API_KEY);
    
    // Clean response (remove markdown if present)
    $aiResponse = preg_replace('/```json\s*/', '', $aiResponse);
    $aiResponse = preg_replace('/```\s*$/', '', $aiResponse);
    $aiResponse = trim($aiResponse);
    
    // Parse JSON from AI response
    $workoutData = json_decode($aiResponse, true);

    // DYNAMIC VIDEO ENRICHMENT
    if ($workoutData && isset($workoutData['todays_workout']['exercises'])) {
        foreach ($workoutData['todays_workout']['exercises'] as &$exercise) {
            $searchTerm = $exercise['search_term'] ?? $exercise['name'];
            
            // Try to fetch HD video from Pexels
            $videoUrl = fetchVideoFromPexels($searchTerm, $PEXELS_API_KEY);
            
            // Fallback to verified category mirrors if Pexels fails or no key
            if (!$videoUrl) {
                $type = strtolower($workoutData['todays_workout']['type'] ?? 'general');
                $videoUrl = $VERIFIED_VIDEOS[$type] ?? $VERIFIED_VIDEOS['general'];
            }
            
            $exercise['video_url'] = $videoUrl;
            $exercise['image_url'] = "https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=800&q=80"; // Default fitness placeholder
        }
    }

    // 1. AUTO-HEALING: If AI fails or returns malformed data, use the high-quality mock
    if (!$workoutData || isset($workoutData['error']) || !isset($workoutData['todays_workout'])) {
        error_log("⚠️ Gemini AI error or malformed data, fallback to mock.");
        $workoutData = [
            'todays_workout' => [
                'name' => 'Fitrova Strength Starter (Offline Mode)',
                'exercises' => [
                    [
                        'name' => 'Jumping Jacks',
                        'sets' => 3, 'reps' => 20, 'duration' => 60,
                        'image_url' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80',
                        'video_url' => $VERIFIED_VIDEOS['cardio'],
                        'instructions' => 'Stand with feet together and arms at sides. Jump and spread legs while swinging arms overhead.'
                    ],
                    [
                        'name' => 'Deep Squats',
                        'sets' => 3, 'reps' => 15, 'duration' => 60,
                        'image_url' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80',
                        'video_url' => $VERIFIED_VIDEOS['strength'],
                        'instructions' => 'Lower your hips as if sitting in a chair, keeping your chest up and weight on your heels.'
                    ]
                ],
                'exercises_count' => 2,
                'duration' => 20,
                'difficulty' => 'beginner',
                'type' => 'Full Body'
            ]
        ];
        $ai_provider = 'Fitrova Internal (Fallback)';
    } else {
        $ai_provider = 'Google Gemini Pro';
        
        // SAVE NEW PLAN TO DATABASE
        try {
            $saveStmt = $pdo->prepare("
                INSERT INTO workout_plans (user_id, name, workout_type, plan_data, plan_date, is_active)
                VALUES (?, ?, ?, ?, CURDATE(), 1)
            ");
            $saveStmt->execute([
                $userId,
                $workoutData['todays_workout']['name'] ?? 'Daily Workout',
                $workoutData['todays_workout']['type'] ?? 'mixed',
                json_encode($workoutData)
            ]);
        } catch (Exception $e) {
            error_log("Failed to save workout plan: " . $e->getMessage());
        }
    }

finalize_response:

    // 2. MANDATORY METADATA ENRICHMENT (Ensures frontend fields like 'completed' never crash)
    $workoutData['weekly_progress'] = [
        'completed' => (int)($history['total_workouts'] ?? 0),
        'goal' => 4
    ];
    
    if (!isset($workoutData['status'])) $workoutData['status'] = 'READY FOR SESSION';
    if (!isset($workoutData['recovery_score'])) $workoutData['recovery_score'] = 98;
    if (!isset($workoutData['missed_workouts'])) $workoutData['missed_workouts'] = [];
    
    // Ensure high-quality Upcoming Workouts exist
    if (!isset($workoutData['upcoming_workouts']) || empty($workoutData['upcoming_workouts'])) {
        $workoutData['upcoming_workouts'] = [
            [
                'name' => 'Upper Body Power',
                'scheduled_date' => date('Y-m-d', strtotime('+1 day')),
                'day_name' => date('l', strtotime('+1 day')),
                'duration' => 45,
                'exercises_count' => 6,
                'exercises' => [
                    ['name' => 'Bench Press', 'sets' => 3, 'reps' => 10],
                    ['name' => 'Incline Flys', 'sets' => 3, 'reps' => 12]
                ]
            ],
            [
                'name' => 'Lower Body Focus',
                'scheduled_date' => date('Y-m-d', strtotime('+2 days')),
                'day_name' => date('l', strtotime('+2 days')),
                'duration' => 50,
                'exercises_count' => 5,
                'exercises' => [
                    ['name' => 'Squats', 'sets' => 3, 'reps' => 15],
                    ['name' => 'Leg Extensions', 'sets' => 3, 'reps' => 12]
                ]
            ]
        ];
    }
    
    // 3. SEND CLEAN RESPONSE
    echo json_encode([
        'status' => 'success',
        'data' => $workoutData,
        'ai_provider' => $ai_provider
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
