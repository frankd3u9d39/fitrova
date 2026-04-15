<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db_config.php';

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
    $GEMINI_API_KEY = 'AQ.Ab8RN6K04-jc_xK7I1yOSz291VKJ1pwm0j5izQMReuOhalV7uA';
    
    $prompt = "You are a professional trainer. Generate a detailed workout plan for: '$workoutName'.\n";
    $prompt .= "Return ONLY valid JSON in this format:\n";
    $prompt .= "{\n";
    $prompt .= '  "name": "' . $workoutName . '",';
    $prompt .= '  "exercises": [';
    $prompt .= '    {"name": "Exercise Name", "sets": 3, "reps": 12, "instructions": "Form tip", "search_term": "standard name"}';
    $prompt .= '  ],';
    $prompt .= '  "duration": 45,';
    $prompt .= '  "difficulty": "intermediate",';
    $prompt .= '  "type": "strength"';
    $prompt .= "\n}";

    // Call Gemini (Simplified call)
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . $GEMINI_API_KEY;
    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
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
