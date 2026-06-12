<?php
$url = 'http://localhost/Fitrova/backend/app/controllers/workout/ai_workout_gemini.php';
$data = ['user_id' => 1];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: " . substr($response, 0, 1000) . "...\n";

$decoded = json_decode($response, true);
if (isset($decoded['ai_provider'])) {
    echo "AI Provider: " . $decoded['ai_provider'] . "\n";
} else {
    echo "AI Provider field missing!\n";
}
