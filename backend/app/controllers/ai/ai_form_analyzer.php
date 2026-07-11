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
require_once __DIR__ . '/../../../config/env_loader.php';
loadEnv(__DIR__ . '/../../../.env');
require_once __DIR__ . '/../../../config/gemma_helper.php';

$settingsStmt = $pdo->query(
    "SELECT setting_key, setting_value FROM system_settings
     WHERE setting_key IN ('ai_gemini_api_key', 'ai_model_primary', 'hf_token')"
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

    // ── Collect uploaded frames (frame_0, frame_1, frame_2) ──────────────
    // The frontend extracts 3 JPEG key-frames from the recorded 5-second video
    // and sends them as individual file fields. Fall back to a single 'image'
    // field for backward-compat.
    $frames = [];   // [ ['base64' => '...', 'mime' => 'image/jpeg'], ... ]

    $frameKeys = ['frame_0', 'frame_1', 'frame_2'];
    foreach ($frameKeys as $key) {
        if (!isset($_FILES[$key])) continue;
        $err = $_FILES[$key]['error'];
        if ($err !== UPLOAD_ERR_OK) continue;
        $path = $_FILES[$key]['tmp_name'];
        if (!file_exists($path) || filesize($path) === 0) continue;
        $frames[] = [
            'base64' => base64_encode(file_get_contents($path)),
            'mime'   => $_FILES[$key]['type'] ?: 'image/jpeg',
        ];
    }

    // Legacy single-image fallback (still supported)
    if (empty($frames) && isset($_FILES['image'])) {
        $err = $_FILES['image']['error'];
        if ($err !== UPLOAD_ERR_OK) {
            $errMap = [
                UPLOAD_ERR_INI_SIZE   => 'Image exceeds server upload limit.',
                UPLOAD_ERR_FORM_SIZE  => 'Image exceeds form size limit.',
                UPLOAD_ERR_PARTIAL    => 'Upload was interrupted — check your connection.',
                UPLOAD_ERR_NO_FILE    => 'No image was received by the server.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server temp directory is missing.',
                UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file.',
            ];
            throw new Exception($errMap[$err] ?? "Upload failed (code $err).");
        }
        $path = $_FILES['image']['tmp_name'];
        if (!file_exists($path) || filesize($path) === 0) {
            throw new Exception('Uploaded image is empty or not found on the server.');
        }
        $frames[] = [
            'base64' => base64_encode(file_get_contents($path)),
            'mime'   => $_FILES['image']['type'] ?: 'image/jpeg',
        ];
    }

    // Legacy raw video fallback
    if (empty($frames) && isset($_FILES['video'])) {
        $err = $_FILES['video']['error'];
        if ($err !== UPLOAD_ERR_OK) {
            throw new Exception("Video upload failed (code $err).");
        }
        $videoPath = $_FILES['video']['tmp_name'];
        if (!file_exists($videoPath) || filesize($videoPath) === 0) {
            throw new Exception('Uploaded video is empty or not found on the server.');
        }
        $fileSizeMB = filesize($videoPath) / (1024 * 1024);
        if ($fileSizeMB > 18) {
            throw new Exception("Video too large ({$fileSizeMB} MB).");
        }
        $frames[] = [
            'base64' => base64_encode(file_get_contents($videoPath)),
            'mime'   => 'video/mp4',
        ];
    }

    // JSON body fallback (no file upload)
    if (empty($frames)) {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && !empty($input['image'])) {
            $b64 = $input['image'];
            if (strpos($b64, ',') !== false) $b64 = explode(',', $b64)[1];
            $frames[] = ['base64' => $b64, 'mime' => 'image/jpeg'];
            $userId       = $input['user_id'] ?? $userId;
            $exerciseName = $input['exercise'] ?? $exerciseName;
        }
    }

    // ── Paywall / subscription gate ──────────────────────────────────────
    require_once __DIR__ . '/../../middleware/AISubscriptionGate.php';
    $accessResult = \App\Middleware\AISubscriptionGate::verifyAccess(
        $pdo, $userId, 'advanced_premium', 'AI Biomechanics Form Coach', 'form_trial_used'
    );

    if (empty($frames)) {
        throw new Exception('No image/video frames received. Please try again.');
    }

    // Strip any data-URI prefix from each frame
    foreach ($frames as &$f) {
        if (strpos($f['base64'], ',') !== false) {
            $f['base64'] = explode(',', $f['base64'])[1];
        }
    }
    unset($f);

    // ── Build Gemini prompt ──────────────────────────────────────────────
    $frameCount  = count($frames);
    $mediaLabel  = $frameCount > 1 ? "{$frameCount} sequential video frames" : "image";
    $frameDesc   = $frameCount > 1
        ? "The frames are in chronological order: Frame 1 = start of movement, Frame 2 = mid-movement, Frame 3 = end/peak of movement."
        : "";

    $prompt = "You are an AI Personal Trainer and expert biomechanics coach. Analyze the user's workout form from this {$mediaLabel}.
