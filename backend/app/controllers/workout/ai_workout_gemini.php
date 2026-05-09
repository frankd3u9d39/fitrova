<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Prevent PHP warnings/notices from breaking JSON output
error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(180); // Ensure script doesn't time out during AI generation

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

// API Configuration
$GEMINI_API_KEY = 'AQ.Ab8RN6K04-jc_xK7I1yOSz291VKJ1pwm0j5izQMReuOhalV7uA';
$PIXABAY_API_KEY = '55510128-80706278b60fe59adb3d443e4';
$YOUTUBE_API_KEY = 'AIzaSyD-tOfE-vkGE4mBNzJLadLb_U6CCfztqUE'; // Added from ai-service/.env

// ═══════════════════════════════════════════════════════════════
// CURATED EXERCISE VIDEO LIBRARY - Verified videos per exercise
// Each entry maps keywords to a specific, correct video URL
// ═══════════════════════════════════════════════════════════════
$EXERCISE_VIDEO_MAP = [
    // CARDIO
    ['keywords' => ['jumping jack', 'star jump'],           'video' => 'https://www.youtube.com/watch?v=VjO-y9oN5C0',  'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
    ['keywords' => ['high knee', 'high knees'],             'video' => 'https://www.youtube.com/watch?v=kYv9d5Nn-y4',  'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
    ['keywords' => ['burpee'],                              'video' => 'https://www.youtube.com/watch?v=dZgVxmf6jkA',  'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
    ['keywords' => ['mountain climber'],                    'video' => 'https://www.youtube.com/watch?v=zJg13E1C55w',  'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
    ['keywords' => ['jump rope', 'skipping'],               'video' => 'https://www.youtube.com/watch?v=vVj4u651L9w',  'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
    ['keywords' => ['running', 'jogging', 'jog', 'run'],    'video' => 'https://www.youtube.com/watch?v=P9W2h9825bM',  'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],

    // UPPER BODY
    ['keywords' => ['push up', 'pushup', 'push-up'],        'video' => 'https://www.youtube.com/watch?v=IODxDxX7oi4',  'image' => 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800'],
    ['keywords' => ['bench press'],                         'video' => 'https://www.youtube.com/watch?v=vcBig73ojpE',  'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
    ['keywords' => ['shoulder press', 'overhead press', 'military press'], 'video' => 'https://www.youtube.com/watch?v=B-aVuyhvLHU', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
    ['keywords' => ['bicep curl', 'curl', 'dumbbell curl'],  'video' => 'https://www.youtube.com/watch?v=F08VqG0k-gI', 'image' => 'https://images.pexels.com/photos/4164761/pexels-photo-4164761.jpeg?w=800'],
    ['keywords' => ['lateral raise', 'side raise'],          'video' => 'https://www.youtube.com/watch?v=3VcKaXpzqRo', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
    ['keywords' => ['tricep', 'dip', 'pushdown'],            'video' => 'https://www.youtube.com/watch?v=1Fw_0V12UoE', 'image' => 'https://images.pexels.com/photos/4164761/pexels-photo-4164761.jpeg?w=800'],
    ['keywords' => ['pull up', 'pullup', 'chin up'],         'video' => 'https://www.youtube.com/watch?v=eGo4IYlbE5g', 'image' => 'https://images.pexels.com/photos/4164761/pexels-photo-4164761.jpeg?w=800'],
    ['keywords' => ['row', 'barbell row', 'dumbbell row'],   'video' => 'https://www.youtube.com/watch?v=gT_nJ9P4F9s', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],

    // LOWER BODY
    ['keywords' => ['squat', 'back squat', 'goblet squat'],  'video' => 'https://www.youtube.com/watch?v=aclHkVaku9U', 'image' => 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800'],
    ['keywords' => ['lunge', 'walking lunge', 'reverse lunge'], 'video' => 'https://www.youtube.com/watch?v=QOVaHwm-Q6U', 'image' => 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800'],
    ['keywords' => ['deadlift', 'romanian deadlift', 'rdl'],  'video' => 'https://www.youtube.com/watch?v=op9kVnSso6Q', 'image' => 'https://images.pexels.com/photos/4164761/pexels-photo-4164761.jpeg?w=800'],
    ['keywords' => ['calf raise', 'calf'],                   'video' => 'https://www.youtube.com/watch?v=-M4-G8p8fmc', 'image' => 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800'],
    ['keywords' => ['leg press', 'leg extension', 'leg curl'], 'video' => 'https://www.youtube.com/watch?v=yZmx_7igP2A', 'image' => 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800'],
    ['keywords' => ['glute bridge', 'hip thrust'],           'video' => 'https://www.youtube.com/watch?v=wPM8icPu6H8', 'image' => 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800'],

    // CORE
    ['keywords' => ['plank', 'forearm plank'],               'video' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
    ['keywords' => ['crunch', 'sit up', 'situp', 'ab'],      'video' => 'https://www.youtube.com/watch?v=Xyd_fa5zoEU', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
    ['keywords' => ['russian twist'],                        'video' => 'https://www.youtube.com/watch?v=Nm0h97Y4uqA', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
    ['keywords' => ['leg raise', 'hanging leg'],             'video' => 'https://www.youtube.com/watch?v=K3u-yT588Ew', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],

    // FLEXIBILITY / RECOVERY
    ['keywords' => ['stretch', 'yoga', 'cooldown', 'cool down'], 'video' => 'https://www.youtube.com/watch?v=tXWh-dowiLg', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],

    // FULL BODY
    ['keywords' => ['kettlebell swing', 'kettlebell'],       'video' => 'https://www.youtube.com/watch?v=Y5U-uGj7V9E', 'image' => 'https://images.pexels.com/photos/4164761/pexels-photo-4164761.jpeg?w=800'],
    ['keywords' => ['box jump', 'jump squat', 'plyometric'],  'video' => 'https://www.youtube.com/watch?v=52r_Ul5k03g', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],

];

// Fallback categories for exercises that don't match any keyword
$CATEGORY_FALLBACK_VIDEOS = [
    'cardio'   => 'https://cdn.pixabay.com/video/2017/11/15/12963-243165477_small.mp4',
    'strength' => 'https://videos.pexels.com/video-files/8401319/8401319-hd_1920_1080_30fps.mp4',
    'core'     => 'https://videos.pexels.com/video-files/4366624/4366624-hd_1080_1920_25fps.mp4',
    'recovery' => 'https://videos.pexels.com/video-files/5510141/5510141-hd_1080_1920_25fps.mp4',
    'general'  => 'https://videos.pexels.com/video-files/5510141/5510141-hd_1080_1920_25fps.mp4',
];

/**
 * Validates if a YouTube video is actually available and embeddable.
 * Uses the OEmbed endpoint which doesn't require an API key.
 */
function isYoutubeVideoAvailable($url) {
    if (strpos($url, 'youtube.com') === false && strpos($url, 'youtu.be') === false) {
        return true; // Not a YouTube URL
    }
    
    $oembedUrl = "https://www.youtube.com/oembed?url=" . urlencode($url) . "&format=json";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $oembedUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Increased timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Added for local dev flexibility
    
    // Add User-Agent to avoid being blocked by YouTube/Google
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode === 200);
}

/**
 * Searches YouTube for a workout video based on a search term.
 * Fetches multiple results and verifies availability until a working one is found.
 */
function searchYouTube($searchTerm, $apiKey) {
    if (empty($searchTerm)) return null;
    
    // We fetch up to 3 results to find a working one without too much overhead
    $query = urlencode($searchTerm . " exercise tutorial");
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&maxResults=3&q=$query&type=video&videoEmbeddable=true&key=$apiKey";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 7);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $videoId = $item['id']['videoId'] ?? null;
                if (!$videoId) continue;
                
                $videoUrl = "https://www.youtube.com/watch?v=" . $videoId;
                
                // VERIFY AVAILABILITY (Deep Search Retry)
                if (isYoutubeVideoAvailable($videoUrl)) {
                    return $videoUrl;
                }
            }
        }
    }
    
    return null;
}

/**
 * Finds the best matching video for an exercise name.
 * Now prioritized: Curated Map -> Dynamic YouTube "Deep Search" -> Category Fallback
 */
function findExerciseVideo($exerciseName, $searchTerm, $exerciseVideoMap, $categoryFallbacks, $ytApiKey, $workoutType = 'general') {
    $nameLower = strtolower($exerciseName);
    
    // 1. Try our curated library first (Verified high-quality results)
    foreach ($exerciseVideoMap as $entry) {
        foreach ($entry['keywords'] as $keyword) {
            if (strpos($nameLower, $keyword) !== false) {
                $videoUrl = $entry['video'];
                if (isYoutubeVideoAvailable($videoUrl)) {
                    return [
                        'video' => $videoUrl,
                        'image' => $entry['image']
                    ];
                }
            }
        }
    }
    
    // 2. Dynamic YouTube "Deep Search" (Tries multiple results)
    $dynamicVideo = searchYouTube($searchTerm ?: $exerciseName, $ytApiKey);
    if ($dynamicVideo) {
        return [
            'video' => $dynamicVideo,
            'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'
        ];
    }
    
    // 3. Last Resort Fallback (Stock Video instead of static image)
    $type = strtolower($workoutType);
    return [
        'video' => $categoryFallbacks[$type] ?? $categoryFallbacks['general'],
        'image' => 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800'
    ];
}

function callGemini($prompt, $apiKey) {
    $logFile = __DIR__ . '/gemini_debug.log';
    
    // Multi-Model Resilience: Using 2026 stable models verified for generateContent
    $models = [
        'gemini-flash-latest',
        'gemini-2.5-flash',
        'gemini-2.0-flash',
        'gemini-pro-latest'
    ];
    
    foreach ($models as $modelName) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelName . ':generateContent?key=' . $apiKey;
        
        $data = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 4096,
                'response_mime_type' => 'application/json'
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Log more details to understand why it fails (especially 404/429)
        $logMsg = date('Y-m-d H:i:s') . " (Trying {$modelName}) HTTP: $httpCode | CurlErr: $curlError";
        if ($httpCode !== 200) {
            $logMsg .= " | Response: " . substr($response, 0, 500); // Log first 500 chars of error
        }
        file_put_contents($logFile, $logMsg . "\n", FILE_APPEND);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                return $result['candidates'][0]['content']['parts'][0]['text'];
            }
        }
        
        usleep(200000); 
    }

    throw new Exception('All Gemini models failed or were throttled.');
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? null;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'User ID required']);
        exit();
    }
    
    // Get user profile
    $stmt = $pdo->prepare("
        SELECT up.*, u.first_name 
        FROM user_profiles up
        JOIN users u ON up.user_id = u.id
        WHERE up.user_id = ?
    ");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get workout history
    $historyStmt = $pdo->prepare("
        SELECT COUNT(*) as total_workouts,
               MAX(completed_date) as last_workout
        FROM workout_logs
        WHERE user_id = ? AND completed_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $historyStmt->execute([$userId]);
    $history = $historyStmt->fetch(PDO::FETCH_ASSOC);
    
    // 1. PERSISTENCE CHECK: Load existing plan for today if it exists
    $planStmt = $pdo->prepare("
        SELECT plan_data 
        FROM workout_plans 
        WHERE user_id = ? AND plan_date = CURDATE() 
        LIMIT 1
    ");
    $planStmt->execute([$userId]);
    $existingPlan = $planStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingPlan) {
        $workoutData = json_decode($existingPlan['plan_data'], true);
        $ai_provider = 'Database (Previously Generated)';
        goto finalize_response; // Skip AI generation
    }

    // Calculate days since last workout for the AI prompt
    $daysSince = 0; // Default to 0 for new users to avoid AI thinking they missed sessions
    if ($history['last_workout']) {
        $lastDate = new DateTime($history['last_workout']);
        $today = new DateTime();
        $daysSince = $today->diff($lastDate)->days;
    }

    // Detect Beginner status for enhanced coaching
    $activityLevel = strtolower($profile['activity_level'] ?? 'moderate');
    $isBeginner = ($activityLevel === 'sedentary' || $activityLevel === 'lightly active' || $activityLevel === 'moderate');
    $hasEquipment = (bool)($profile['has_equipment'] ?? true);
    
    // 2. Build AI prompt
    $prompt = "You are a professional fitness trainer. Generate a personalized workout plan.\n\n";
    $prompt .= "User Profile:\n";
    $prompt .= "- Name: " . ($profile['first_name'] ?? 'User') . "\n";
    $prompt .= "- Fitness Goal: " . ($profile['fitness_goal'] ?? 'general fitness') . "\n";
    $prompt .= "- Activity Level: " . ($profile['activity_level'] ?? 'moderate') . "\n";
    $prompt .= "- Equipment Available: " . ($hasEquipment ? "Full Gym Access" : "NO EQUIPMENT (Bodyweight Only)") . "\n";
    $prompt .= "- Workouts in last 30 days: " . ($history['total_workouts'] ?? 0) . "\n";
    $prompt .= "- Days since last workout: " . $daysSince . "\n";
    $prompt .= "- Age: " . ($profile['age'] ?? 'not specified') . "\n\n";

    if (($history['total_workouts'] ?? 0) === 0) {
        $prompt .= "NOTE: This is a NEW user account. They have not started their journey yet. Do NOT return any 'missed_workouts'.\n";
    }
    
    if (!$hasEquipment) {
        $prompt .= "CRITICAL: The user has NO equipment. ONLY suggest bodyweight exercises. DO NOT mention weights, bars, or gym machinery.\n";
    }

    if ($isBeginner) {
        $prompt .= "CRITICAL: The user is a BEGINNER. Choose exercises that are safe and easy to follow. Avoid complex compound movements unless specified.\n";
        $prompt .= "VIDEO STRATEGY: Provide a 'search_term' optimized for finding slow-paced instructional videos (e.g., 'pushups tutorial').\n";
        $prompt .= "INSTRUCTION STRATEGY: Provide 3-4 clear, numbered steps for perfect form. Make them easy for a first-timer to understand.\n\n";
    } else {
        $prompt .= "VIDEO STRATEGY: For each exercise, provide a 'search_term' which is a standard fitness name for finding a video.\n";
        $prompt .= "CRITICAL: If the user has NO equipment, include 'bodyweight' or 'no equipment' in the search_term for every exercise.\n";
        $prompt .= "Instructions should be 1-2 powerful cues for correct form.\n\n";
    }

    $prompt .= "3D ANIMATION STRATEGY:\n";
    $prompt .= "For each exercise, provide an 'ai_motion' object to drive a simplified 3D skeleton.\n";
    $prompt .= "Available targets: wrist_l, wrist_r, ankle_l, ankle_r (Positions relative to hips).\n";
    $prompt .= "Available joint rotations: hips, spine, neck, head, shoulder_l, shoulder_r, elbow_l, elbow_r, leg_l, leg_r.\n";
    $prompt .= "USE TARGETS FOR HANDS/FEET whenever possible. Example for Jumping Jacks:\n";
    $prompt .= "- Start: wrist_l: [0.5, -0.8, 0], wrist_r: [-0.5, -0.8, 0], ankle_l: [-0.2, -1.0, 0]\n";
    $prompt .= "- Jump: wrist_l: [0.3, 0.8, 0], wrist_r: [-0.3, 0.8, 0], ankle_l: [-0.6, -1.0, 0], hips: [0, 0.5, 0]\n";
    $prompt .= "Rotations are in RADIANS. Positions are in METERS relative to hips.\n";
    $prompt .= "Provide 3-5 keyframes (time 0.0 to 1.0) for one repetition.\n\n";
    
    $prompt .= "JSON FORMAT:\n";
    $prompt .= "{\n";
    $prompt .= '  "todays_workout": {';
    $prompt .= '    "name": "Workout Title",';
    $prompt .= '    "exercises": [';
    $prompt .= '      {"name": "Exercise Name", "search_term": "name", "sets": 3, "reps": 10, "instructions": "cues", "ai_motion": {"keyframes": [{"time": 0, "targets": {"wrist_l": [0.5, -0.8, 0]}, "joints": {"hips": [0,0,0]}, "position": [0,0,0]}]}}';
    $prompt .= '    ],';
    $prompt .= '    "exercises_count": 1,';
    $prompt .= '    "duration": 30,';
    $prompt .= '    "difficulty": "beginner",';
    $prompt .= '    "type": "strength"';
    $prompt .= '  },';
    $prompt .= '  "recovery_score": 90,';
    $prompt .= '  "status": "READY FOR SESSION",';
    $prompt .= '  "missed_workouts": [],' . "\n";
    $prompt .= '  "upcoming_workouts": [' . "\n";
    $prompt .= '    {"name": "Upper Body Power", "scheduled_date": "YYYY-MM-DD", "duration": 45, "exercises_count": 6}' . "\n";
    $prompt .= '  ]' . "\n";
    $prompt .= '}' . "\n";
    $prompt .= 'IMPORTANT: Always return "name" and "scheduled_date" (YYYY-MM-DD) for upcoming workouts.';
    
    // Call Gemini with graceful fallback
    try {
        $aiResponse = callGemini($prompt, $GEMINI_API_KEY);
        
        // Clean response (remove markdown if present)
        $aiResponse = preg_replace('/```json\s*/', '', $aiResponse);
        $aiResponse = preg_replace('/```\s*$/', '', $aiResponse);
        $aiResponse = trim($aiResponse);
        
        // Parse JSON from AI response
        $workoutData = json_decode($aiResponse, true);
    } catch (Exception $e) {
        error_log("Gemini API call failed: " . $e->getMessage());
        $workoutData = ['error' => true, 'message' => $e->getMessage()];
    }

    // SMART VIDEO MATCHING - Uses curated library + AI-Driven YouTube Search
    if ($workoutData && isset($workoutData['todays_workout']['exercises'])) {
        $workoutType = $workoutData['todays_workout']['type'] ?? 'general';

        foreach ($workoutData['todays_workout']['exercises'] as $idx => &$exercise) {
            $exerciseName = $exercise['name'] ?? 'general exercise';
            $searchTerm = $exercise['search_term'] ?? $exerciseName;
            
            $media = findExerciseVideo($exerciseName, $searchTerm, $EXERCISE_VIDEO_MAP, $CATEGORY_FALLBACK_VIDEOS, $YOUTUBE_API_KEY, $workoutType);
            $exercise['video_url'] = $media['video'];
            $exercise['image_url'] = $media['image'];
        }
    }

    // 1. AUTO-HEALING: If AI fails or returns malformed data, use the high-quality mock
    if (!$workoutData || isset($workoutData['error']) || !isset($workoutData['todays_workout'])) {
        error_log("⚠️ Gemini AI error or malformed data, fallback to mock.");
        $workoutData = [
            'todays_workout' => [
                'name' => 'Fitrova Strength Starter',
                'exercises' => [
                    [
                        'name' => 'Jumping Jacks',
                        'sets' => 3, 'reps' => 20, 'duration' => 60,
                        'image_url' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80',
                        'video_url' => $CATEGORY_FALLBACK_VIDEOS['cardio'],
                        'instructions' => 'Stand with feet together and arms at sides. Jump and spread legs while swinging arms overhead.'
                    ],
                    [
                        'name' => 'Deep Squats',
                        'sets' => 3, 'reps' => 15, 'duration' => 60,
                        'image_url' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80',
                        'video_url' => $CATEGORY_FALLBACK_VIDEOS['strength'],
                        'instructions' => 'Lower your hips as if sitting in a chair, keeping your chest up and weight on your heels.'
                    ]
                ],
                'exercises_count' => 2,
                'duration' => 20,
                'difficulty' => 'beginner',
                'type' => 'Full Body'
            ]
        ];
        $ai_provider = 'Fitrova Internal (Fallback)';
    } else {
        $ai_provider = 'Google Gemini Pro';
        
        // SAVE NEW PLAN TO DATABASE
        try {
            $saveStmt = $pdo->prepare("
                INSERT INTO workout_plans (user_id, name, workout_type, plan_data, plan_date, is_active)
                VALUES (?, ?, ?, ?, CURDATE(), 1)
            ");
            $saveStmt->execute([
                $userId,
                $workoutData['todays_workout']['name'] ?? 'Daily Workout',
                $workoutData['todays_workout']['type'] ?? 'mixed',
                json_encode($workoutData)
            ]);
        } catch (Exception $e) {
            error_log("Failed to save workout plan: " . $e->getMessage());
        }
    }

finalize_response:

    // 2. MANDATORY METADATA ENRICHMENT (Ensures frontend fields like 'completed' never crash)
    $workoutData['weekly_progress'] = [
        'completed' => (int)($history['total_workouts'] ?? 0),
        'goal' => 4
    ];
    
    if (!isset($workoutData['status'])) $workoutData['status'] = 'READY FOR SESSION';
    if (!isset($workoutData['recovery_score'])) $workoutData['recovery_score'] = 98;
    if (!isset($workoutData['missed_workouts'])) $workoutData['missed_workouts'] = [];
    
    // 3. SANITIZE AND MAP DATA (Fixes 'TBD' and 'No Video' issues)
    if (isset($workoutData['upcoming_workouts']) && is_array($workoutData['upcoming_workouts'])) {
        foreach ($workoutData['upcoming_workouts'] as $i => &$upcoming) {
            // Map AI variants like 'focus' or 'title' to 'name'
            if (!isset($upcoming['name']) && isset($upcoming['focus'])) $upcoming['name'] = $upcoming['focus'];
            if (!isset($upcoming['name']) && isset($upcoming['title'])) $upcoming['name'] = $upcoming['title'];
            if (!isset($upcoming['name'])) $upcoming['name'] = "Daily Session " . ($i + 1);

            // Map 'day' or 'date' to 'scheduled_date'
            if (!isset($upcoming['scheduled_date'])) {
                if (isset($upcoming['date'])) {
                    $upcoming['scheduled_date'] = $upcoming['date'];
                } elseif (isset($upcoming['day'])) {
                    $dayStr = $upcoming['day'];
                    $upcoming['scheduled_date'] = date('Y-m-d', strtotime("next $dayStr"));
                } else {
                    $upcoming['scheduled_date'] = date('Y-m-d', strtotime('+' . ($i + 1) . ' days'));
                }
            }

            // Ensure exercises exist for 'ActiveWorkout' screen
            if (!isset($upcoming['exercises']) || empty($upcoming['exercises'])) {
                $upcoming['exercises'] = [
                    ['name' => 'Forearm Plank', 'sets' => 3, 'reps' => 60, 'duration' => 60],
                    ['name' => 'Mountain Climbers', 'sets' => 3, 'reps' => 20, 'duration' => 60],
                ];
            }
            
            // Match videos for upcoming exercises too
            foreach ($upcoming['exercises'] as &$ex) {
                $m = findExerciseVideo($ex['name'], $ex['search_term'] ?? $ex['name'], $EXERCISE_VIDEO_MAP, $CATEGORY_FALLBACK_VIDEOS, $YOUTUBE_API_KEY);
                $ex['video_url'] = $m['video'];
                $ex['image_url'] = $m['image'];
            }

            if (!isset($upcoming['duration'])) $upcoming['duration'] = 45;
            if (!isset($upcoming['exercises_count'])) $upcoming['exercises_count'] = count($upcoming['exercises']);
        }
    } else {
        // High-quality fallback with FULL exercise data
        $workoutData['upcoming_workouts'] = [
            [
                'name' => 'Upper Body Power',
                'scheduled_date' => date('Y-m-d', strtotime('+1 day')),
                'day_name' => date('l', strtotime('+1 day')),
                'duration' => 45,
                'exercises_count' => 3,
                'exercises' => [
                    ['name' => 'Push Ups', 'sets' => 3, 'reps' => 15, 'video_url' => 'https://www.youtube.com/watch?v=IODxDxX7oi4', 'image_url' => 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800'],
                    ['name' => 'Dumbbell Curls', 'sets' => 3, 'reps' => 12, 'video_url' => 'https://www.youtube.com/watch?v=F08VqG0k-gI', 'image_url' => 'https://images.pexels.com/photos/4164761/pexels-photo-4164761.jpeg?w=800'],
                    ['name' => 'Lateral Raises', 'sets' => 3, 'reps' => 15, 'video_url' => 'https://www.youtube.com/watch?v=3VcKaXpzqRo', 'image_url' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800']
                ]
            ],
            [
                'name' => 'Core and Stability',
                'scheduled_date' => date('Y-m-d', strtotime('+2 days')),
                'day_name' => date('l', strtotime('+2 days')),
                'duration' => 30,
                'exercises_count' => 2,
                'exercises' => [
                    ['name' => 'Forearm Plank', 'sets' => 3, 'reps' => 60, 'video_url' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw', 'image_url' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Russian Twists', 'sets' => 3, 'reps' => 20, 'video_url' => 'https://www.youtube.com/watch?v=Nm0h97Y4uqA', 'image_url' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800']
                ]
            ]
        ];
    }
    
    // 4. SEND CLEAN RESPONSE
    echo json_encode([
        'status' => 'success',
        'data' => $workoutData,
        'ai_provider' => $ai_provider
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
