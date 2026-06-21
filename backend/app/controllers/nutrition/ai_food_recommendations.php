<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';
require_once __DIR__ . '/../../../config/env_loader.php';
loadEnv(__DIR__ . '/../../../.env');
require_once __DIR__ . '/../../../config/gemma_helper.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
        exit();
    }
    
    $userId = intval($input['user_id']);

    // Enforce paywall gatekeeper check
    // Free users get 1 trial; after that they need Advanced Premium
    require_once __DIR__ . '/../../middleware/AISubscriptionGate.php';
    $dietAccessResult = \App\Middleware\AISubscriptionGate::verifyAccess($pdo, $userId, 'advanced_premium', 'AI Nutrition Coach', 'diet_trial_used');

    // Check if recommendations already exist for today
    $today = date('Y-m-d');
    $cacheStmt = $pdo->prepare("SELECT recommendations_json FROM ai_food_recommendations WHERE user_id = ? AND recommendation_date = ?");
    $cacheStmt->execute([$userId, $today]);
    $cached = $cacheStmt->fetch(PDO::FETCH_ASSOC);

    if ($cached) {
        $cachedData = json_decode($cached['recommendations_json'], true);
        if ($cachedData) {
            echo json_encode(['status' => 'success', 'data' => $cachedData]);
            exit();
        }
    }

    // Fetch dynamic configuration for AI
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('ai_gemini_api_key', 'ai_model_primary', 'hf_token')");
    $settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $GEMINI_API_KEY = $settings['ai_gemini_api_key'] ?? '';
    $primaryModel = $settings['ai_model_primary'] ?? '';

    // Build models fallback list
    $models = [];
    if (!empty($primaryModel)) {
        $models[] = $primaryModel;
    }
    $fallbackModels = ['gemini-3.1-flash-lite', 'gemini-2.5-flash-lite', 'gemini-flash-lite-latest'];
    foreach ($fallbackModels as $fm) {
        if ($fm !== $primaryModel) {
            $models[] = $fm;
        }
    }

    // Get user profile
    $profileStmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
    $profileStmt->execute([$userId]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        echo json_encode(['status' => 'error', 'message' => 'Profile not found']);
        exit();
    }

    $age = $profile['age'] ?? 'unknown';
    $weight = $profile['weight'] ?? 'unknown';
    $height = $profile['height'] ?? 'unknown';
    $goal = $profile['fitness_goal'] ?? 'General Health';
    $diet = $profile['diet_preference'] ?? 'None';
    $allergies = $profile['allergies'] ?? 'None';
    $conditions = $profile['medical_conditions'] ?? 'None';

    $prompt = "You are a professional nutrition AI coach for the Fitrova app. 
Generate 3 highly personalized food/meal recommendations for the following user.
It is CRITICAL that you consider their medical conditions and allergies.

User Profile:
- Age: {$age}
- Height: {$height}cm
- Current Weight: {$weight}kg
- Primary Goal: {$goal}
- Diet Preference: {$diet}
- Allergies: {$allergies}
- Medical Conditions: {$conditions}

Your response must be a JSON array of 3 objects with the following structure:
[
  {
    \"name\": \"Meal Name\",
    \"reason\": \"Why this is perfect for their specific conditions/goals (max 100 chars)\",
    \"description\": \"A mouthwatering 2-3 sentence description of the meal, explaining how it is prepared and its delicious flavor profile.\",
    \"prep_time\": \"20 mins\",
    \"ingredients\": [\"Ingredient 1 with amount\", \"Ingredient 2 with amount\", \"Ingredient 3 with amount\", \"Ingredient 4 with amount\"],
    \"benefits\": [\"Key benefit 1\", \"Key benefit 2\"],
    \"calories\": 450,
    \"protein\": 30,
    \"carbs\": 40,
    \"fats\": 15
  }
]
Return ONLY the JSON array, nothing else.";

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
            $error_details[] = "Model {$modelName} succeeded with 200 but failed to parse JSON text. Response: " . substr($response, 0, 300);
        } else {
            $error_details[] = "Model {$modelName} failed with code {$httpCode}. Response: " . substr($response, 0, 300);
        }
    }

    if (!$ai_data) {
        // Fallback to Gemma 3
        try {
            $hfToken = getenv('HF_TOKEN') ?: ($settings['hf_token'] ?? '');
            if (!empty($hfToken)) {
                $gemmaText = callGemma3($prompt, $hfToken, 1000);
                $parsed = json_decode($gemmaText, true);
                if ($parsed && is_array($parsed) && isset($parsed[0]['name'])) {
                    $ai_data = $parsed;
                } else {
                    $error_details[] = "Gemma 3 fallback returned invalid JSON or wrong format: " . substr($gemmaText, 0, 200);
                }
            } else {
                $error_details[] = "Gemma 3 fallback skipped: HF_TOKEN is empty";
            }
        } catch (Exception $gemmaEx) {
            $error_details[] = "Gemma 3 fallback failed: " . $gemmaEx->getMessage();
        }
    }

    if ($ai_data) {
        // Save newly generated recommendations to database cache for today
        try {
            $insertStmt = $pdo->prepare("
                INSERT INTO ai_food_recommendations (user_id, recommendation_date, recommendations_json)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE recommendations_json = VALUES(recommendations_json)
            ");
            $insertStmt->execute([$userId, $today, json_encode($ai_data)]);
        } catch (Exception $dbEx) {
            error_log("Failed to cache AI food recommendations: " . $dbEx->getMessage());
        }

        // If this was a free trial, consume it now
        if (!empty($dietAccessResult['is_trial'])) {
            \App\Middleware\AISubscriptionGate::consumeTrial($pdo, $userId, 'diet_trial_used');
        }

        echo json_encode(['status' => 'success', 'data' => $ai_data]);
    } else {
        http_response_code(503);
        echo json_encode([
            'status' => 'error', 
            'message' => 'AI is temporarily over capacity. Please try again later.',
            'details' => $error_details
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
