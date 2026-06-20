<?php
require_once __DIR__ . '/../includes/auth_check.php';
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';

try {
    // 1. Total Users
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $newUsersThisWeek = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
    
    // 2. Active Today (Users who logged food or workout today)
    $activeToday = $pdo->query("
        SELECT COUNT(DISTINCT user_id) FROM (
            SELECT user_id FROM nutrition_logs WHERE logged_date = CURDATE()
            UNION
            SELECT user_id FROM workout_logs WHERE completed_date = CURDATE()
        ) as active
    ")->fetchColumn();
    
    // 3. AI Generations Today
    $aiCount = $pdo->query("SELECT COUNT(*) FROM workout_plans WHERE plan_date = CURDATE()")->fetchColumn();
    
    // 4. Calorie Surplus Alerts (Users who are > 500 kcal over goal)
    // This is a complex query, let's simplify for the dashboard
    $surplusAlerts = $pdo->query("
        SELECT COUNT(*) FROM (
            SELECT nl.user_id, SUM(nl.calories) as total_in, up.daily_calorie_goal
            FROM nutrition_logs nl
            JOIN user_profiles up ON nl.user_id = up.user_id
            WHERE nl.logged_date = CURDATE()
            GROUP BY nl.user_id
            HAVING total_in > up.daily_calorie_goal + 500
        ) as overeaters
    ")->fetchColumn();

    // 5. Recent Activity Feed (UNION query from 7 tables)
    $recentActivity = $pdo->query("
        SELECT 
            CONVERT('meal' USING utf8mb4) as type,
            nl.user_id,
            CONVERT(u.first_name USING utf8mb4) as first_name,
            CONVERT(u.last_name USING utf8mb4) as last_name,
            CONVERT(nl.meal_name USING utf8mb4) as workout_name,
            CONVERT(nl.calories USING utf8mb4) as extra_info,
            nl.created_at as completed_date
        FROM nutrition_logs nl
        JOIN users u ON nl.user_id = u.id

        UNION ALL

        SELECT 
            CONVERT('workout' USING utf8mb4) as type,
            wl.user_id,
            CONVERT(u.first_name USING utf8mb4) as first_name,
            CONVERT(u.last_name USING utf8mb4) as last_name,
            CONVERT(wl.workout_name USING utf8mb4) as workout_name,
            CONVERT(wl.duration_minutes USING utf8mb4) as extra_info,
            wl.created_at as completed_date
        FROM workout_logs wl
        JOIN users u ON wl.user_id = u.id

        UNION ALL

        SELECT 
            CONVERT('weight' USING utf8mb4) as type,
            wh.user_id,
            CONVERT(u.first_name USING utf8mb4) as first_name,
            CONVERT(u.last_name USING utf8mb4) as last_name,
            CONVERT(wh.weight USING utf8mb4) as workout_name,
            NULL as extra_info,
            wh.created_at as completed_date
        FROM weight_history wh
        JOIN users u ON wh.user_id = u.id

        UNION ALL

        SELECT 
            CONVERT('challenge_join' USING utf8mb4) as type,
            uc.user_id,
            CONVERT(u.first_name USING utf8mb4) as first_name,
            CONVERT(u.last_name USING utf8mb4) as last_name,
            CONVERT(uc.challenge_key USING utf8mb4) as workout_name,
            NULL as extra_info,
            uc.joined_at as completed_date
        FROM user_challenges uc
        JOIN users u ON uc.user_id = u.id

        UNION ALL

        SELECT 
            CONVERT('chat' USING utf8mb4) as type,
            cm.user_id,
            CONVERT(u.first_name USING utf8mb4) as first_name,
            CONVERT(u.last_name USING utf8mb4) as last_name,
            CONVERT(cm.challenge_key USING utf8mb4) as workout_name,
            CONVERT(cm.message USING utf8mb4) as extra_info,
            cm.created_at as completed_date
        FROM challenge_messages cm
        JOIN users u ON cm.user_id = u.id

        UNION ALL

        SELECT 
            CONVERT('form_check' USING utf8mb4) as type,
            fcl.user_id,
            CONVERT(u.first_name USING utf8mb4) as first_name,
            CONVERT(u.last_name USING utf8mb4) as last_name,
            CONVERT(fcl.exercise_name USING utf8mb4) as workout_name,
            CONVERT(fcl.score USING utf8mb4) as extra_info,
            fcl.created_at as completed_date
        FROM form_check_logs fcl
        JOIN users u ON fcl.user_id = u.id

        UNION ALL

        SELECT 
            CONVERT('payment' USING utf8mb4) as type,
            pt.user_id,
            CONVERT(u.first_name USING utf8mb4) as first_name,
            CONVERT(u.last_name USING utf8mb4) as last_name,
            CONVERT(pt.subscription_tier USING utf8mb4) as workout_name,
            CONVERT(pt.amount USING utf8mb4) as extra_info,
            pt.created_at as completed_date
        FROM payment_transactions pt
        JOIN users u ON pt.user_id = u.id

        ORDER BY completed_date DESC
        LIMIT 30
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 6. Calculate 7-day Utilization Trend
    $days = [];
    $labels = [];
    $completedDays = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $days[$date] = 0;
        $completedDays[$date] = 0;
        $labels[] = date('D', strtotime("-$i days"));
    }

    // Fetch generated plans count grouped by date
    try {
        $plansTrendStmt = $pdo->query("
            SELECT DATE(created_at) as plan_date, COUNT(*) as count 
            FROM workout_plans 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(created_at)
        ");
        while ($row = $plansTrendStmt->fetch(PDO::FETCH_ASSOC)) {
            $date = date('Y-m-d', strtotime($row['plan_date']));
            if (isset($days[$date])) {
                $days[$date] = (int)$row['count'];
            }
        }
    } catch (PDOException $e) {
        try {
            $plansTrendStmt = $pdo->query("
                SELECT plan_date, COUNT(*) as count 
                FROM workout_plans 
                WHERE plan_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                GROUP BY plan_date
            ");
            while ($row = $plansTrendStmt->fetch(PDO::FETCH_ASSOC)) {
                $date = $row['plan_date'];
                if (isset($days[$date])) {
                    $days[$date] = (int)$row['count'];
                }
            }
        } catch (PDOException $ex) {
            // Silently absorb
        }
    }

    // Fetch completed workouts count grouped by date
    try {
        $logsTrendStmt = $pdo->query("
            SELECT completed_date, COUNT(*) as count 
            FROM workout_logs 
            WHERE completed_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY completed_date
        ");
        while ($row = $logsTrendStmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['completed_date'];
            if (isset($completedDays[$date])) {
                $completedDays[$date] = (int)$row['count'];
            }
        }
    } catch (PDOException $e) {
        // Silently absorb
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'stats' => [
                'total_users' => (int)$userCount,
                'new_users_week' => (int)$newUsersThisWeek,
                'active_today' => (int)$activeToday,
                'ai_generations' => (int)$aiCount,
                'surplus_alerts' => (int)$surplusAlerts
            ],
            'recent_activity' => $recentActivity,
            'trends' => [
                'labels' => $labels,
                'ai_plans' => array_values($days),
                'completed_workouts' => array_values($completedDays)
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
