<?php
// backend/tests/test_log_insight_dismiss.php

require_once __DIR__ . '/../config/db_config.php';

$userId = 6;
$insightText = "Test eating pattern warning alert!";
$insightType = "warning";

// Clean up any existing unread insights first
$pdo->prepare("UPDATE ai_insights SET is_read = TRUE WHERE user_id = ?")->execute([$userId]);

// 1. Call log_meal.php to log a meal with an insight alert
echo "1. Logging a meal with an AI insight warning...\n";
$urlLog = 'http://localhost/Fitrova/backend/app/controllers/nutrition/log_meal.php';
$payloadLog = json_encode([
    'user_id' => $userId,
    'meal_name' => 'Test Healthy Salad',
    'calories' => 200,
    'protein' => 10,
    'carbs' => 15,
    'fats' => 5,
    'meal_type' => 'lunch',
    'insight_text' => $insightText,
    'insight_type' => $insightType
]);

$ch = curl_init($urlLog);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadLog);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$resLog = curl_exec($ch);
curl_close($ch);
echo "Log Meal Response: $resLog\n\n";

$logData = json_decode($resLog, true);
$loggedMealId = $logData['data']['id'] ?? null;

// 2. Call get_nutrition_data.php to check if the latest_insight is returned
echo "2. Fetching nutrition data (expecting active warning)...\n";
$urlGet = 'http://localhost/Fitrova/backend/app/controllers/nutrition/get_nutrition_data.php';
$payloadGet = json_encode(['user_id' => $userId]);

$ch = curl_init($urlGet);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadGet);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$resGet = curl_exec($ch);
curl_close($ch);
echo "Get Nutrition Data Response (Truncated):\n";
$getData = json_decode($resGet, true);
if (isset($getData['data']['latest_insight'])) {
    print_r($getData['data']['latest_insight']);
} else {
    echo "latest_insight not found in response!\n";
}
echo "\n";

// 3. Call dismiss_insight.php to mark insights as read
echo "3. Dismissing active insights...\n";
$urlDismiss = 'http://localhost/Fitrova/backend/app/controllers/nutrition/dismiss_insight.php';
$payloadDismiss = json_encode(['user_id' => $userId]);

$ch = curl_init($urlDismiss);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadDismiss);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$resDismiss = curl_exec($ch);
curl_close($ch);
echo "Dismiss Response: $resDismiss\n\n";

// 4. Call get_nutrition_data.php again to verify latest_insight is now null
echo "4. Fetching nutrition data again (expecting latest_insight to be null or empty)...\n";
$ch = curl_init($urlGet);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadGet);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$resGet2 = curl_exec($ch);
curl_close($ch);
$getData2 = json_decode($resGet2, true);
if (isset($getData2['data']['latest_insight'])) {
    echo "latest_insight after dismiss: ";
    print_r($getData2['data']['latest_insight']);
} else {
    echo "latest_insight is now null (Correct!)\n";
}
echo "\n";

// 5. Cleanup the database
echo "5. Cleaning up test data from DB...\n";
if ($loggedMealId) {
    $pdo->prepare("DELETE FROM nutrition_logs WHERE id = ?")->execute([$loggedMealId]);
}
$pdo->prepare("DELETE FROM ai_insights WHERE user_id = ? AND insight_text = ?")->execute([$userId, $insightText]);
echo "Cleanup completed successfully.\n";
