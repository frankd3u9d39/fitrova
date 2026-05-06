<?php
// test_ai_scout.php
$url = 'http://localhost/Fitrova/backend/app/controllers/workout/ai_workout_gemini.php';
$data = ['user_id' => 5]; // User with no equipment

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

echo "Testing AI Workout Generation for User 5 (No Equipment)...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($httpCode === 200) {
    $result = json_decode($response, true);
    echo "Status: " . $result['status'] . "\n";
    echo "AI Provider: " . $result['ai_provider'] . "\n";
    
    if (isset($result['data']['todays_workout'])) {
        $workout = $result['data']['todays_workout'];
        echo "Workout: " . $workout['name'] . " (" . $workout['difficulty'] . ")\n";
        foreach ($workout['exercises'] as $idx => $ex) {
            echo "  Ex " . ($idx+1) . ": " . $ex['name'] . "\n";
            echo "      Video: " . $ex['video_url'] . "\n";
        }
    } else {
        echo "Response Data: " . print_r($result['data'], true) . "\n";
    }
} else {
    echo "Error Response: " . $response . "\n";
}
?>
