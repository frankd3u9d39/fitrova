<?php
require_once __DIR__ . '/../backend/config/db_config.php';

try {
    echo "=== USERS IN DATABASE ===\n";
    $users = $pdo->query("SELECT id, first_name, email FROM users")->fetchAll();
    print_r($users);
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
