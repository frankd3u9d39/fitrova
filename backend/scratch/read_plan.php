<?php
require_once 'c:/xampp/htdocs/Fitrova/backend/config/db_config.php';
$stmt = $pdo->query('SELECT plan_data FROM workout_plans ORDER BY id DESC LIMIT 1');
$row = $stmt->fetch();
if ($row) {
    header('Content-Type: application/json');
    echo $row['plan_data'];
} else {
    echo "No plan found";
}
?>
