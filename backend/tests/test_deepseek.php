<?php
require_once __DIR__ . '/../config/env_loader.php';
loadEnv(__DIR__ . '/../.env');
require_once __DIR__ . '/../config/deepseek_helper.php';

echo "--- Testing DeepSeek API Connection ---\n";

$pdo = null;
try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'fitrova_db';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
} catch (PDOException $e) {
    echo "Local DB not available, connecting to Aiven cloud DB...\n";
    $host = getenv('AIVEN_DB_HOST') ?: 'fitroval-db123-ibehpromise30-af36.g.aivencloud.com';
    $port = getenv('AIVEN_DB_PORT') ?: '11816';
    $dbname = getenv('AIVEN_DB_NAME') ?: 'defaultdb';
    $username = getenv('AIVEN_DB_USER') ?: 'avnadmin';
    $password = getenv('AIVEN_DB_PASS') ?: 'AVNS_Sz6-RnTLGjBHbi49wvp';
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
}

try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('ai_deepseek_api_key', 'ai_model_primary')");
    $settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $key = $settings['ai_deepseek_api_key'] ?? (getenv('DEEPSEEK_API_KEY') ?: '');
    $model = $settings['ai_model_primary'] ?? 'deepseek-chat';

    echo "Model: {$model}\n";
    echo "API Key Length: " . strlen($key) . "\n";
    echo "Key Prefix: " . substr($key, 0, 10) . "...\n";

    $prompt = 'Respond ONLY with a JSON object: {"status": "success", "message": "DeepSeek API is working perfectly!"}';
    
    echo "Sending test request to DeepSeek API...\n";
    $response = callDeepSeek($prompt, $key, 'You are a test assistant.', 0.7, $model);

    echo "\nRAW RESPONSE:\n" . $response . "\n\n";

    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['status'])) {
        echo "SUCCESS! DeepSeek API returned valid JSON.\n";
    } else {
        echo "WARNING: Response was received but JSON decoding had issues.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
