<?php
require_once __DIR__ . '/../config/env_loader.php';
loadEnv(__DIR__ . '/../.env');

$deepseekKey = getenv('DEEPSEEK_API_KEY') ?: ($argv[1] ?? '');
$primaryModel = 'deepseek-chat';
$primaryProvider = 'deepseek';

if (empty($deepseekKey)) {
    die("Error: DEEPSEEK_API_KEY not found in .env or passed arguments.\n");
}

$pdo = null;
try {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'fitrova_db';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
} catch (PDOException $e) {
    echo "Connecting to Aiven cloud database...\n";
    $host = getenv('AIVEN_DB_HOST') ?: 'fitroval-db123-ibehpromise30-af36.g.aivencloud.com';
    $port = getenv('AIVEN_DB_PORT') ?: '11816';
    $dbname = getenv('AIVEN_DB_NAME') ?: 'defaultdb';
    $username = getenv('AIVEN_DB_USER') ?: 'avnadmin';
    $password = getenv('AIVEN_DB_PASS') ?: 'AVNS_Sz6-RnTLGjBHbi49wvp';
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");

    // 1. Update/Insert DeepSeek Key
    $stmt->execute(['ai_deepseek_api_key']);
    if ($stmt->fetchColumn() > 0) {
        $updateStmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, category = 'ai', description = 'API Key for DeepSeek AI Services' WHERE setting_key = 'ai_deepseek_api_key'");
        $updateStmt->execute([$deepseekKey]);
        echo "Updated ai_deepseek_api_key.\n";
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, category, description) VALUES ('ai_deepseek_api_key', ?, 'ai', 'API Key for DeepSeek AI Services')");
        $insertStmt->execute([$deepseekKey]);
        echo "Inserted ai_deepseek_api_key.\n";
    }

    // 2. Update Primary Model
    $stmt->execute(['ai_model_primary']);
    if ($stmt->fetchColumn() > 0) {
        $updateStmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, category = 'ai' WHERE setting_key = 'ai_model_primary'");
        $updateStmt->execute([$primaryModel]);
        echo "Updated ai_model_primary to {$primaryModel}.\n";
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, category, description) VALUES ('ai_model_primary', ?, 'ai', 'The main AI model for workout generation')");
        $insertStmt->execute([$primaryModel]);
        echo "Inserted ai_model_primary as {$primaryModel}.\n";
    }

    // 3. Update Primary Provider
    $stmt->execute(['ai_provider']);
    if ($stmt->fetchColumn() > 0) {
        $updateStmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, category = 'ai' WHERE setting_key = 'ai_provider'");
        $updateStmt->execute([$primaryProvider]);
        echo "Updated ai_provider to {$primaryProvider}.\n";
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, category, description) VALUES ('ai_provider', ?, 'ai', 'Primary AI Provider (deepseek/gemini/gemma)')");
        $insertStmt->execute([$primaryProvider]);
        echo "Inserted ai_provider as {$primaryProvider}.\n";
    }

    echo "DeepSeek settings updated cleanly!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
