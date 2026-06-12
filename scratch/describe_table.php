<?php
require_once __DIR__ . '/../backend/config/db_config.php';

$stmt = $pdo->query("DESCRIBE exercise_library");
echo "=== Columns in exercise_library ===\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
?>
