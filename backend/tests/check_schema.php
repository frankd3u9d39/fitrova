<?php
require_once __DIR__ . '/../config/db_config.php';

try {
    echo "=== USER_PROFILES SCHEMA ===\n";
    $q = $pdo->query("DESCRIBE user_profiles");
    while($row = $q->fetch()) {
        echo "{$row['Field']} - {$row['Type']}\n";
    }

    echo "\n=== SYSTEM_SETTINGS ===\n";
    $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    print_r($settings);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
