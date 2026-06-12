<?php
require_once __DIR__ . '/../backend/config/db_config.php';

$userId = 1;

// Load settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$PRIMARY_MODEL = 'gemini-2.5-pro';
$SYSTEM_PROMPT = $settings['ai_system_prompt'] ?? 'You are a professional fitness trainer. Generate a personalized workout plan.';
$GEMINI_API_KEY = $settings['ai_gemini_api_key'] ?? '';

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

$daysSince = 0;
if ($history['last_workout']) {
    $lastDate = new DateTime($history['last_workout']);
    $today = new DateTime();
    $daysSince = $today->diff($lastDate)->days;
}

$activityLevel = strtolower($profile['activity_level'] ?? 'moderate');
$isBeginner = ($activityLevel === 'sedentary' || $activityLevel === 'lightly active' || $activityLevel === 'moderate');
$hasEquipment = (bool)($profile['has_equipment'] ?? true);

$prompt = $SYSTEM_PROMPT . "\n\n";
$prompt .= "User Profile:\n";
$prompt .= "- Name: " . ($profile['first_name'] ?? 'User') . "\n";
$prompt .= "- Fitness Goal: " . ($profile['fitness_goal'] ?? 'general fitness') . "\n";
$prompt .= "- Activity Level: " . ($profile['activity_level'] ?? 'moderate') . "\n";
$prompt .= "- Equipment Available: " . ($hasEquipment ? "Full Gym Access" : "NO EQUIPMENT (Bodyweight Only)") . "\n";
$prompt .= "- Workouts in last 30 days: " . ($history['total_workouts'] ?? 0) . "\n";
$prompt .= "- Days since last workout: " . $daysSince . "\n";
$prompt .= "- Age: " . ($profile['age'] ?? 'not specified') . "\n";

$prompt .= "JSON FORMAT:\n";
$prompt .= "{\n";
$prompt .= '  "todays_workout": {';
$prompt .= '    "name": "Workout Title",';
$prompt .= '    "exercises": [';
$prompt .= '      {"name": "Exercise Name", "search_term": "name", "sets": 3, "reps": 10, "instructions": "cues"}';
$prompt .= '    ],';
$prompt .= '    "exercises_count": 1,';
$prompt .= '    "duration": 30,';
$prompt .= '    "difficulty": "beginner",';
$prompt .= '    "type": "strength"';
$prompt .= '  },';
$prompt .= '  "recovery_score": 90,';
$prompt .= '  "status": "READY FOR SESSION",';
$prompt .= '  "missed_workouts": [],' . "\n";
$prompt .= '  "upcoming_workouts": [' . "\n";
$prompt .= '    {"name": "Upper Body Power", "scheduled_date": "YYYY-MM-DD", "duration": 45, "exercises_count": 6}' . "\n";
$prompt .= '  ]' . "\n";
$prompt .= '}' . "\n";
$prompt .= 'IMPORTANT: Always return "name" and "scheduled_date" (YYYY-MM-DD) for upcoming workouts.';

echo "Sending simplified request to $PRIMARY_MODEL...\n";

$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $PRIMARY_MODEL . ':generateContent?key=' . $GEMINI_API_KEY;
$data = [
    'contents' => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 2048,
        'response_mime_type' => 'application/json'
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$startTime = microtime(true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);
$duration = microtime(true) - $startTime;

echo "HTTP Code: $httpCode | Took " . round($duration, 2) . " seconds\n";

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $text = $result['candidates'][0]['content']['parts'][0]['text'];
        echo "SUCCESS!\n";
        $decoded = json_decode($text, true);
        if ($decoded === null) {
            echo "JSON Decode Error: " . json_last_error_msg() . "\n";
        } else {
            echo "Decoded Successfully!\n";
            echo "Workout Name: " . $decoded['todays_workout']['name'] . "\n";
            echo "Exercises:\n";
            foreach ($decoded['todays_workout']['exercises'] as $ex) {
                echo "- " . $ex['name'] . " (" . $ex['sets'] . "x" . $ex['reps'] . ")\n";
            }
        }
    } else {
        echo "No candidate text found!\n";
    }
} else {
    echo "Failed! Response: " . $response . "\n";
}
?>
