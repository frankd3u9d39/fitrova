<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/agent_router_helper.php';

echo "=== 1. Checking Database system_settings ===\n";
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('ai_agent_router_api_key', 'ai_model_primary', 'ai_provider')");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
print_r($settings);

echo "\n=== 2. Testing callAgentRouter with Fitrova Workout Prompt ===\n";
$prompt = 'Generate a 1-day beginner workout JSON: {"workout_name": "Full Body Starter", "exercises": [{"name": "Push Ups", "sets": 3, "reps": 10}]}';
$apiKey = $settings['ai_agent_router_api_key'] ?? '';

try {
    $result = callAgentRouter($prompt, $apiKey, $settings['ai_model_primary'] ?? 'claude-opus-5', 1500);
    echo "AI RESULT:\n" . $result . "\n";
    $json = json_decode($result, true);
    if ($json) {
        echo "✅ Valid JSON generated successfully!\n";
    } else {
        echo "⚠️ Response was not strict JSON, but received text successfully.\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
