<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db_config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $userId = $input['user_id'] ?? null;
    $workoutName = $input['workout_name'] ?? null;
    $duration = $input['duration'] ?? 0; // expected in minutes
    
    if (!$userId || !$workoutName) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing user_id or workout_name']);
        exit();
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO workout_logs (user_id, workout_name, duration_minutes, completed_date)
        VALUES (?, ?, ?, CURDATE())
    ");
    
    $stmt->execute([$userId, $workoutName, $duration]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Workout completed successfully',
        'log_id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save workout: ' . $e->getMessage()
    ]);
}
?>
