<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Suppress warnings from leaking into JSON output
error_reporting(0);
ini_set('display_errors', 0);
ini_set('memory_limit', '256M');
set_time_limit(300);
ignore_user_abort(true); // Keep running even if Apache closes the client connection

// ── Fatal-error safety net ─────────────────────────────────────────────────
// try/catch cannot catch OOM / E_ERROR; this shutdown function can.
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo json_encode([
            'status'  => 'ERROR',
            'message' => 'Server fatal error. Try a shorter video clip.',
        ]);
    }
});

// Handle CORS pre-flight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ── DB & settings ─────────────────────────────────────────────────────────
require_once __DIR__ . '/../../../config/db_config.php';

$settingsStmt = $pdo->query(
    "SELECT setting_key, setting_value FROM system_settings
     WHERE setting_key IN ('ai_gemini_api_key', 'ai_model_primary')"
);
$settings       = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$GEMINI_API_KEY = $settings['ai_gemini_api_key'] ?? '';
// 'ai_model_primary' is the key that actually exists in the DB
// Confirmed-available fallback chain for this API key
$GEMINI_MODELS  = array_unique([
    $settings['ai_model_primary'] ?? 'gemini-3.1-flash-lite',
    'gemini-1.5-flash',
    'gemini-1.5-pro',
    'gemini-2.0-flash',
    'gemini-2.5-flash',
    'gemini-3.5-flash',
    'gemini-flash-latest',
]);

// ── Main logic ────────────────────────────────────────────────────────────
try {
    $userId       = $_POST['user_id'] ?? 1;
    $exerciseName = $_POST['exercise'] ?? 'Detect automatically';
    $imageBase64  = null;
    $mimeType     = 'image/jpeg';

    // --- Input: video uploaded via multipart/form-data ---
    if (isset($_FILES['video'])) {
        $uploadErr = $_FILES['video']['error'];
        if ($uploadErr !== UPLOAD_ERR_OK) {
            $errMap = [
                UPLOAD_ERR_INI_SIZE   => 'Video exceeds server upload limit. Record a shorter clip.',
                UPLOAD_ERR_FORM_SIZE  => 'Video exceeds form size limit.',
                UPLOAD_ERR_PARTIAL    => 'Upload was interrupted — check your connection.',
                UPLOAD_ERR_NO_FILE    => 'No video was received by the server.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server temp directory is missing.',
                UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file.',
            ];
            throw new Exception($errMap[$uploadErr] ?? "Upload failed (code $uploadErr).");
        }

        $videoPath = $_FILES['video']['tmp_name'];
        if (!file_exists($videoPath) || filesize($videoPath) === 0) {
            throw new Exception('Uploaded video is empty or not found on the server.');
        }

        // Gemini inline_data limit is ~20 MB raw
        $fileSizeMB = filesize($videoPath) / (1024 * 1024);
        if ($fileSizeMB > 18) {
            throw new Exception("Video too large ({$fileSizeMB} MB). Record under 5 seconds at 480p.");
        }

        $imageBase64 = base64_encode(file_get_contents($videoPath));
        $mimeType    = 'video/mp4';

    } else {
        // --- Input: JSON body or POST field with base64 image ---
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            $imageBase64  = $input['image']   ?? null;
            $userId       = $input['user_id'] ?? $userId;
            $exerciseName = $input['exercise'] ?? $exerciseName;
        }
        if (!$imageBase64 && isset($_POST['image'])) {
            $imageBase64 = $_POST['image'];
        }
    }

    // ── Paywall / subscription gate ──────────────────────────────────────
    require_once __DIR__ . '/../../middleware/AISubscriptionGate.php';
    $accessResult = \App\Middleware\AISubscriptionGate::verifyAccess(
        $pdo, $userId, 'advanced_premium', 'AI Biomechanics Form Coach', 'form_trial_used'
    );

    if (!$imageBase64) {
        throw new Exception('No image or video data received. Please try again.');
    }

    // Strip data-URI prefix if present ("data:image/jpeg;base64,...")
    if (strpos($imageBase64, ',') !== false) {
        $imageBase64 = explode(',', $imageBase64)[1];
    }

    // ── Build Gemini prompt ──────────────────────────────────────────────
    $mediaLabel = ($mimeType === 'video/mp4') ? 'video' : 'image';
    $prompt = "You are an AI Personal Trainer and expert biomechanics coach. Analyze this user's workout {$mediaLabel}.
The expected exercise they should be performing is: \"{$exerciseName}\".

