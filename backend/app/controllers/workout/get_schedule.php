<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

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

    // 1. Fetch Workout History (Past 7 Days + Today)
    $historyStmt = $pdo->prepare("
        SELECT workout_name as title, completed_date as date, 'completed' as status
        FROM workout_logs
        WHERE user_id = ? AND completed_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY completed_date DESC
    ");
    $historyStmt->execute([$userId]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Planned Workouts (Upcoming)
    $plansStmt = $pdo->prepare("
        SELECT id, name as title, plan_date as date, 'upcoming' as status, plan_data
        FROM workout_plans
        WHERE user_id = ? AND plan_date >= CURDATE()
        ORDER BY plan_date ASC
    ");
    $plansStmt->execute([$userId]);
    $plans = $plansStmt->fetchAll(PDO::FETCH_ASSOC);

    // Combine and format for Frontend
    // Convert DB results to the structure expected by ScheduleScreen
    $schedule = [];
    
    // Merge history
    foreach ($history as $row) {
        $dateTime = new DateTime($row['date']);
        $schedule[] = [
            'day' => strtoupper($dateTime->format('D')),
            'date' => $dateTime->format('d'),
            'status' => 'completed',
            'title' => $row['title'],
            'full_date' => $row['date']
        ];
    }

    // Merge/Update with plans
    foreach ($plans as $row) {
        $dateTime = new DateTime($row['date']);
        $isToday = $row['date'] === date('Y-m-d');
        
        // If it's today and not in history yet, then it's 'today' status
        $status = $isToday ? 'today' : 'upcoming';
        
        // Check if we already have a 'completed' entry for this date (if so, maybe skip or show both)
        $schedule[] = [
            'day' => strtoupper($dateTime->format('D')),
            'date' => $dateTime->format('d'),
            'status' => $status,
            'title' => $row['title'],
            'full_date' => $row['date'],
            'workout' => json_decode($row['plan_data'], true)['todays_workout'] ?? null
        ];
    }

    echo json_encode([
        'status' => 'success',
        'schedule' => $schedule
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
