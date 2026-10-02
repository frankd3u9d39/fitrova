<?php
require __DIR__ . '/../config/agent_router_helper.php';
require __DIR__ . '/../config/db_config.php';

// Optional: pass a key as a CLI arg to test it without touching the DB.
// php scripts/test_claude_key.php "sk-..."
$key = $argv[1] ?? null;
if ($key) {
    echo "Testing key passed via CLI arg.\n";
} else {
    $key = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='ai_agent_router_api_key'")->fetchColumn();
    echo "Testing key stored in DB.\n";
}
echo "Key length: " . strlen($key) . "\n";

try {
    $result = callClaude('Reply with exactly this and nothing else: {"ok": true}', $key, 'claude-opus-5', 100, 0.7, [], 30);
    echo "SUCCESS: " . $result . "\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
