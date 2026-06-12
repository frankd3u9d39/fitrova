<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';

try {
    // 1. Total Users
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
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

    // 5. Recent Activity Feed
    $recentActivity = [];
    
    // Recent Workouts
    $workouts = $pdo->query("
        SELECT wl.workout_name, wl.completed_date, u.first_name, 'workout' as type
        FROM workout_logs wl
        JOIN users u ON wl.user_id = u.id
        ORDER BY wl.created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent Meals
    $meals = $pdo->query("
        SELECT nl.meal_name as workout_name, nl.logged_date as completed_date, u.first_name, 'meal' as type
        FROM nutrition_logs nl
        JOIN users u ON nl.user_id = u.id
        ORDER BY nl.created_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $recentActivity = array_merge($workouts, $meals);
    usort($recentActivity, function($a, $b) {
        return strtotime($b['completed_date']) - strtotime($a['completed_date']);
    });
    $recentActivity = array_slice($recentActivity, 0, 5);

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
