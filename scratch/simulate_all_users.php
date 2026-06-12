<?php
require_once __DIR__ . '/../backend/config/db_config.php';

// First delete all plans to ensure fresh generation
$pdo->exec("DELETE FROM workout_plans");

$usersStmt = $pdo->query("SELECT id, email, first_name FROM users");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $user) {
    $userId = $user['id'];
    echo "\n========================================\n";
    echo "Simulating request for User ID {$userId} ({$user['first_name']} - {$user['email']})\n";
    echo "========================================\n";
    
    $url = 'http://localhost/Fitrova/backend/app/controllers/workout/ai_workout_gemini.php';
    $data = ['user_id' => $userId];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $duration = microtime(true) - $startTime;
    
    echo "HTTP Status: $httpCode | Took " . round($duration, 2) . "s\n";
    
    $decoded = json_decode($response, true);
    if (!$decoded) {
        echo "Failed to decode response! Raw response:\n" . substr($response, 0, 1000) . "\n";
    } else {
        echo "Status: " . ($decoded['status'] ?? 'N/A') . "\n";
        echo "AI Provider: " . ($decoded['ai_provider'] ?? 'N/A') . "\n";
        if (isset($decoded['data']['todays_workout'])) {
            echo "Workout generated: " . $decoded['data']['todays_workout']['name'] . "\n";
            echo "Exercises: " . implode(', ', array_map(function($e) { return $e['name']; }, $decoded['data']['todays_workout']['exercises'])) . "\n";
        } else {
            echo "No todays_workout in response!\n";
        }
    }
}
?>
