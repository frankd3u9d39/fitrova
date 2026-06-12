<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

// Prevent warnings from breaking JSON
error_reporting(0);
ini_set('display_errors', 0);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? null;
    $workoutName = $input['workout_name'] ?? 'Custom Workout';

    if (!$userId) {
        throw new Exception('User ID required');
    }

    // AI Configuration (Re-using Gemini logic from main script)
    // Fetch dynamic configuration
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key = 'ai_gemini_api_key'");
    $settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $GEMINI_API_KEY = $settings['ai_gemini_api_key'] ?? '';
    
    $prompt = "Trainer AI. Generate workout plan for '$workoutName'. Strict JSON:\n";
    $prompt .= "{\n";
    $prompt .= '  "name": "' . $workoutName . '",';
    $prompt .= '  "exercises": [{"name": "Exercise", "sets": 3, "reps": 12, "instructions": "Form instructions", "search_term": "name"}],';
    $prompt .= '  "duration": 45, "difficulty": "intermediate", "type": "strength"';
    $prompt .= "\n}";

    // Call Gemini (Optimized and simplified)
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . $GEMINI_API_KEY;
    $data = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 1500,
            'response_mime_type' => 'application/json'
        ]
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('cURL Error: ' . $curlError);
    }

    if ($httpCode !== 200) {
        throw new Exception('Gemini API error (HTTP ' . $httpCode . '): ' . $response);
    }

    $result = json_decode($response, true);
    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception('Invalid response structure from Gemini API: ' . $response);
    }
    $aiJson = $result['candidates'][0]['content']['parts'][0]['text'];
    
    // Clean JSON
    $aiJson = preg_replace('/```json\s*/', '', $aiJson);
    $aiJson = preg_replace('/```\s*$/', '', $aiJson);
    $workout = json_decode(trim($aiJson), true);

    if (!$workout) throw new Exception('AI failed to generate valid workout JSON');

    // Add video fallbacks (Re-using from main script)
    foreach ($workout['exercises'] as &$ex) {
        $ex['video_url'] = 'https://videos.pexels.com/video-files/5510141/5510141-hd_1080_1920_25fps.mp4'; // Default
    }

    echo json_encode([
        'status' => 'success',
        'workout' => $workout
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
