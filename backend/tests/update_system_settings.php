<?php
// backend/tests/update_system_settings.php

require_once __DIR__ . '/../config/db_config.php';

try {
    // Check if ai_model_primary already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'ai_model_primary'");
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        $updateStmt = $pdo->prepare("UPDATE system_settings SET setting_value = 'gemini-1.5-flash' WHERE setting_key = 'ai_model_primary'");
        $updateStmt->execute();
        echo "Updated existing ai_model_primary setting to 'gemini-1.5-flash'.\n";
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_model_primary', 'gemini-1.5-flash')");
        $insertStmt->execute();
        echo "Inserted new ai_model_primary setting set to 'gemini-1.5-flash'.\n";
    }

    // Verify
    $verifyStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'ai_model_primary'");
    $verifyStmt->execute();
    $val = $verifyStmt->fetchColumn();
    echo "Verification: ai_model_primary = '$val'\n";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
