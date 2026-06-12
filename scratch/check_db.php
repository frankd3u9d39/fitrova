<?php
require_once __DIR__ . '/../backend/config/db_config.php';
echo "Columns in exercise_library:\n";
$stmt = $pdo->query("DESCRIBE exercise_library");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
