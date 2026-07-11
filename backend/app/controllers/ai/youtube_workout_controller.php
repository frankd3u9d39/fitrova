<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(120);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';
require_once __DIR__ . '/../../../config/env_loader.php';
loadEnv(__DIR__ . '/../../../.env');

// ── Fetch Gemini API key + preferred model from DB ────────────────────────
$settingsStmt = $pdo->query(
    "SELECT setting_key, setting_value FROM system_settings
     WHERE setting_key IN ('ai_gemini_api_key', 'ai_model_primary', 'hf_token')"
);
$settings       = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$GEMINI_API_KEY = $settings['ai_gemini_api_key'] ?? '';
// Only try top-2 models to keep total latency manageable
$primary        = $settings['ai_model_primary'] ?? 'gemini-2.0-flash';
$GEMINI_MODELS  = array_unique([$primary, 'gemini-2.0-flash', 'gemini-1.5-flash']);
$GEMINI_MODELS  = array_slice($GEMINI_MODELS, 0, 2); // max 2 attempts

$YOUTUBE_API_KEY = getenv('YOUTUBE_API_KEY');
if (!$YOUTUBE_API_KEY || $YOUTUBE_API_KEY === 'YOUR_YOUTUBE_API_KEY_HERE') {
    $YOUTUBE_API_KEY = 'AIzaSyD-tOfE-vkGE4mBNzJLadLb_U6CCfztqUE';
}

// Hugging Face AI service — primary analysis endpoint
$HF_SERVICE_URL = 'https://ibeh12-fitrova-ai.hf.space/api/analyze-youtube';

$action = $_GET['action'] ?? 'search';

