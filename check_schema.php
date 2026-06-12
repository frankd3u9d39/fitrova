<?php
require_once __DIR__ . '/backend/config/db_config.php';

try {
    echo "Columns in users:\n";
    $q1 = $pdo->query("DESCRIBE users");
    print_r($q1->fetchAll(PDO::FETCH_COLUMN, 0));

    echo "\nColumns in user_profiles:\n";
    $q2 = $pdo->query("DESCRIBE user_profiles");
    print_r($q2->fetchAll(PDO::FETCH_COLUMN, 0));

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
