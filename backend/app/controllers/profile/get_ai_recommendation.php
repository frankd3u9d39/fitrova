<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Gemini API Configuration
$GEMINI_API_KEY = 'AQ.Ab8RN6K04-jc_xK7I1yOSz291VKJ1pwm0j5izQMReuOhalV7uA';
$models = ['gemini-1.5-flash', 'gemini-1.5-flash-latest', 'gemini-pro'];

$input = json_decode(file_get_contents('php://input'), true);

$age = $input['age'] ?? 'unknown';
$gender = $input['gender'] ?? 'unknown';
$height = $input['height'] ?? 'unknown';
$weight = $input['weight'] ?? 'unknown';
$activityLevel = $input['activityLevel'] ?? 'Moderate';
$goal = $input['goal'] ?? 'General Health';

$prompt = "You are a professional fitness AI coach for the Fitrova app. 
Analyze the following user profile and provide a concise, encouraging, and highly specific recommendation for their target weight and timeline.

User Profile:
- Age: {$age}
- Gender: {$gender}
- Height: {$height}cm
- Current Weight: {$weight}kg
- Activity Level: {$activityLevel}
- Primary Goal: {$goal}

Your response should be:
1. A concise recommendation (max 150 characters).
2. Professional but friendly.
3. Focused on what's healthy and sustainable for THEIR specific data.
4. Include a suggested target weight if applicable.

Return the result ONLY as a JSON object with this structure:
{
  \"recommendation\": \"Your custom text here\",
  \"suggested_target_weight\": 70.5,
  \"suggested_weeks\": 12
}";

$ai_data = null;
$error_details = [];

foreach ($models as $modelName) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$GEMINI_API_KEY}";
    
    $payload = [
        "contents" => [["parts" => [["text" => $prompt]]]],
        "generationConfig" => ["response_mime_type" => "application/json"]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        $ai_text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($ai_text) {
            $ai_data = json_decode($ai_text, true);
            if ($ai_data) break;
        }
    } else {
        $error_details[] = "Model {$modelName} failed with code {$httpCode}";
    }
}

if ($ai_data) {
    echo json_encode(['status' => 'success', 'data' => $ai_data]);
} else {
    http_response_code(503);
    echo json_encode([
        'status' => 'error', 
        'message' => 'AI coach is temporarily over capacity. Please try selecting your goal again in a moment.',
        'details' => $error_details
    ]);
}
?>
