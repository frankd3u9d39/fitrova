<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Prevent PHP warnings from breaking JSON
error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db_config.php';

$GEMINI_API_KEY = 'AQ.Ab8RN6K04-jc_xK7I1yOSz291VKJ1pwm0j5izQMReuOhalV7uA';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $userId = $input['user_id'] ?? 1;
    $imageBase64 = $input['image'] ?? null;
    $exerciseName = $input['exercise'] ?? 'Detect automatically';

    if (!$imageBase64) {
        throw new Exception('Image data required');
    }

    // Clean base64 string (remove data:image/jpeg;base64, prefix if present)
    if (strpos($imageBase64, ',') !== false) {
        $imageBase64 = explode(',', $imageBase64)[1];
    }

    // Gemini Vision API Call
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . $GEMINI_API_KEY;
    
    $prompt = "You are an AI Personal Trainer. Analyze this user's workout form in the image. 
    The user is performing: $exerciseName.
    Please identify:
    1. If the form is good or needs improvement.
    2. Provide 2-3 specific, actionable tips to improve safety or efficiency.
    3. Detect which exercise they are doing if it wasn't provided.
    
    Return ONLY valid JSON in this format:
    {
      \"status\": \"GOOD\" | \"IMPROVEMENT_NEEDED\",
      \"detected_exercise\": \"Exercise Name\",
      \"score\": 0 to 100,
      \"tips\": [\"Tip 1\", \"Tip 2\"],
      \"summary\": \"Brief encouraging summary\"
    }";

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => 'image/jpeg',
                            'data' => $imageBase64
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'response_mime_type' => 'application/json'
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Gemini Vision API error: ' . $response);
    }

    $result = json_decode($response, true);
    $analysisText = $result['candidates'][0]['content']['parts'][0]['text'];
    
    // Clean JSON: Remove markdown wrappers if present
    $analysisText = preg_replace('/```json\s*/', '', $analysisText);
    $analysisText = preg_replace('/```\s*$/', '', $analysisText);
    
    // Verify it is valid JSON before sending
    $testJson = json_decode(trim($analysisText), true);
    if (!$testJson) {
        throw new Exception('AI generated invalid JSON: ' . $analysisText);
    }
    
    echo trim($analysisText);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'ERROR',
        'message' => $e->getMessage()
    ]);
}
?>
