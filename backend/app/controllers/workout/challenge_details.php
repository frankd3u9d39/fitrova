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

// Self-Heal Database: Ensure user_connections table exists
try {
    $connectionsTable = $pdo->query("SHOW TABLES LIKE 'user_connections'")->fetch();
    if (!$connectionsTable) {
        ob_start();
        require_once __DIR__ . '/../../../scripts/setup_production_db.php';
        ob_end_clean();
    }
} catch (PDOException $e) {
    error_log("Database self-healing failed in challenge_details.php: " . $e->getMessage());
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['user_id']) || !isset($input['challenge_key'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'User ID and challenge key are required']);
        exit();
    }

    $userId = intval($input['user_id']);
    $challengeKey = trim($input['challenge_key']);

    // 1. Fetch user profiles to configure target values
    $profileStmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
    $profileStmt->execute([$userId]);
    $profile = $profileStmt->fetch();
    
    $weight = isset($profile['weight']) ? floatval($profile['weight']) : (isset($profile['current_weight']) ? floatval($profile['current_weight']) : 75);
    $target = isset($profile['target_weight']) ? floatval($profile['target_weight']) : 70;
    $calorieGoal = isset($profile['daily_calorie_goal']) ? intval($profile['daily_calorie_goal']) : 2000;
    $proteinGoal = round(($calorieGoal * 0.30) / 4);

    $title = "Fitness Challenge";
    $description = "Challenge description";
    $difficulty = "Intermediate";
    $duration = "7 Days";
    $targetValue = 0;

    switch ($challengeKey) {
        case 'weight_shred_loss':
            $title = 'Calorie Deficit Push';
            $description = "Stay below your calorie target of $calorieGoal kcal for 5 days to support shedding weight.";
            $difficulty = 'Intermediate';
            $duration = '5 Days';
            $targetValue = $calorieGoal;
            break;
        case 'muscle_growth_bulk':
            $title = 'Bulking Macro Target';
            $description = "Consume at least ${proteinGoal}g of protein daily for 7 days to support lean muscle gain.";
            $difficulty = 'Advanced';
            $duration = '7 Days';
            $targetValue = $proteinGoal;
            break;
        case 'weight_maintenance_stabilize':
            $title = 'Daily Calorie Balance';
            $description = "Keep within 100 kcal of your daily budget ($calorieGoal kcal) for 5 consecutive days.";
            $difficulty = 'Beginner';
            $duration = '5 Days';
            $targetValue = $calorieGoal;
            break;
        case 'hiit_stamina_blast':
            $title = 'HIIT Stamina Shred';
            $description = 'Perform 4 high-intensity interval training workouts this week to accelerate progress.';
            $difficulty = 'Advanced';
            $duration = '7 Days';
            $targetValue = 4;
            break;
        case 'cardio_consistency_run':
            $title = 'Cardio Consistency';
            $description = 'Log at least 30 minutes of cardio exercise on 3 different days this week.';
            $difficulty = 'Intermediate';
            $duration = '7 Days';
            $targetValue = 3;
            break;
        case 'hydration_hero_water':
            $title = 'Hydration Hero';
            $description = 'Log at least 3.0 liters of water daily for 7 consecutive days to optimize cellular hydration.';
            $difficulty = 'Beginner';
            $duration = '7 Days';
            $targetValue = 3;
            break;
    }

    // 2. Fetch challenge participants
    $participantsStmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, up.profile_picture, up.motto
        FROM user_challenges uc
        JOIN users u ON u.id = uc.user_id
        LEFT JOIN user_profiles up ON up.user_id = u.id
        WHERE uc.challenge_key = ?
    ");
    $participantsStmt->execute([$challengeKey]);
    $participantsRaw = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Map connection status relative to the current user
    $participants = [];
    foreach ($participantsRaw as $p) {
        $pId = intval($p['id']);
        $initials = strtoupper(substr($p['first_name'] ?? 'U', 0, 1) . substr($p['last_name'] ?? '', 0, 1));
        
        $connStatus = 'not_connected';
        if ($pId === $userId) {
            $connStatus = 'self';
        } else {
            $connStmt = $pdo->prepare("
                SELECT requester_id, receiver_id, status 
                FROM user_connections 
                WHERE (requester_id = ? AND receiver_id = ?) 
                   OR (requester_id = ? AND receiver_id = ?)
            ");
            $connStmt->execute([$userId, $pId, $pId, $userId]);
            $conn = $connStmt->fetch();

            if ($conn) {
                if ($conn['status'] === 'accepted') {
                    $connStatus = 'connected';
                } else if ($conn['status'] === 'pending') {
                    if (intval($conn['requester_id']) === $userId) {
                        $connStatus = 'pending_sent';
                    } else {
                        $connStatus = 'pending_received';
                    }
                }
            }
        }

        $colors = ['#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899'];
        $color = $colors[$pId % count($colors)];

        $participants[] = [
            'id' => $pId,
            'first_name' => $p['first_name'],
            'last_name' => $p['last_name'],
            'initials' => $initials,
            'color' => $color,
            'profile_picture' => $p['profile_picture'],
            'motto' => $p['motto'] ?? 'Striving for 1% better every day',
            'connection_status' => $connStatus,
            'is_me' => ($pId === $userId)
        ];
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'challenge' => [
                'key' => $challengeKey,
                'title' => $title,
                'description' => $description,
                'difficulty' => $difficulty,
                'duration' => $duration,
                'target_value' => $targetValue
            ],
            'participants' => $participants
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
