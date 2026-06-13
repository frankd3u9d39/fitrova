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

// Self-Heal Database: Ensure user_challenges table exists
try {
    $challengesTable = $pdo->query("SHOW TABLES LIKE 'user_challenges'")->fetch();
    if (!$challengesTable) {
        ob_start();
        require_once __DIR__ . '/../../../scripts/setup_production_db.php';
        ob_end_clean();
    }
} catch (PDOException $e) {
    error_log("Database self-healing failed in get_dashboard_data.php: " . $e->getMessage());
}

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

    // Get today's calories burned
    $burnedStmt = $pdo->prepare("
        SELECT COALESCE(SUM(calories_burned), 0) as total_burned
        FROM workout_logs
        WHERE user_id = ? AND completed_date = ?
    ");
    $burnedStmt->execute([$userId, $today]);
    $burnedData = $burnedStmt->fetch(PDO::FETCH_ASSOC);
    $totalBurned = intval($burnedData['total_burned']);
    
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

    // Get user's joined challenges
    $joinedStmt = $pdo->prepare("SELECT challenge_key FROM user_challenges WHERE user_id = ?");
    $joinedStmt->execute([$userId]);
    $joinedKeys = $joinedStmt->fetchAll(PDO::FETCH_COLUMN);

    // Custom challenges generation (Gemma AI rules engine)
    $weight = isset($profile['weight']) ? floatval($profile['weight']) : (isset($profile['current_weight']) ? floatval($profile['current_weight']) : 75);
    $target = isset($profile['target_weight']) ? floatval($profile['target_weight']) : 70;
    $calorieGoal = isset($profile['daily_calorie_goal']) ? intval($profile['daily_calorie_goal']) : 2000;
    $proteinGoal = round(($calorieGoal * 0.30) / 4);

    // Dynamic daily offset (-2 to +2) based on date to make counts change every day
    $dayOfYear = intval(date('z'));
    $year = intval(date('Y'));
    $dailyOffset = (($dayOfYear * 7 + $year) % 5) - 2;

    $rawChallenges = [];

    // Challenge 1: Custom based on Weight Goal
    if ($weight > $target) {
        $rawChallenges[] = [
            'key' => 'weight_shred_loss',
            'title' => 'Calorie Deficit Push',
            'description' => "Stay below your calorie target of $calorieGoal kcal for 5 days to support shedding weight.",
            'difficulty' => 'Intermediate',
            'duration' => '5 Days',
            'target_value' => $calorieGoal,
            'base_participants' => 7,
        ];
    } else if ($weight < $target) {
        $rawChallenges[] = [
            'key' => 'muscle_growth_bulk',
            'title' => 'Bulking Macro Target',
            'description' => "Consume at least ${proteinGoal}g of protein daily for 7 days to support lean muscle gain.",
            'difficulty' => 'Advanced',
            'duration' => '7 Days',
            'target_value' => $proteinGoal,
            'base_participants' => 6,
        ];
    } else {
        $rawChallenges[] = [
            'key' => 'weight_maintenance_stabilize',
            'title' => 'Daily Calorie Balance',
            'description' => "Keep within 100 kcal of your daily budget ($calorieGoal kcal) for 5 consecutive days.",
            'difficulty' => 'Beginner',
            'duration' => '5 Days',
            'target_value' => $calorieGoal,
            'base_participants' => 5,
        ];
    }

    // Challenge 2: Cardio / Activity based on weight target diff
    $weightDiff = abs($weight - $target);
    if ($weightDiff > 5) {
        $rawChallenges[] = [
            'key' => 'hiit_stamina_blast',
            'title' => 'HIIT Stamina Shred',
            'description' => 'Perform 4 high-intensity interval training workouts this week to accelerate progress.',
            'difficulty' => 'Advanced',
            'duration' => '7 Days',
            'target_value' => 4,
            'base_participants' => 9,
        ];
    } else {
        $rawChallenges[] = [
            'key' => 'cardio_consistency_run',
            'title' => 'Cardio Consistency',
            'description' => 'Log at least 30 minutes of cardio exercise on 3 different days this week.',
            'difficulty' => 'Intermediate',
            'duration' => '7 Days',
            'target_value' => 3,
            'base_participants' => 6,
        ];
    }

    // Challenge 3: Water/Hydration/General
    $rawChallenges[] = [
        'key' => 'hydration_hero_water',
        'title' => 'Hydration Hero',
        'description' => 'Log at least 3.0 liters of water daily for 7 consecutive days to optimize cellular hydration.',
        'difficulty' => 'Beginner',
        'duration' => '7 Days',
        'target_value' => 3,
        'base_participants' => 11,
    ];

    // Mock participants database with profile picture URLs
    $mockPeople = [
        ['first_name' => 'Sarah', 'last_name' => 'Jenkins', 'initials' => 'SJ', 'color' => '#10B981', 'profile_picture' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150'],
        ['first_name' => 'Michael', 'last_name' => 'Chen', 'initials' => 'MC', 'color' => '#3B82F6', 'profile_picture' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150'],
        ['first_name' => 'Jessica', 'last_name' => 'Taylor', 'initials' => 'JT', 'color' => '#F59E0B', 'profile_picture' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=150'],
        ['first_name' => 'David', 'last_name' => 'Ross', 'initials' => 'DR', 'color' => '#EF4444', 'profile_picture' => null],
        ['first_name' => 'Emily', 'last_name' => 'Davis', 'initials' => 'ED', 'color' => '#8B5CF6', 'profile_picture' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150'],
        ['first_name' => 'James', 'last_name' => 'Wilson', 'initials' => 'JW', 'color' => '#EC4899', 'profile_picture' => null],
    ];

    $challenges = [];
    foreach ($rawChallenges as $idx => $chal) {
        $key = $chal['key'];
        $joined = in_array($key, $joinedKeys);
        $count = max(3, $chal['base_participants'] + $dailyOffset) + ($joined ? 1 : 0);
        
        // Take a deterministic slice of mock people based on index
        $sliceOffset = ($idx * 2) % count($mockPeople);
        $participants = array_slice($mockPeople, $sliceOffset, 3);
        if ($joined) {
            // Put current user first in list
            $currentUser = [
                'first_name' => $profile['first_name'] ?? 'You',
                'last_name' => $profile['last_name'] ?? '',
                'initials' => strtoupper(substr($profile['first_name'] ?? 'Y', 0, 1) . substr($profile['last_name'] ?? '', 0, 1)),
                'color' => '#10B981',
                'profile_picture' => $profile['profile_picture'] ?? null,
                'is_me' => true
            ];
            array_unshift($participants, $currentUser);
            // remove last one to keep size 3
            if (count($participants) > 3) {
                array_pop($participants);
            }
        }

        $challenges[] = [
            'id' => $idx + 1,
            'key' => $key,
            'title' => $chal['title'],
            'description' => $chal['description'],
            'difficulty' => $chal['difficulty'],
            'duration' => $chal['duration'],
            'target_value' => $chal['target_value'],
            'participants_count' => $count,
            'joined' => $joined,
            'participants' => $participants
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'user' => [
                'first_name' => $profile['first_name'],
                'last_name' => $profile['last_name'],
                'profile_picture' => $profile['profile_picture'] ?? null
            ],
            'health_score' => $healthScore,
            'calories' => [
                'consumed' => max(0, intval($caloriesData['total_calories']) - $totalBurned),
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
            ] : null,
            'challenges' => $challenges
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
