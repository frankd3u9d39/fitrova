<?php
require_once __DIR__ . '/../backend/config/db_config.php';

$settingsStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'ai_gemini_api_key'");
$GEMINI_API_KEY = $settingsStmt->fetchColumn() ?: '';

echo "Using API Key: " . substr($GEMINI_API_KEY, 0, 10) . "...\n";

$url = "https://generativelanguage.googleapis.com/v1beta/models?key={$GEMINI_API_KEY}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$data = json_decode($response, true);

if (isset($data['models'])) {
    foreach ($data['models'] as $m) {
        echo $m['name'] . " (" . $m['displayName'] . ")\n";
    }
} else {
    echo "No models found: " . $response . "\n";
}
?>
