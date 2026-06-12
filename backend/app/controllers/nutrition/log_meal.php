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
    
    if (!isset($input['user_id']) || !isset($input['meal_name']) || !isset($input['calories'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit();
    }
    
    $userId = intval($input['user_id']);
    $mealName = $input['meal_name'];
    $calories = intval($input['calories']);
    $protein = isset($input['protein']) ? floatval($input['protein']) : 0;
    $carbs = isset($input['carbs']) ? floatval($input['carbs']) : 0;
    $fats = isset($input['fats']) ? floatval($input['fats']) : 0;
    $mealType = isset($input['meal_type']) ? strtolower($input['meal_type']) : 'snack';
    $loggedDate = date('Y-m-d');
    
    // Validate meal type
    $allowedTypes = ['breakfast', 'lunch', 'dinner', 'snack'];
    if (!in_array($mealType, $allowedTypes)) {
        $mealType = 'snack';
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO nutrition_logs (user_id, meal_name, calories, protein, carbs, fats, meal_type, logged_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$userId, $mealName, $calories, $protein, $carbs, $fats, $mealType, $loggedDate]);
    $loggedMealId = $pdo->lastInsertId();

    // Insert AI insight alert if present
    $insightText = isset($input['insight_text']) && !empty($input['insight_text']) ? trim($input['insight_text']) : null;
    $insightType = isset($input['insight_type']) && !empty($input['insight_type']) ? trim($input['insight_type']) : 'tip';

    if ($insightText) {
        // Mark previous alerts as read to keep dashboard clean, then insert new one
        $pdo->prepare("UPDATE ai_insights SET is_read = TRUE WHERE user_id = ? AND insight_type = ?")
            ->execute([$userId, $insightType]);

        $insightStmt = $pdo->prepare("
            INSERT INTO ai_insights (user_id, insight_text, insight_type, is_read)
            VALUES (?, ?, ?, FALSE)
        ");
        $insightStmt->execute([$userId, $insightText, $insightType]);
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Meal logged successfully',
        'data' => [
            'id' => $loggedMealId
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
