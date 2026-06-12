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
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing user_id']);
        exit();
    }
    
    // Get user basic info
    $userStmt = $pdo->prepare("
        SELECT u.first_name, u.last_name, u.email, up.motto, up.profile_picture, up.subscription_tier
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE u.id = ?
    ");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit();
    }
    
    // Get workout stats
    $workoutStatsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_workouts,
            COALESCE(AVG(duration_minutes), 0) as avg_duration,
            MAX(completed_date) as last_workout_date
        FROM workout_logs
        WHERE user_id = ?
    ");
    $workoutStatsStmt->execute([$userId]);
    $workoutStats = $workoutStatsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate streak
    $streakStmt = $pdo->prepare("
        SELECT completed_date
        FROM workout_logs
        WHERE user_id = ?
        ORDER BY completed_date DESC
    ");
    $streakStmt->execute([$userId]);
    $workoutDates = $streakStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $streak = 0;
    $currentDate = new DateTime();
    $currentDate->setTime(0, 0, 0);
    
    foreach ($workoutDates as $dateStr) {
        $workoutDate = new DateTime($dateStr);
        $workoutDate->setTime(0, 0, 0);
        $diff = $currentDate->diff($workoutDate)->days;
        
        if ($diff === $streak) {
            $streak++;
        } else {
            break;
        }
    }
    
    // Get personal records
    $prStmt = $pdo->prepare("
        SELECT exercise_name, MAX(weight_kg) as max_weight
        FROM personal_records
        WHERE user_id = ?
        GROUP BY exercise_name
        ORDER BY max_weight DESC
        LIMIT 2
    ");
    $prStmt->execute([$userId]);
    $personalRecords = $prStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent activities (last 5 workouts)
    $recentStmt = $pdo->prepare("
        SELECT 
            workout_name as title,
            duration_minutes,
            completed_date,
            'workout' as type
        FROM workout_logs
        WHERE user_id = ?
        ORDER BY completed_date DESC
        LIMIT 5
    ");
    $recentStmt->execute([$userId]);
    $recentActivities = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format recent activities
    foreach ($recentActivities as &$activity) {
        $activityDate = new DateTime($activity['completed_date']);
        $now = new DateTime();
        $diff = $now->diff($activityDate);
        
        if ($diff->days === 0) {
            $timeAgo = 'Today';
        } elseif ($diff->days === 1) {
            $timeAgo = 'Yesterday';
        } else {
            $timeAgo = $diff->days . ' days ago';
        }
        
        $activity['time'] = $timeAgo . ' • ' . $activity['duration_minutes'] . ' min';
        $activity['icon'] = 'fitness';
        $activity['color'] = '#10B981';
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'user' => [
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'motto' => $user['motto'] ?? 'Striving for 1% better every day',
                'profile_picture' => $user['profile_picture'] ?? null,
                'subscription_tier' => $user['subscription_tier'] ?? 'free'
            ],
            'stats' => [
                'total_workouts' => (int)$workoutStats['total_workouts'],
                'avg_duration' => round((float)$workoutStats['avg_duration']),
                'streak' => $streak
            ],
            'personal_records' => $personalRecords,
            'recent_activities' => $recentActivities
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
