<?php
// Simulate POST request to backend/app/controllers/workout/ai_workout_gemini.php

$url = "http://localhost/Fitrova/backend/app/controllers/workout/ai_workout_gemini.php";
$data = ['user_id' => 1]; // User 1 has has_equipment = 0 (No Equipment)

$options = [
    'http' => [
        'header'  => "Content-Type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'timeout' => 60 // Allow ample time for potential live AI call
    ]
];

$context  = stream_context_create($options);
$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo "Error simulating request to local XAMPP backend. Ensure XAMPP Apache is running!\n";
    exit();
}

$result = json_decode($response, true);

echo "=== Response Status ===\n";
echo "Status: " . ($result['status'] ?? 'Unknown') . "\n";
echo "AI Provider: " . ($result['ai_provider'] ?? 'Unknown') . "\n\n";

if (isset($result['data']['todays_workout'])) {
    $workout = $result['data']['todays_workout'];
    echo "=== Today's Workout: " . $workout['name'] . " ===\n";
    echo "Difficulty: " . $workout['difficulty'] . " | Type: " . $workout['type'] . "\n";
    echo "Exercises:\n";
    foreach ($workout['exercises'] as $ex) {
        echo "  - " . $ex['name'] . " | Video: " . $ex['video_url'] . "\n";
        echo "    Instructions: " . $ex['instructions'] . "\n";
    }
} else {
    echo "Error: No today's workout in response data!\n";
}

if (isset($result['data']['upcoming_workouts'])) {
    echo "\n=== Upcoming Workouts ===\n";
    foreach ($result['data']['upcoming_workouts'] as $up) {
        echo "  Workout: " . $up['name'] . " on " . $up['scheduled_date'] . "\n";
        foreach ($up['exercises'] as $ex) {
            echo "    - " . $ex['name'] . " | Video: " . ($ex['video_url'] ?? 'No Video') . "\n";
        }
    }
}

?>
