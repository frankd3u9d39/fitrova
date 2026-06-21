<?php
try {
    $host = 'fitroval-db123-ibehpromise30-af36.g.aivencloud.com';
    $port = '11816';
    $dbname = 'defaultdb';
    $username = 'avnadmin';
    $password = 'AVNS_Sz6-RnTLGjBHbi49wvp';

    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== AIVEN USER_PROFILES SCHEMA ===\n";
    $q = $pdo->query("DESCRIBE user_profiles");
    while($row = $q->fetch()) {
        echo "{$row['Field']} - {$row['Type']}\n";
    }

    echo "\n=== AIVEN SYSTEM_SETTINGS ===\n";
    $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    print_r($settings);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
