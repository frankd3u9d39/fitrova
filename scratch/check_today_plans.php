<?php
require_once __DIR__ . '/../backend/config/db_config.php';

echo "Today's plans in workout_plans for user 6:\n";
$stmt = $pdo->prepare("SELECT id, plan_date, created_at FROM workout_plans WHERE user_id = 6 AND plan_date = CURDATE()");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\nLatest generated workout in workout_plans for user 6:\n";
$stmt = $pdo->prepare("SELECT id, plan_date, created_at FROM workout_plans WHERE user_id = 6 ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\nAll exercise names in exercise_library:\n";
$stmt = $pdo->query("SELECT id, name FROM exercise_library");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
