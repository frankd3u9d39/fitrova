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
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['user_id'] ?? null;
    }

    if (!$userId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'User ID required']);
        exit();
    }

    // Fetch unique workout plans generated for this user
    // We group by name to avoid showing the same plan multiple times if generated on different days
    $stmt = $pdo->prepare("
        SELECT name, workout_type as category, plan_data, MAX(plan_date) as last_generated
        FROM workout_plans
        WHERE user_id = ?
        GROUP BY name, workout_type
        ORDER BY last_generated DESC
    ");
    $stmt->execute([$userId]);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $library = [];
    foreach ($plans as $row) {
        $data = json_decode($row['plan_data'], true);
        $workout = $data['todays_workout'] ?? null;
        
        if ($workout) {
            $library[] = [
                'name' => $row['name'],
                'category' => $row['category'],
                'difficulty' => $workout['difficulty'] ?? 'Intermediate',
                'duration' => $workout['duration'] ?? 45,
                'exercises_count' => count($workout['exercises'] ?? []),
                'image_url' => 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=800&q=80',
                'workout' => $workout
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'library' => $library
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
