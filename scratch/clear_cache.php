<?php
require_once __DIR__ . '/../backend/config/db_config.php';

$stmt = $pdo->prepare("DELETE FROM workout_plans WHERE plan_date = CURDATE()");
$stmt->execute();

echo "Workout plan cache cleared for today! A fresh regeneration will be triggered on the next request.\n";
?>