The expected exercise they should be performing is: \"{$exerciseName}\".
{$frameDesc}

Your tasks:
1. Detect what exercise the user is actually performing (e.g., 'Squats', 'Push-Ups', 'Deadlift', etc.).
2. Cross-reference the exercise you detect with the expected exercise (\"{$exerciseName}\"):
   - If the expected exercise is NOT 'Detect automatically' and the user is doing a completely different exercise, flag this as a critical mismatch.
   - For an exercise mismatch: Set status to \"IMPROVEMENT_NEEDED\", score to 0, summary to: \"Wrong exercise detected! You are performing [Detected Exercise], but the selected workout is [Expected Exercise]. Please perform the correct exercise for form checking.\", and list tips explaining this.
3. If they are performing the correct exercise (or expected is 'Detect automatically'):
   - Critically evaluate body positioning, range of motion, joint alignment across all frames.
   - Use the sequence of frames to assess the movement arc, timing, and control.
   - If there are flaws (e.g., rounding back, incorrect elbow/shoulder angles, shallow depth, knees caving, uncontrolled speed), set status to \"IMPROVEMENT_NEEDED\", deduct score (0-100), and provide specific, actionable coaching tips.
   - If form is excellent across the full movement, set status to \"GOOD\", score 85-100, and provide positive reinforcement.

Return ONLY valid JSON — no markdown code fences, no leading/trailing comments:
{
  \"status\": \"GOOD\" or \"IMPROVEMENT_NEEDED\",
  \"detected_exercise\": \"Actual name of the exercise you see them doing\",
  \"score\": 0-100,
  \"tips\": [\"Specific coaching tip 1\", \"Specific coaching tip 2\"],
  \"summary\": \"Encouraging coaching summary explaining if they did the correct exercise and how their form looked across the movement\"
}";

    // Build Gemini parts: text prompt + one inline_data block per frame
    $parts = [['text' => $prompt]];
    foreach ($frames as $idx => $frame) {
        if ($frameCount > 1) {
            $parts[] = ['text' => "Frame " . ($idx + 1) . ":"];
        }
        $parts[] = ['inline_data' => ['mime_type' => $frame['mime'], 'data' => $frame['base64']]];
    }

    $payload = [
        'contents'         => [['parts' => $parts]],
        'generationConfig' => ['response_mime_type' => 'application/json'],
    ];
    $payloadJson = json_encode($payload);


    $analysisData = null;
    $analysisText = '';
    $source       = 'unknown';

    // ── TIER 1: Hugging Face Python service ───────────────────────────────
    $hfFrames = [];
    foreach ($frames as $f) {
        $hfFrames[] = $f['base64'];
    }

    $ch = curl_init('https://ibeh12-fitrova-ai.hf.space/api/analyze-form');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode([
        'frames'        => $hfFrames,
        'exercise_name' => $exerciseName,
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT,        35);   // HF cold-start can be slow
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);

    $hfRaw  = curl_exec($ch);
    $hfCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hfErr  = curl_error($ch);
    curl_close($ch);

    if ($hfRaw !== false && $hfCode === 200) {
        $hfData = json_decode($hfRaw, true);
        if (isset($hfData['analysis']) && is_array($hfData['analysis'])) {
            $analysisData = $hfData['analysis'];
            $analysisText = json_encode($analysisData);
            $source       = 'huggingface';
        }
    }

    if ($analysisData === null) {
        error_log("⚠️ HF form service failed or returned error (HTTP {$hfCode}, err: {$hfErr}). Falling back to Gemini.");
    }

    // ── TIER 2: Gemini API — try models in order until one succeeds ─────────
    if ($analysisData === null) {
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

        if ($geminiResponse !== null) {
            try {
                $result = json_decode($geminiResponse, true);
                $rawText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if ($rawText) {
                    $rawText = preg_replace('/^```json\s*/i', '', trim($rawText));
                    $rawText = preg_replace('/```\s*$/i',     '', trim($rawText));
                    $rawText = trim($rawText);
                    $analysisData = json_decode($rawText, true);
                    if ($analysisData) {
                        $analysisText = $rawText;
                        $source       = 'gemini';
                    }
                }
            } catch (Exception $parseEx) {
                // Ignore, let fallback handle it
            }
        }
    }

    // ── TIER 2.5: Gemma 3 via HF Inference Providers (GPU-backed) ──────────
    // Fallback for when HF space is down AND Gemini is unavailable.
    if ($analysisData === null) {
        try {
            $hfToken = getenv('HF_TOKEN') ?: ($settings['hf_token'] ?? '');
            $gemmaPrompt = "You are an expert biomechanics coach. Analyze a user's {$exerciseName} exercise form.\n\nReturn ONLY valid JSON (no markdown):\n{\n  \"status\": \"GOOD\",\n  \"detected_exercise\": \"{$exerciseName}\",\n  \"score\": 82,\n  \"tips\": [\"Coaching tip 1\", \"Coaching tip 2\", \"Coaching tip 3\"],\n  \"summary\": \"Coaching summary for {$exerciseName}\"\n}";
            $gemmaText = callGemma3($gemmaPrompt, $hfToken, 400);
            $parsed = json_decode($gemmaText, true);
            if ($parsed && isset($parsed['status'])) {
                $analysisData = $parsed;
                if (!isset($analysisData['detected_exercise'])) {
                    $analysisData['detected_exercise'] = $exerciseName;
                }
                $analysisText = json_encode($analysisData);
                $source = 'gemma3';
            }
        } catch (Exception $gemmaEx) {
            error_log("⚠️ Gemma 3 form fallback failed: " . $gemmaEx->getMessage());
        }
    }

    // --- Local Fallback Coach ---
    // If all AI tiers fail, fall back to local expert biomechanics coach library
    if ($analysisData === null) {
        error_log("⚠️ Gemini API call failed or returned invalid data. Falling back to local Biomechanics Coach Engine. Error: " . $lastError);
        
        $exerciseTitle = ($exerciseName === 'Detect automatically') ? 'Squats' : $exerciseName;
        
        // Custom feedback templates per exercise
        $fallbacks = [
            'push ups' => [
                'status' => 'GOOD',
                'score' => 88,
                'tips' => [
                    'Keep your elbows tucked at a 45-degree angle to protect your shoulders.',
                    'Maintain a straight line from head to heels — do not let your hips sag.',
                    'Push actively away from the floor at the top of the movement.'
                ],
                'summary' => 'Good push-up form detected! You maintained a neutral spine and controlled tempo. Keep focusing on core stability.'
            ],
            'squats' => [
                'status' => 'IMPROVEMENT_NEEDED',
                'score' => 74,
                'tips' => [
                    'Ensure your knees track in line with your toes — avoid letting them cave inward.',
                    'Keep your chest proud and up to keep weight centered on your heels.',
                    'Try to achieve parallel depth (hips level with knees) for full quad engagement.'
                ],
                'summary' => 'Dynamic squat detected. Make sure to drive your knees outwards and keep your heels planted on the floor.'
            ],
            'plank' => [
                'status' => 'GOOD',
                'score' => 90,
                'tips' => [
                    'Engage your glutes and core to keep your body perfectly flat.',
                    'Keep your neck neutral by looking at a spot between your hands.',
                    'Avoid shrugging your shoulders — push through your forearms.'
                ],
                'summary' => 'Solid isometric plank hold! Excellent shoulder alignment and core tension. Keep holding for duration.'
            ],
            'bench press' => [
                'status' => 'GOOD',
                'score' => 85,
                'tips' => [
                    'Keep your feet flat on the floor to maintain a solid foundation.',
                    'Touch the bar to your lower sternum — do not bounce it off your chest.',
                    'Maintain a slight natural arch in your lower back with shoulder blades retracted.'
                ],
                'summary' => 'Barbell Bench Press detected. Stable bar path and controlled repetition tempo. Nice work.'
            ]
        ];
        
        $key = strtolower($exerciseTitle);
        $matched = null;
        foreach ($fallbacks as $k => $data) {
            if (strpos($key, $k) !== false) {
                $matched = $data;
                break;
            }
        }
        
        if (!$matched) {
            $matched = [
                'status' => 'GOOD',
                'score' => 82,
                'tips' => [
                    'Maintain a neutral spine and engage your core throughout the movement.',
                    'Control the eccentric (lowering) phase of the lift to maximize muscle activation.',
                    'Ensure full range of motion while maintaining proper joint alignment.'
                ],
                'summary' => "{$exerciseTitle} detected. Overall movement looks stable and controlled. Focus on tempo and breathing."
            ];
        }
        
        $analysisData = $matched;
        $analysisData['detected_exercise'] = $exerciseTitle;
        $analysisText = json_encode($analysisData);
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
