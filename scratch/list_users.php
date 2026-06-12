<?php
require_once __DIR__ . '/../backend/config/db_config.php';

$stmt = $pdo->query("SELECT u.id, u.email, u.first_name, up.fitness_goal, up.activity_level, up.has_equipment FROM users u LEFT JOIN user_profiles up ON u.id = up.user_id");
echo "=== Users and Profiles ===\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
?>