Your tasks:
1. Detect what exercise the user is actually performing in this {$mediaLabel} (e.g., 'Jumping Jacks', 'Barbell Bench Press', 'Squats', etc.).
2. Cross-reference the exercise you detect with the expected exercise (\"{$exerciseName}\"):
   - If the expected exercise is NOT 'Detect automatically' (or empty) and the user is performing a completely different exercise (e.g., doing Jumping Jacks when they should be doing a Barbell Bench Press), you MUST flag this as a critical mismatch.
   - For an exercise mismatch: Set status to \"IMPROVEMENT_NEEDED\", set the score to 0, set the summary to: \"Wrong exercise detected! You are performing [Detected Exercise], but the selected workout is [Expected Exercise]. Please perform the correct exercise for form checking.\", and list tips explaining this.
3. If they are performing the correct exercise (or if expected is 'Detect automatically'):
   - Critically evaluate their body positioning, range of motion, and joint alignment.
   - If there are flaws (e.g., rounding the back, incorrect elbow/shoulder angles, shallow squat depth, knees caving, bad range of motion, speed too fast/uncontrolled), set status to \"IMPROVEMENT_NEEDED\", deduct score (0-100) based on severity, and provide specific, actionable tips to correct these. Do not be overly lenient or generic.
   - If their form is excellent, set status to \"GOOD\", score to 85-100, and provide positive reinforcement.

Return ONLY valid JSON — no markdown code fences, no leading/trailing comments:
{
  \"status\": \"GOOD\" or \"IMPROVEMENT_NEEDED\",
  \"detected_exercise\": \"Actual name of the exercise you see them doing\",
  \"score\": 0-100,
  \"tips\": [\"Specific coaching tip 1\", \"Specific coaching tip 2\"],
  \"summary\": \"Encouraging coaching summary explaining if they did the correct exercise and how their form looked\"
}";

    $payload = [
        'contents' => [[
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageBase64]],
            ],
        ]],
        'generationConfig' => ['response_mime_type' => 'application/json'],
    ];
    $payloadJson = json_encode($payload);

    // ── Call Gemini API — try models in order until one succeeds ─────────
    $geminiResponse = null;
    $httpCode       = 0;
    $lastError      = '';

    foreach ($GEMINI_MODELS as $model) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$GEMINI_API_KEY}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST,           true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,     $payloadJson);
        curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT,        90);  // 90-second cap per model
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

        $rawResponse = curl_exec($ch);
        $curlErr     = curl_error($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rawResponse === false) {
            $lastError = "cURL ({$model}): {$curlErr}";
            continue;
        }
        if ($httpCode === 200) {
            $geminiResponse = $rawResponse;
            break;
        }
        // Try other models for any error (400, 404, 429, 503, etc.)
        $lastError = "HTTP {$httpCode} ({$model}): " . substr($rawResponse, 0, 200);
    }

    if ($geminiResponse === null) {
        throw new Exception("Gemini API unavailable. Last error: {$lastError}");
    }

    // ── Parse Gemini response ────────────────────────────────────────────
    $result = json_decode($geminiResponse, true);
    $analysisText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!$analysisText) {
        throw new Exception('Gemini returned an empty response: ' . $geminiResponse);
    }

    // Strip markdown code-fence wrappers if present
    $analysisText = preg_replace('/^```json\s*/i', '', trim($analysisText));
    $analysisText = preg_replace('/```\s*$/i',     '', trim($analysisText));
    $analysisText = trim($analysisText);

    $analysisData = json_decode($analysisText, true);
    if (!$analysisData) {
        throw new Exception('AI returned non-JSON: ' . $analysisText);
    }

    // ── Save to DB (non-fatal) ───────────────────────────────────────────
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO form_check_logs (user_id, exercise_name, score, status, summary, tips)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId,
            $analysisData['detected_exercise'] ?? $exerciseName,
            $analysisData['score']             ?? 0,
            $analysisData['status']            ?? 'UNKNOWN',
            $analysisData['summary']           ?? '',
            json_encode($analysisData['tips']  ?? []),
        ]);
    } catch (PDOException $dbErr) {
        error_log("form_analyzer DB save failed: " . $dbErr->getMessage());
    }

    // ── Consume trial if applicable ──────────────────────────────────────
    if (!empty($accessResult['is_trial'])) {
        \App\Middleware\AISubscriptionGate::consumeTrial($pdo, $userId, 'form_trial_used');
    }

    echo $analysisText;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'ERROR',
        'message' => $e->getMessage(),
    ]);
}
?>
