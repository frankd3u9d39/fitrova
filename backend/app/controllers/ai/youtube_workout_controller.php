<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/env_loader.php';
loadEnv(__DIR__ . '/../../../.env');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$YOUTUBE_API_KEY = getenv('YOUTUBE_API_KEY');
if (!$YOUTUBE_API_KEY || $YOUTUBE_API_KEY === 'YOUR_YOUTUBE_API_KEY_HERE') {
    $YOUTUBE_API_KEY = 'AIzaSyD-tOfE-vkGE4mBNzJLadLb_U6CCfztqUE';
}
$AI_SERVICE_URL = getenv('AI_SERVICE_URL') ?: 'http://localhost:5001/api/analyze-youtube';

$action = $_GET['action'] ?? 'search';

if ($action === 'search') {
    $query = $_GET['query'] ?? 'workout';
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&maxResults=10&q=" . urlencode($query . " workout tutorial") . "&type=video&key=" . $YOUTUBE_API_KEY;

    $response = @file_get_contents($url);
    if ($response === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch from YouTube API', 'key_used' => substr($YOUTUBE_API_KEY, 0, 5) . '...']);
        exit();
    }
    
    // Validate JSON response
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Invalid response from YouTube API',
            'message' => 'YouTube API key may be invalid or quota exceeded',
            'hint' => 'Please set YOUTUBE_API_KEY in backend/.env file'
        ]);
        exit();
    }
    
    // Check for YouTube API errors
    if (isset($decoded['error'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'YouTube API Error',
            'message' => $decoded['error']['message'] ?? 'Unknown error',
            'code' => $decoded['error']['code'] ?? 'unknown'
        ]);
        exit();
    }
    
    echo $response;
} 
elseif ($action === 'analyze') {
    $input = json_decode(file_get_contents('php://input'), true);
    $youtube_url = $input['youtube_url'] ?? '';
    $exercise_name = $input['exercise_name'] ?? 'Workout';

    if (!$youtube_url) {
        echo json_encode(['error' => 'YouTube URL is required']);
        exit();
    }

    $ch = curl_init($AI_SERVICE_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'youtube_url' => $youtube_url,
        'exercise_name' => $exercise_name
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Analysis takes time

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo json_encode([
            'error' => 'AI Service connection failed',
            'curl_error' => $curlError,
            'details' => json_decode($response, true)
        ]);
    } else {
        echo $response;
    }
}
?>
