<?php
/**
 * Upserts the Gemini API key + primary model into system_settings.
 *
 * Usage (run once from backend/):
 *   php scripts/update_gemini_key.php "AIzaSy-your-new-key" [model]
 *
 * The key is passed as a CLI argument rather than hardcoded here — a
 * previous version of this script had a live key committed directly in
 * the file, which Google's leak scanners detected and auto-revoked.
 * See scripts/set_agent_router_key.php for the same pattern.
 */

require_once __DIR__ . '/../config/db_config.php';

$newKey = $argv[1] ?? null;
if (!$newKey) {
    fwrite(STDERR, "Usage: php scripts/update_gemini_key.php \"AIzaSy-your-new-key\" [model]\n");
    exit(1);
}

$newModel = $argv[2] ?? 'gemini-3.1-flash-lite';

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");

    // Update or Insert Gemini API Key
    $stmt->execute(['ai_gemini_api_key']);
    if ($stmt->fetchColumn() > 0) {
        $updateStmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'ai_gemini_api_key'");
        $updateStmt->execute([$newKey]);
        echo "Updated ai_gemini_api_key.\n";
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_gemini_api_key', ?)");
        $insertStmt->execute([$newKey]);
        echo "Inserted ai_gemini_api_key.\n";
    }

    // Update Primary Model
    $stmt->execute(['ai_model_primary']);
    if ($stmt->fetchColumn() > 0) {
        $updateStmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'ai_model_primary'");
        $updateStmt->execute([$newModel]);
        echo "Updated ai_model_primary to $newModel.\n";
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_model_primary', ?)");
        $insertStmt->execute([$newModel]);
        echo "Inserted ai_model_primary as $newModel.\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
