<?php
// backend/tests/test_pattern_alert.php

require_once __DIR__ . '/../config/db_config.php';

$userId = 6;

// 1. Insert temporary repetitive meals for User 6 (already logged 3-4 times this week)
echo "Inserting temporary repetitive meals for user_id = $userId...\n";
$tempLogs = [
    ['Double Cheeseburger', 850, 42, 48, 52, 'lunch', date('Y-m-d', strtotime('-1 day'))],
    ['Double Cheeseburger', 850, 42, 48, 52, 'dinner', date('Y-m-d', strtotime('-2 days'))],
    ['Double Cheeseburger', 850, 42, 48, 52, 'lunch', date('Y-m-d', strtotime('-3 days'))],
    ['Double Cheeseburger', 850, 42, 48, 52, 'lunch', date('Y-m-d', strtotime('-4 days'))],
];

$stmt = $pdo->prepare("INSERT INTO nutrition_logs (user_id, meal_name, calories, protein, carbs, fats, meal_type, logged_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

$insertedIds = [];
foreach ($tempLogs as $log) {
    $stmt->execute([$userId, $log[0], $log[1], $log[2], $log[3], $log[4], $log[5], $log[6]]);
    $insertedIds[] = $pdo->lastInsertId();
}

// 2. Call the scan meal endpoint for a cheeseburger image (using mock red dot)
$base64Image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
$url = 'http://localhost/Fitrova/backend/app/controllers/nutrition/scan_meal.php';
$payload = json_encode([
    'user_id' => $userId,
    'image' => 'data:image/png;base64,' . $base64Image
]);

echo "Sending scan meal request to: $url (expecting cheeseburger pattern detection)\n";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 40);

$response = curl_exec($ch);
curl_close($ch);

// 3. Clean up the database (delete the temporary logs)
echo "Cleaning up temporary logs from database...\n";
$placeholders = implode(',', array_fill(0, count($insertedIds), '?'));
$pdo->prepare("DELETE FROM nutrition_logs WHERE id IN ($placeholders)")->execute($insertedIds);

// 4. Print results
echo "Response:\n";
echo $response . "\n";
