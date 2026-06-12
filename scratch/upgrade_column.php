<?php
require_once __DIR__ . '/../backend/config/db_config.php';

try {
    $pdo->exec("ALTER TABLE user_profiles MODIFY COLUMN profile_picture MEDIUMTEXT DEFAULT NULL");
    echo "Column 'profile_picture' modified to MEDIUMTEXT successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
