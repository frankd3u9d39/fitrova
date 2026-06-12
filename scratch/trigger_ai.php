<?php
// scratch/trigger_ai.php

$url = 'http://localhost/Fitrova/backend/app/controllers/workout/ai_workout_gemini.php';
$payload = json_encode(['user_id' => 1]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

echo "Triggering ai_workout_gemini.php for user_id = 1...\n";
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: " . substr($res, 0, 500) . "...\n";

// Let's check if it created the insight in ai_insights
require_once __DIR__ . '/../backend/config/db_config.php';
$stmt = $pdo->prepare("SELECT * FROM ai_insights WHERE user_id = 1 ORDER BY id DESC LIMIT 1");
$stmt->execute();
$insight = $stmt->fetch();

echo "\nLatest Insight in DB:\n";
print_r($insight);
?>
