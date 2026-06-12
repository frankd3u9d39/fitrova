<?php
// backend/tests/test_scan_meal.php

// A tiny 1x1 transparent pixel base64 PNG
$base64Image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

$url = 'http://localhost/Fitrova/backend/app/controllers/nutrition/scan_meal.php';
$payload = json_encode([
    'user_id' => 6, // Ibrahim Yusuf
    'image' => 'data:image/png;base64,' . $base64Image
]);

echo "Sending scan meal request to: $url\n";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 40);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
$err = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo "Curl Error: $err\n";
} else {
    echo "HTTP Status Code: " . $info['http_code'] . "\n";
    echo "Response:\n";
    echo $response . "\n";
}
