<?php
$imagePath = 'C:\Users\User\.gemini\antigravity\brain\8b2934bb-641f-401e-9959-9a886af5d8eb\person_doing_squat_imperfect_form_1776259751435.png';
$imageData = base64_encode(file_get_contents($imagePath));

$url = 'http://localhost/Fitrova/backend/ai_form_analyzer.php';
$payload = json_encode([
    'user_id' => 1,
    'image' => 'data:image/png;base64,' . $imageData,
    'exercise' => 'Detect automatically'
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "HTTP Code: " . $info['http_code'] . "\n\n";
echo "Response:\n";
echo $response;
?>
