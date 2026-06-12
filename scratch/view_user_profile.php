<?php
require_once __DIR__ . '/../backend/config/db_config.php';

$stmt = $pdo->query("
    SELECT up.*, u.first_name, u.email 
    FROM user_profiles up
    JOIN users u ON up.user_id = u.id
");
echo "=== User Profiles ===\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['user_id'] . " | Name: " . $row['first_name'] . " | Email: " . $row['email'] . "\n";
    echo "  Goal: " . $row['fitness_goal'] . " | Activity: " . $row['activity_level'] . "\n";
    echo "  Has Equipment: " . $row['has_equipment'] . " (Raw: " . var_export($row['has_equipment'], true) . ")\n";
}
?>
