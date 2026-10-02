<?php
/**
 * Upserts the Agent Router (Claude) API key + default model into
 * system_settings, replacing the Gemini key as the active AI provider.
 *
 * Usage (run once from backend/):
 *   php scripts/set_agent_router_key.php "sk-your-agent-router-key"
 *
 * The key is passed as a CLI argument rather than hardcoded here so it
 * never ends up committed to git (see scripts/update_gemini_key.php for
 * why that matters — it has a live key checked into history).
 */

require_once __DIR__ . '/../config/db_config.php';

$newKey = $argv[1] ?? null;
if (!$newKey) {
    fwrite(STDERR, "Usage: php scripts/set_agent_router_key.php \"sk-your-agent-router-key\"\n");
    exit(1);
}

$newModel = 'claude-opus-5';

function upsertSetting(PDO $pdo, string $key, string $value, string $category = 'ai', string $description = ''): void {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);

    if ($stmt->fetchColumn() > 0) {
        $update = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $update->execute([$value, $key]);
        echo "Updated {$key}.\n";
    } else {
        $insert = $pdo->prepare(
            "INSERT INTO system_settings (setting_key, setting_value, category, description) VALUES (?, ?, ?, ?)"
        );
        $insert->execute([$key, $value, $category, $description]);
        echo "Inserted {$key}.\n";
    }
}

try {
    upsertSetting(
        $pdo,
        'ai_agent_router_api_key',
        $newKey,
        'ai',
        'API key for Agent Router (agentrouter.org), used to call Claude.'
    );
    upsertSetting(
        $pdo,
        'ai_model_primary',
        $newModel,
        'ai',
        'Primary AI model for workout/nutrition/form-check generation.'
    );
    echo "Done. Primary model set to {$newModel}.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
