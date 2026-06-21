<?php
try {
    $host = 'fitroval-db123-ibehpromise30-af36.g.aivencloud.com';
    $port = '11816';
    $dbname = 'defaultdb';
    $username = 'avnadmin';
    $password = 'AVNS_Sz6-RnTLGjBHbi49wvp';

    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('ai_gemini_api_key', 'ai_model_primary')");
    $settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $key = $settings['ai_gemini_api_key'] ?? '';
    $model = $settings['ai_model_primary'] ?? 'gemini-1.5-flash';

    echo "Model: $model\n";
    echo "API Key length: " . strlen($key) . "\n";
    echo "Key: " . substr($key, 0, 8) . "...\n";

    // Test request to Gemini API
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key={$key}";
    $payload = [
        'contents' => [[
            'parts' => [['text' => 'Hello, respond with JSON: {"status": "ok"}']],
        ]],
        'generationConfig' => ['responseMimeType' => 'application/json'],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT,        15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    echo "HTTP Status Code: $httpCode\n";
    if ($curlErr) {
        echo "cURL Error: $curlErr\n";
    }
    
    $data = json_decode($response, true);
    if (isset($data['models'])) {
        echo "Available models that support generateContent:\n";
        foreach ($data['models'] as $m) {
            if (isset($m['supportedGenerationMethods']) && in_array('generateContent', $m['supportedGenerationMethods'])) {
                echo "  - " . $m['name'] . "\n";
            }
        }
    } else {
        echo "Response:\n";
        echo substr($response, 0, 1000) . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
