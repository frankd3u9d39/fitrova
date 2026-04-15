<?php
$ch = curl_init('http://127.0.0.1/Fitrova/backend/ai_workout_gemini.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['user_id' => 1]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($res === false) echo "Curl error: " . curl_error($ch);
echo "\nHTTP Code: $http_code\n";
echo "Response: $res\n";