// ─────────────────────────────────────────────────────────────────────────
// ACTION: search
// ─────────────────────────────────────────────────────────────────────────
if ($action === 'search') {
    $query = $_GET['query'] ?? 'workout';
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&maxResults=10&q="
        . urlencode($query . " workout tutorial")
        . "&type=video&key=" . $YOUTUBE_API_KEY;

    $response = @file_get_contents($url);
    if ($response === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch from YouTube API']);
        exit();
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        echo json_encode([
            'error'   => 'Invalid response from YouTube API',
            'message' => 'YouTube API key may be invalid or quota exceeded',
        ]);
        exit();
    }

    if (isset($decoded['error'])) {
        http_response_code(400);
        echo json_encode([
            'error'   => 'YouTube API Error',
            'message' => $decoded['error']['message'] ?? 'Unknown error',
            'code'    => $decoded['error']['code']    ?? 'unknown',
        ]);
        exit();
    }

    echo $response;

// ─────────────────────────────────────────────────────────────────────────
// ACTION: analyze  (3-tier fallback chain)
// ─────────────────────────────────────────────────────────────────────────
} elseif ($action === 'analyze') {

    $input         = json_decode(file_get_contents('php://input'), true);
    $youtube_url   = $input['youtube_url']   ?? '';
    $exercise_name = $input['exercise_name'] ?? 'Workout';
    $video_title   = $input['video_title']   ?? $exercise_name;

    if (!$youtube_url) {
        echo json_encode(['error' => 'YouTube URL is required']);
        exit();
    }

    $analysisData = null;
    $source       = 'unknown';

    // ── TIER 1: Gemini API — single model, hard 18 s cap ────────────────────
    // Render's proxy kills connections at ~30 s, so we must return within that window.
    // If Gemini fails/times-out we fall through instantly to the local library.
    if ($analysisData === null && $GEMINI_API_KEY) {
        $prompt = "You are an expert AI personal trainer and biomechanics coach specializing in workout form analysis.

A user is watching this YouTube workout tutorial:
- Exercise: \"{$exercise_name}\"
- Video title: \"{$video_title}\"
- URL: {$youtube_url}

Based on your expert knowledge of proper form for \"{$exercise_name}\", provide a detailed professional coaching analysis.

Return ONLY valid JSON — no markdown, no code fences:
{
  \"analysis\": {
    \"exercise\": \"Name of the exercise\",
    \"accuracy_score\": 85,
    \"status\": \"GOOD\",
    \"summary\": \"Professional coaching summary about this exercise and what to focus on\",
    \"pro_tips\": [\"Specific tip 1\", \"Specific tip 2\", \"Specific tip 3\"],
    \"common_mistakes\": [\"Mistake 1\", \"Mistake 2\"],
    \"key_cues\": [\"Mental cue 1\", \"Mental cue 2\"]
  }
}

status must be one of: GOOD, CAUTION, IMPROVEMENT_NEEDED";

        $payload     = [
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 800],
        ];
        $payloadJson = json_encode($payload);

        // Use only the primary model — no multi-model loop to stay under Render's timeout
        $geminiModel = $GEMINI_MODELS[0] ?? 'gemini-2.0-flash';
        $geminiUrl   = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent?key={$GEMINI_API_KEY}";

        $ch = curl_init($geminiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST,           true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,     $payloadJson);
        curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT,        18);   // hard cap — local fallback fires if exceeded
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $rawResponse = curl_exec($ch);
        $geminiCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rawResponse !== false && $geminiCode === 200) {
            $result  = json_decode($rawResponse, true);
            $rawText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

            if ($rawText) {
                $rawText = preg_replace('/^```json\s*/i', '', trim($rawText));
                $rawText = preg_replace('/```\s*$/i',     '', trim($rawText));
                $rawText = trim($rawText);

                $parsed = json_decode($rawText, true);
                if ($parsed && isset($parsed['analysis'])) {
                    $analysisData = $parsed;
                    $source       = 'gemini';
                } elseif ($parsed && isset($parsed['exercise'])) {
                    $analysisData = ['analysis' => $parsed];
                    $source       = 'gemini';
                }
            }
        }

        if ($analysisData === null) {
            error_log("⚠️ Gemini failed (HTTP {$geminiCode}). Trying Gemma 2 on HF Space.");
        }
    }

    // ── TIER 2: Gemma 3 via HF Inference Providers API (GPU-backed, fast) ─────
    if ($analysisData === null) {
        $hfToken = getenv('HF_TOKEN') ?: ($settings['hf_token'] ?? '');
        if ($hfToken) {
            $prompt = "You are an expert fitness coach. Give detailed coaching advice for: {$exercise_name}.\n\nReturn ONLY valid JSON with no markdown:\n{\n  \"analysis\": {\n    \"exercise\": \"{$exercise_name}\",\n    \"accuracy_score\": 85,\n    \"status\": \"GOOD\",\n    \"summary\": \"...\",\n    \"pro_tips\": [\"tip1\", \"tip2\", \"tip3\"],\n    \"common_mistakes\": [\"mistake1\", \"mistake2\"],\n    \"key_cues\": [\"cue1\", \"cue2\"]\n  }\n}";

            $ch = curl_init('https://router.huggingface.co/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST,           true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode([
                'model' => 'google/gemma-3-4b-it',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 400,
                'temperature' => 0.7
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER,     [
                "Authorization: Bearer {$hfToken}",
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT,        8);    // 18s Gemini + 8s Gemma = 26s total, under Render's 30s limit
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

            $gemmaRaw  = curl_exec($ch);
            $gemmaCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $gemmaErr  = curl_error($ch);
            curl_close($ch);

            if ($gemmaRaw !== false && $gemmaCode === 200) {
                $resp = json_decode($gemmaRaw, true);
                $text = $resp['choices'][0]['message']['content'] ?? '';

                if ($text) {
                    $text = preg_replace('/^```json\s*/i', '', trim($text));
                    $text = preg_replace('/```\s*$/i',     '', trim($text));
                    $text = trim($text);

                    if (preg_match('/\{.*\}/s', $text, $m)) {
                        $parsed = json_decode($m[0], true);
                        if ($parsed && isset($parsed['analysis'])) {
                            $analysisData = $parsed;
                            $source       = 'gemma3';
                        } elseif ($parsed && isset($parsed['exercise'])) {
                            $analysisData = ['analysis' => $parsed];
                            $source       = 'gemma3';
                        }
                    }
                }
            }

            if ($analysisData === null) {
                error_log("⚠️ Gemma 3 HF Inference API failed (HTTP {$gemmaCode}, err: {$gemmaErr}). Using local coaching library.");
            }
        } else {
            error_log("⚠️ HF_TOKEN not set in environment. Skipping Gemma 3 tier.");
        }
    }

    // ── TIER 3: Local coaching library ────────────────────────────────────
    if ($analysisData === null) {
        $key       = strtolower($exercise_name);
        $fallbacks = [
            'squat' => [
                'exercise'        => 'Squats',
                'accuracy_score'  => 80,
                'status'          => 'GOOD',
                'summary'         => 'Squats are a fundamental compound movement. Focus on depth, knee tracking, and keeping your chest tall throughout the movement.',
                'pro_tips'        => [
                    'Keep your feet shoulder-width apart with toes slightly turned out.',
                    'Drive your knees outward in line with your toes — never let them cave in.',
                    'Keep your chest tall and core braced throughout the descent.',
                    'Aim to hit parallel (hips level with knees) or below for maximum benefit.',
                ],
                'common_mistakes' => ['Knees caving inward', 'Not reaching parallel depth', 'Heels rising off the floor'],
                'key_cues'        => ['Chest up', 'Knees out', 'Push the floor away'],
            ],
            'push' => [
                'exercise'        => 'Push-Ups',
                'accuracy_score'  => 85,
                'status'          => 'GOOD',
                'summary'         => 'Push-ups are a classic upper body exercise. Maintaining a rigid plank-like body position is key to maximising results.',
                'pro_tips'        => [
                    'Tuck your elbows at 45 degrees — not flared out at 90.',
                    'Keep a straight line from head to heels; do not let your hips sag or pike.',
                    'Lower your chest until it nearly touches the floor for full range of motion.',
                    'Exhale as you push up; inhale on the way down.',
                ],
                'common_mistakes' => ['Hips sagging or piking', 'Flared elbows', 'Incomplete range of motion'],
                'key_cues'        => ['Hollow body', 'Elbows at 45', 'Chest to floor'],
            ],
            'deadlift' => [
                'exercise'        => 'Deadlift',
                'accuracy_score'  => 82,
                'status'          => 'CAUTION',
                'summary'         => 'The deadlift is king of posterior chain exercises. Prioritise a neutral spine above all else.',
                'pro_tips'        => [
                    'Set up with the bar over mid-foot, hips higher than your knees.',
                    'Engage lats by "putting your shoulder blades in your back pockets".',
                    'Drive through the floor — think leg press, not row.',
                    'Lock out hips and knees simultaneously at the top.',
                ],
                'common_mistakes' => ['Rounding the lower back', 'Bar drifting from the body', 'Hyperextending at lockout'],
                'key_cues'        => ['Neutral spine', 'Bar against shins', 'Hips and knees together'],
            ],
            'bench' => [
                'exercise'        => 'Bench Press',
                'accuracy_score'  => 84,
                'status'          => 'GOOD',
                'summary'         => 'The bench press is the ultimate upper body strength builder. Master the setup and you will press more safely.',
                'pro_tips'        => [
                    'Retract and depress your shoulder blades — "bend the bar" to engage lats.',
                    'Keep your feet flat on the floor to maintain drive and stability.',
                    'Touch the bar to your lower sternum, not your collarbone.',
                    'Maintain a slight arch in your lower back with your glutes on the bench.',
                ],
                'common_mistakes' => ['Bar path too high on chest', 'Feet leaving the floor', 'Bouncing bar off chest'],
                'key_cues'        => ['Shoulder blades retracted', 'Feet flat', 'Lower sternum touch'],
            ],
            'lunge' => [
                'exercise'        => 'Lunges',
                'accuracy_score'  => 83,
                'status'          => 'GOOD',
                'summary'         => 'Lunges are an excellent unilateral exercise for building leg strength and stability. Focus on control and balance.',
                'pro_tips'        => [
                    'Step far enough forward so your front shin stays vertical.',
                    'Keep your torso upright — do not lean forward at the waist.',
                    'Lower your back knee to just above the floor for full depth.',
                    'Drive through your front heel to return to standing.',
                ],
                'common_mistakes' => ['Front knee shooting past toes', 'Torso leaning forward', 'Back knee slamming the floor'],
                'key_cues'        => ['Upright chest', 'Front shin vertical', 'Drive the heel'],
            ],
            'plank' => [
                'exercise'        => 'Plank',
                'accuracy_score'  => 90,
                'status'          => 'GOOD',
                'summary'         => 'The plank is a cornerstone of core training. Focus on a perfectly rigid body line and consistent breathing.',
                'pro_tips'        => [
                    'Engage your glutes and core to keep your body perfectly flat.',
                    'Keep your neck neutral by looking at a spot between your hands.',
                    'Avoid shrugging your shoulders — push actively through your forearms.',
                    'Breathe slowly and steadily throughout the hold.',
                ],
                'common_mistakes' => ['Hips too high or too low', 'Holding breath', 'Shrugged shoulders'],
                'key_cues'        => ['Squeeze everything', 'Neutral neck', 'Active forearms'],
            ],
        ];

        $matched = null;
        foreach ($fallbacks as $k => $data) {
            if (strpos($key, $k) !== false) {
                $matched = $data;
                break;
            }
        }

        if (!$matched) {
            $matched = [
                'exercise'        => $exercise_name,
                'accuracy_score'  => 82,
                'status'          => 'GOOD',
                'summary'         => "This is a solid workout tutorial for {$exercise_name}. Focus on controlled movement, proper breathing, and maintaining good posture throughout each repetition.",
                'pro_tips'        => [
                    'Maintain a neutral spine and brace your core on every rep.',
                    'Control the eccentric (lowering) phase to maximise muscle activation.',
                    'Ensure full range of motion while preserving proper joint alignment.',
                    'Breathe consistently — exhale on exertion, inhale on release.',
                ],
                'common_mistakes' => ['Rushing through reps', 'Incomplete range of motion'],
                'key_cues'        => ['Control the movement', 'Breathe with purpose'],
            ];
        }

        $analysisData = ['analysis' => $matched];
        $source       = 'local_fallback';
    }

    // ── Respond ───────────────────────────────────────────────────────────
    // Optionally expose which tier served the response (useful for debugging)
    // $analysisData['_source'] = $source;

    echo json_encode($analysisData);
}
?>
