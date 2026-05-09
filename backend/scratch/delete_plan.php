<?php
require_once 'c:/xampp/htdocs/Fitrova/backend/config/db_config.php';
$pdo->query('DELETE FROM workout_plans WHERE plan_date = CURDATE()');
echo "Plan deleted\n";
?>
