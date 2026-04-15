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
    
    if (!isset($input['user_id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
        exit();
    }
    
    $userId = intval($input['user_id']);
    
    // Get user profile
    $profileStmt = $pdo->prepare("
        SELECT up.*, u.first_name, u.last_name 
        FROM users u
        LEFT JOIN user_profiles up ON up.user_id = u.id
        WHERE u.id = ?
    ");
    $profileStmt->execute([$userId]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit();
    }
    
    // Create profile if it doesn't exist
    if (!$profile['id']) {
        $pdo->prepare("INSERT INTO user_profiles (user_id) VALUES (?)")->execute([$userId]);
    }
    
    // Get today's calories
    $today = date('Y-m-d');
    $caloriesStmt = $pdo->prepare("
        SELECT COALESCE(SUM(calories), 0) as total_calories
        FROM nutrition_logs
        WHERE user_id = ? AND logged_date = ?
    ");
    $caloriesStmt->execute([$userId, $today]);
    $caloriesData = $caloriesStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get today's workout
    $workoutStmt = $pdo->prepare("
        SELECT workout_name, duration_minutes
        FROM workout_logs
        WHERE user_id = ? AND completed_date = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $workoutStmt->execute([$userId, $today]);
    $todayWorkout = $workoutStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get weight history for last 7 days
    $weightStmt = $pdo->prepare("
        SELECT weight, recorded_date
        FROM weight_history
        WHERE user_id = ? AND recorded_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY recorded_date ASC
    ");
    $weightStmt->execute([$userId]);
    $weightHistory = $weightStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get latest AI insight
    $insightStmt = $pdo->prepare("
        SELECT insight_text, insight_type
        FROM ai_insights
        WHERE user_id = ? AND is_read = FALSE
        ORDER BY created_at DESC LIMIT 1
    ");
    $insightStmt->execute([$userId]);
    $insight = $insightStmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate health score
    $healthScore = 50;
    if ($caloriesData['total_calories'] > 0) $healthScore += 10;
    if ($todayWorkout) $healthScore += 15;
    if (count($weightHistory) >= 3) $healthScore += 10;
    $healthScore = min(100, $healthScore);
    
    // Update health score if profile exists
    if ($profile['id']) {
        $pdo->prepare("UPDATE user_profiles SET health_score = ? WHERE user_id = ?")
            ->execute([$healthScore, $userId]);
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'user' => [
                'first_name' => $profile['first_name'],
                'last_name' => $profile['last_name']
            ],
            'health_score' => $healthScore,
            'calories' => [
                'consumed' => intval($caloriesData['total_calories']),
                'goal' => isset($profile['daily_calorie_goal']) ? intval($profile['daily_calorie_goal']) : 2000
            ],
            'weight' => [
                'current' => isset($profile['current_weight']) && $profile['current_weight'] ? floatval($profile['current_weight']) : (isset($profile['weight']) && $profile['weight'] ? floatval($profile['weight']) : null),
                'target' => isset($profile['target_weight']) && $profile['target_weight'] ? floatval($profile['target_weight']) : null,
                'history' => $weightHistory
            ],
            'today_workout' => $todayWorkout ? [
                'name' => $todayWorkout['workout_name'],
                'duration' => intval($todayWorkout['duration_minutes'])
            ] : null,
            'insight' => $insight ? [
                'text' => $insight['insight_text'],
                'type' => $insight['insight_type']
            ] : null
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
