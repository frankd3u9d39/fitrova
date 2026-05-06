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
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
        exit();
    }
    
    $userId = intval($input['user_id']);
    $today = date('Y-m-d');
    
    // Get user profile goals
    $profileStmt = $pdo->prepare("SELECT daily_calorie_goal FROM user_profiles WHERE user_id = ?");
    $profileStmt->execute([$userId]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
    
    $calorieGoal = $profile ? intval($profile['daily_calorie_goal']) : 2000;
    
    // Default Macro Goals (Protein: 30%, Carbs: 40%, Fats: 30%)
    // Protein: 4 kcal/g, Carbs: 4 kcal/g, Fats: 9 kcal/g
    $proteinGoal = round(($calorieGoal * 0.30) / 4);
    $carbsGoal = round(($calorieGoal * 0.40) / 4);
    $fatsGoal = round(($calorieGoal * 0.30) / 9);
    
    // Get logged meals for today
    $mealsStmt = $pdo->prepare("
        SELECT id, meal_name, calories, protein, carbs, fats, meal_type, 
               DATE_FORMAT(created_at, '%h:%i %p') as meal_time
        FROM nutrition_logs
        WHERE user_id = ? AND logged_date = ?
        ORDER BY created_at ASC
    ");
    $mealsStmt->execute([$userId, $today]);
    $meals = $mealsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $totalCalories = 0;
    $totalProtein = 0;
    $totalCarbs = 0;
    $totalFats = 0;
    
    foreach ($meals as $key => $meal) {
        $totalCalories += $meal['calories'];
        $totalProtein += $meal['protein'];
        $totalCarbs += $meal['carbs'];
        $totalFats += $meal['fats'];
        
        // Add items array to match frontend structure (though currently we don't have a separate items table, 
        // we can just put the meal name as an item for now or just use the meal name directly)
        $meals[$key]['items'] = [
            ['name' => $meal['meal_name'], 'amount' => '1 serving', 'calories' => $meal['calories']]
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'goals' => [
                'calories' => $calorieGoal,
                'protein' => $proteinGoal,
                'carbs' => $carbsGoal,
                'fats' => $fatsGoal
            ],
            'totals' => [
                'calories' => $totalCalories,
                'protein' => $totalProtein,
                'carbs' => $totalCarbs,
                'fats' => $totalFats
            ],
            'remaining' => [
                'calories' => max(0, $calorieGoal - $totalCalories),
                'protein' => max(0, $proteinGoal - $totalProtein),
                'carbs' => max(0, $carbsGoal - $totalCarbs),
                'fats' => max(0, $fatsGoal - $totalFats)
            ],
            'meals' => $meals
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
