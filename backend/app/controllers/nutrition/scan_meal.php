<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Gemini API Configuration
$GEMINI_API_KEY = 'AQ.Ab8RN6K04-jc_xK7I1yOSz291VKJ1pwm0j5izQMReuOhalV7uA';
$models = ['gemini-1.5-flash', 'gemini-2.0-flash', 'gemini-flash-latest'];

$input = json_decode(file_get_contents('php://input'), true);
$image_data = isset($input['image']) ? $input['image'] : null;

if (!$image_data) {
    echo json_encode(['status' => 'error', 'message' => 'No image provided']);
    exit();
}

// Remove base64 prefix if present
if (strpos($image_data, 'data:image') === 0) {
    $image_data = substr($image_data, strpos($image_data, ',') + 1);
}

$prompt = "Analyze this food image and provide the nutritional information. 
Return the result ONLY as a JSON object with the following structure:
{
  \"meal_name\": \"Descriptive name of the meal\",
  \"calories\": total_calories_as_number,
  \"protein\": grams_protein_as_number,
  \"carbs\": grams_carbs_as_number,
  \"fats\": grams_fats_as_number,
  \"items\": [
    {\"name\": \"item name\", \"amount\": \"estimated amount (e.g. 100g, 1 slice)\", \"calories\": item_calories}
  ]
}
Ensure the numbers are accurate estimates for the meal in the photo. Be precise but concise.";

$ai_text = null;
$last_error = '';

foreach ($models as $modelName) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$GEMINI_API_KEY}";
    
    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt],
                    [
                        "inline_data" => [
                            "mime_type" => "image/jpeg",
                            "data" => $image_data
                        ]
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "response_mime_type" => "application/json"
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        $ai_text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($ai_text) break;
    } else {
        $last_error = "Model {$modelName} failed: HTTP {$httpCode} | {$curlError} | {$response}";
        file_put_contents(__DIR__ . '/gemini_error.log', date('Y-m-d H:i:s') . " - " . $last_error . "\n", FILE_APPEND);
    }
}

if (!$ai_text) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'AI analysis failed after trying multiple models',
        'debug' => $last_error
    ]);
    exit();
}

// The AI should return valid JSON because of response_mime_type
$meal_data = json_decode($ai_text, true);

if (!$meal_data) {
    // Fallback in case the response isn't clean JSON
    preg_match('/\{.*\}/s', $ai_text, $matches);
    if (isset($matches[0])) {
        $meal_data = json_decode($matches[0], true);
    }
}

if ($meal_data) {
    echo json_encode([
        'status' => 'success',
        'data' => $meal_data
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'AI returned invalid format', 'raw' => $ai_text]);
}
