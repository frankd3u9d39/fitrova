<?php
require_once __DIR__ . '/../backend/config/db_config.php';

$stmt = $pdo->query("DESCRIBE user_profiles");
echo "=== Columns in user_profiles ===\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
