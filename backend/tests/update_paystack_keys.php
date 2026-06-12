<?php
// backend/tests/update_paystack_keys.php

require_once __DIR__ . '/../config/db_config.php';

$keys = [
    'paystack_public_key' => 'pk_test_79608bf67c6be506b8da88cc7f7b7970907c252d',
    'paystack_secret_key' => 'sk_test_5ce313cab010d1618ed9719374793c84b620b8d4'
];

try {
    foreach ($keys as $key => $value) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $update = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            $update->execute([$value, $key]);
            echo "Updated '$key' successfully.\n";
        } else {
            $insert = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
            $insert->execute([$key, $value]);
            echo "Inserted '$key' successfully.\n";
        }
    }

    echo "\nVerification:\n";
    foreach (array_keys($keys) as $key) {
        $verify = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $verify->execute([$key]);
        echo " - $key: " . $verify->fetchColumn() . "\n";
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
