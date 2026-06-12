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

// Fetch dynamic configuration
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('ai_gemini_api_key', 'ai_model_primary')");
$settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$GEMINI_API_KEY = $settings['ai_gemini_api_key'] ?? '';
$primaryModel = $settings['ai_model_primary'] ?? '';

// Build models fallback list
$models = [];
if (!empty($primaryModel)) {
    $models[] = $primaryModel;
}
$fallbackModels = ['gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-2.0-flash', 'gemini-2.5-flash', 'gemini-3.5-flash', 'gemini-flash-latest'];
foreach ($fallbackModels as $fm) {
    if ($fm !== $primaryModel) {
        $models[] = $fm;
    }
}

$input = json_decode(file_get_contents('php://input'), true);
$image_data = isset($input['image']) ? $input['image'] : null;
$userId = isset($input['user_id']) ? intval($input['user_id']) : null;

// Enforce paywall gatekeeper check
// Free users get 1 trial scan; after that they need Advanced Premium
require_once __DIR__ . '/../../middleware/AISubscriptionGate.php';
$scanAccessResult = \App\Middleware\AISubscriptionGate::verifyAccess($pdo, $userId, 'advanced_premium', 'AI Meal Scanner', 'scan_trial_used');

if (!$image_data) {
    echo json_encode(['status' => 'error', 'message' => 'No image provided']);
    exit();
}

// Remove base64 prefix if present
if (strpos($image_data, 'data:image') === 0) {
    $image_data = substr($image_data, strpos($image_data, ',') + 1);
}

// Load user profile and 7-day meal history if user_id is provided
$profile = null;
$recent_meals = [];

if ($userId) {
    // 1. Get user profile
    $profileStmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
    $profileStmt->execute([$userId]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Get user's logged meals for the last 7 days
    $mealsStmt = $pdo->prepare("
        SELECT meal_name, calories, protein, carbs, fats, meal_type, logged_date
        FROM nutrition_logs
        WHERE user_id = ? AND logged_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY logged_date DESC, created_at DESC
    ");
    $mealsStmt->execute([$userId]);
    $recent_meals = $mealsStmt->fetchAll(PDO::FETCH_ASSOC);
}

$age = $profile['age'] ?? 'unknown';
$weight = isset($profile['weight']) && $profile['weight'] ? $profile['weight'] : ($profile['current_weight'] ?? 'unknown');
$height = $profile['height'] ?? 'unknown';
$goal = $profile['fitness_goal'] ?? 'General Health';
$diet = $profile['diet_preference'] ?? 'None';
$allergies = $profile['allergies'] ?? 'None';
$conditions = $profile['medical_conditions'] ?? 'None';

// Deserialize JSON arrays if applicable
if ($allergies && @json_decode($allergies)) {
    $decodedAllergies = json_decode($allergies, true);
    $allergies = is_array($decodedAllergies) ? implode(', ', $decodedAllergies) : $allergies;
}
if ($conditions && @json_decode($conditions)) {
    $decodedConditions = json_decode($conditions, true);
    $conditions = is_array($decodedConditions) ? implode(', ', $decodedConditions) : $conditions;
}

$history_str = "No recent meal history logged.";
if (!empty($recent_meals)) {
    $history_arr = [];
    foreach ($recent_meals as $m) {
        $history_arr[] = "- {$m['logged_date']}: {$m['meal_name']} ({$m['calories']} kcal, P: {$m['protein']}g, C: {$m['carbs']}g, F: {$m['fats']}g)";
    }
    $history_str = implode("\n", $history_arr);
}

$prompt = "You are a professional nutrition AI coach for the Fitrova app. 
Analyze this food image and estimate the nutritional information.
Also, evaluate whether this food choice is beneficial or harmful for the user based on their profile, goals, and recent eating patterns.

User Profile:
- Age: {$age}
- Height: {$height}cm
- Weight: {$weight}kg
- Primary Goal: {$goal}
- Diet Preference: {$diet}
- Allergies: {$allergies}
- Medical Conditions: {$conditions}

User's Recent Meal History (Last 7 Days):
{$history_str}

Analyze:
1. Is this meal healthy or suitable for their specific health profile/fitness goals?
2. Look at their last 7 days of meal history. If they have repeatedly consumed this specific food or type of food multiple times (e.g. 3-5 times) within this week, and it does not support a balanced diet (like eating pizza, burgers, or same high-calorie/low-protein meals repetitively), detect this eating pattern and proactively provide a friendly, coaching pattern alert.
3. Identify potential dietary imbalances (e.g. high calorie, high fats, lack of protein, or too repetitive).
4. Suggest 2-3 healthier, more balanced alternatives that align with their goals.

Return the result ONLY as a JSON object with the following structure:
{
  \"meal_name\": \"Descriptive name of the meal\",
  \"calories\": total_calories_as_number,
  \"protein\": grams_protein_as_number,
  \"carbs\": grams_carbs_as_number,
  \"fats\": grams_fats_as_number,
  \"items\": [
    {\"name\": \"item name\", \"amount\": \"estimated amount (e.g. 100g, 1 slice)\", \"calories\": item_calories}
  ],
  \"health_analysis\": {
    \"health_rating\": \"Excellent\" | \"Good\" | \"Caution\" | \"Avoid\",
    \"evaluation\": \"A brief 1-2 sentence explanation of how this meal fits their health profile, allergies, and goals (max 200 chars).\",
    \"pattern_alert\": \"A friendly warning message if they are eating this same food repeatedly this week (e.g., 'You've consumed this food 4 times this week. To maintain a healthier and more balanced diet, consider adding foods rich in protein, fruits, vegetables, or other essential nutrients.'). Set to null if there is no repetitive or unhealthy pattern.\",
    \"dietary_imbalances\": [\"Imbalance description 1\", \"Imbalance description 2\"],
    \"healthier_alternatives\": [
      {\"name\": \"Alternative Food Name 1\", \"reason\": \"Why it's a better choice (max 100 chars)\"},
      {\"name\": \"Alternative Food Name 2\", \"reason\": \"Why it's a better choice (max 100 chars)\"}
    ],
    \"coach_feedback\": \"Coaching feedback encouraging variety and better portion/nutrient habits (max 250 chars).\"
  }
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
        file_put_contents(__DIR__ . '/../../../storage/logs/gemini_error.log', date('Y-m-d H:i:s') . " - " . $last_error . "\n", FILE_APPEND);
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
    // If this was a free trial, consume it now
    if (!empty($scanAccessResult['is_trial'])) {
        \App\Middleware\AISubscriptionGate::consumeTrial($pdo, $userId, 'scan_trial_used');
    }
    echo json_encode([
        'status' => 'success',
        'data' => $meal_data
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'AI returned invalid format', 'raw' => $ai_text]);
}
