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
require_once __DIR__ . '/../../../config/env_loader.php';
loadEnv(__DIR__ . '/../../../.env');
require_once __DIR__ . '/../../../config/gemma_helper.php';
require_once __DIR__ . '/../../../config/deepseek_helper.php';
require_once __DIR__ . '/../../middleware/AISubscriptionGate.php';
use App\Middleware\AISubscriptionGate;

// ═══════════════════════════════════════════════════════════════
// DYNAMIC CONFIGURATION - Loaded from Database
// ═══════════════════════════════════════════════════════════════
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

if (($settings['maintenance_mode'] ?? 'false') === 'true') {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'System is under maintenance. Please try again later.']);
    exit();
}

$PRIMARY_MODEL = $settings['ai_model_primary'] ?? 'deepseek-chat';
$SYSTEM_PROMPT = $settings['ai_system_prompt'] ?? 'You are a professional fitness trainer. Generate a personalized workout plan.';
$AI_TEMPERATURE = (float)($settings['ai_temperature'] ?? 0.7);
$DEEPSEEK_API_KEY = $settings['ai_deepseek_api_key'] ?? (getenv('DEEPSEEK_API_KEY') ?: '');
$GEMINI_API_KEY = $settings['ai_gemini_api_key'] ?? '';
$YOUTUBE_API_KEY = $settings['ai_youtube_api_key'] ?? '';

// ═══════════════════════════════════════════════════════════════
// DYNAMIC EXERCISE LIBRARY - Loaded from Database
// ═══════════════════════════════════════════════════════════════
$exerciseStmt = $pdo->query("SELECT keywords, video_url as video, image_url as image FROM exercise_library");
$EXERCISE_VIDEO_MAP = [];
while ($row = $exerciseStmt->fetch(PDO::FETCH_ASSOC)) {
    $row['keywords'] = explode(',', $row['keywords']);
    $EXERCISE_VIDEO_MAP[] = $row;
}

// Hardcoded list removed - now using database-driven $EXERCISE_VIDEO_MAP

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Reduced timeout for speed
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
    if (empty($apiKey) || $apiKey === 'YOUR_YOUTUBE_API_KEY_HERE') {
        return null;
    }
    
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
                    $imageUrl = $item['snippet']['thumbnails']['high']['url'] ?? "https://img.youtube.com/vi/$videoId/hqdefault.jpg";
                    return [
                        'video' => $videoUrl,
                        'image' => $imageUrl
                    ];
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
function findExerciseVideo($exerciseName, $searchTerm, $exerciseVideoMap, $categoryFallbacks, $ytApiKey, $workoutType = 'general', $skipCuratedMap = false) {
    $nameLower = strtolower($exerciseName);
    
    // 1. Try our curated library first (Verified high-quality results) - skipped if skipCuratedMap is true (Premium AI Coach)
    if (!$skipCuratedMap) {
        foreach ($exerciseVideoMap as $entry) {
            foreach ($entry['keywords'] as $keyword) {
                if (strpos($nameLower, $keyword) !== false) {
                    return [
                        'video' => $entry['video'],
                        'image' => $entry['image']
                    ];
                }
            }
        }
    }
    
    // 2. Dynamic YouTube "Deep Search" (Tries multiple results)
    $dynamicResult = searchYouTube($searchTerm ?: $exerciseName, $ytApiKey);
    if ($dynamicResult) {
        return $dynamicResult;
    }
    
    // 3. Last Resort Fallback (Stock Video instead of static image)
    $type = strtolower($workoutType);
    return [
        'video' => $categoryFallbacks[$type] ?? $categoryFallbacks['general'],
        'image' => 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800'
    ];
}

/**
 * Saves a generated or dynamically matched exercise to the dynamic database library.
 * This ensures every new AI-generated content automatically registers and displays
 * inside the Admin Nexus "Content Control" Exercise Library dashboard page!
 */
function saveExerciseToLibrary($pdo, $exercise, $workoutType = 'strength', $difficulty = 'intermediate') {
    $name = trim($exercise['name'] ?? '');
    if (empty($name)) return;
    
    // Check if it already exists (case-insensitive)
    $stmt = $pdo->prepare("SELECT id FROM exercise_library WHERE LOWER(name) = LOWER(?)");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        return; // Already exists, do not duplicate!
    }
    
    // Determine category matching database enum: 'cardio','strength','core','recovery','full_body'
    $category = 'strength';
    $typeLower = strtolower($workoutType);
    if (strpos($typeLower, 'cardio') !== false) {
        $category = 'cardio';
    } elseif (strpos($typeLower, 'core') !== false || strpos($typeLower, 'abs') !== false) {
        $category = 'core';
    } elseif (strpos($typeLower, 'recovery') !== false || strpos($typeLower, 'stretch') !== false) {
        $category = 'recovery';
    } elseif (strpos($typeLower, 'full') !== false) {
        $category = 'full_body';
    }
    
    // Determine difficulty matching database enum: 'beginner','intermediate','advanced'
    $diffLower = strtolower($difficulty);
    $diffValue = 'intermediate';
    if ($diffLower === 'beginner' || $diffLower === 'intermediate' || $diffLower === 'advanced') {
        $diffValue = $diffLower;
    }
    
    // Auto-generate standard search-friendly keywords
    $keywords = implode(', ', array_unique(array_filter([
        strtolower($name), 
        strtolower($category),
        strtolower($diffValue)
    ])));
    
    // Insert new exercise into library
    try {
        $insert = $pdo->prepare("
            INSERT INTO exercise_library (name, keywords, video_url, image_url, category, difficulty)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            $name,
            $keywords,
            $exercise['video_url'] ?? null,
            $exercise['image_url'] ?? null,
            $category,
            $diffValue
        ]);
    } catch (Exception $e) {
        error_log("Failed to insert exercise to library: " . $e->getMessage());
    }
}

function getLocalSmartWorkout($pdo, $userId, $profile, $hasEquipment, $categoryFallbacks) {
    $firstName = $profile['first_name'] ?? 'User';
    $goal = strtolower($profile['fitness_goal'] ?? 'general fitness');
    $activity = strtolower($profile['activity_level'] ?? 'moderate');
    
    // Determine level
    $level = 'intermediate';
    if ($activity === 'sedentary' || $activity === 'lightly active' || $activity === 'moderate') {
        $level = 'beginner';
    } elseif ($activity === 'active' || $activity === 'very active') {
        $level = 'advanced';
    }
    
    // Progressive Overload calculations based on completed workouts count
    $logCount = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM workout_logs WHERE user_id = ?");
        $stmt->execute([$userId]);
        $logCount = intval($stmt->fetchColumn());
    } catch (Exception $e) {}

    // Reps scale up: +1 rep for every 2 completed workouts (cap at +8)
    $repScale = min(8, floor($logCount / 2));
    // Sets scale up: 3 sets base, +1 set for every 8 completed workouts (cap at 5 sets)
    $setsCount = 3 + min(2, floor($logCount / 8));
    
    // Use day of the week to rotate workouts: 1 (Monday) to 7 (Sunday)
    $dayOfWeek = intval(date('N'));
    // Use week of year to alternate between Week A and Week B splits
    $weekIndex = intval(date('W')) % 2;
    
    // Define 7-Day rotation exercises pool (Week A and Week B splits)
    if ($weekIndex === 0) {
        // WEEK A
        $bodyweightPool = [
            1 => [ // Chest, Push & Abs Focus
                'name' => "Monday Push & Abs Burner (Week A)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Standard Push Ups', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Keep your body in a straight line, lower chest near floor, push up.', 'video' => 'https://www.youtube.com/watch?v=IODxDxX7oi4', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Wide-grip Push Ups', 'sets' => $setsCount, 'reps' => 8 + $repScale, 'duration' => 60, 'instructions' => 'Hands wider than shoulder-width. Lower slowly and press back up.', 'video' => 'https://www.youtube.com/watch?v=rr6eFNNDQdo', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Bench/Chair Tricep Dips', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Lower hips off bench/chair, bend elbows to 90 degrees, push up.', 'video' => 'https://www.youtube.com/watch?v=0326dy_-CzM', 'image' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80'],
                    ['name' => 'Forearm Plank Hold', 'sets' => $setsCount, 'reps' => 1, 'duration' => 45 + ($repScale * 5), 'instructions' => 'Keep core tight, body level. Do not sag hips.', 'video' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Mountain Climbers', 'sets' => $setsCount, 'reps' => 20 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Drive knees to chest quickly from a pushup position.', 'video' => 'https://www.youtube.com/watch?v=cnyTQDSE884', 'image' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=800&q=80']
                ]
            ],
            2 => [ // Lower Body / Leg Focus
                'name' => "Tuesday Lower Body Sculpt (Week A)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Bodyweight Squats', 'sets' => $setsCount, 'reps' => 15 + $repScale, 'duration' => 60, 'instructions' => 'Lower hips back as if sitting, weight on heels, chest high.', 'video' => 'https://www.youtube.com/watch?v=aclHkVaku9U', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Reverse Lunges', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Step backward, lower back knee close to ground, push forward.', 'video' => 'https://www.youtube.com/watch?v=O-L4P7Sldf0', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Single-Leg Glute Bridges', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Lie on back, lift one leg, drive hips upward using plant heel.', 'video' => 'https://www.youtube.com/watch?v=A3nSgJ11j8I', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Standing Calf Raises', 'sets' => $setsCount, 'reps' => 20 + $repScale, 'duration' => 60, 'instructions' => 'Squeeze calf muscles at top of motion. Perform slowly.', 'video' => 'https://www.youtube.com/watch?v=-M4-G8p8fmc', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Bicycle Crunches', 'sets' => $setsCount, 'reps' => 15 + ($repScale * 2), 'duration' => 60, 'instructions' => 'Alternately touch elbow to opposite knee with shoulders up.', 'video' => 'https://www.youtube.com/watch?v=Iwyvozckjak', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80']
                ]
            ],
            3 => [ // Cardio Endurance & Agility
                'name' => "Wednesday Metabolic Conditioning (Week A)",
                'type' => 'cardio',
                'exercises' => [
                    ['name' => 'Jumping Jacks', 'sets' => $setsCount, 'reps' => 25 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Swing arms overhead while jumping legs wide in sync.', 'video' => 'https://www.youtube.com/watch?v=7Pxr4xOrhNk', 'image' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80'],
                    ['name' => 'High Knees', 'sets' => $setsCount, 'reps' => 30 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Run in place, driving knees to chest level dynamically.', 'video' => 'https://www.youtube.com/watch?v=QA6Vn5tV_mY', 'image' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80'],
                    ['name' => 'Burpees', 'sets' => $setsCount, 'reps' => 8 + $repScale, 'duration' => 45, 'instructions' => 'Squat, jump feet back, pushup, jump feet forward, jump up.', 'video' => 'https://www.youtube.com/watch?v=dZgVxmf6jkA', 'image' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80'],
                    ['name' => 'Mountain Climbers', 'sets' => $setsCount, 'reps' => 25 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Alternately drive knees toward chest quickly in high plank.', 'video' => 'https://www.youtube.com/watch?v=cnyTQDSE884', 'image' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=800&q=80'],
                    ['name' => 'Plank Shoulder Taps', 'sets' => $setsCount, 'reps' => 16 + $repScale, 'duration' => 45, 'instructions' => 'In high plank, tap alternate shoulders without shifting hips.', 'video' => 'https://www.youtube.com/watch?v=VfwCQ14soUo', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800']
                ]
            ],
            4 => [ // Core Strength & Stability
                'name' => "Thursday Core & Pillar Stability (Week A)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Bird Dog', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Extend opposite arm/leg from all-fours position, keep back flat.', 'video' => 'https://www.youtube.com/watch?v=wiF5XMDjsVM', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Dead Bug', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Lie on back, extend opposite arm/leg while pressing low back down.', 'video' => 'https://www.youtube.com/watch?v=g_BYB0R1bf8', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Forearm Plank Hold', 'sets' => $setsCount, 'reps' => 1, 'duration' => 50 + ($repScale * 5), 'instructions' => 'Engage core, maintain neutral neck, align hips and shoulders.', 'video' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Superman Extensions', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Lie face down, lift chest, arms, and legs off floor, pause at top.', 'video' => 'https://www.youtube.com/watch?v=z6PJMT2y8GQ', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Side Plank Hold', 'sets' => $setsCount, 'reps' => 1, 'duration' => 30 + ($repScale * 3), 'instructions' => 'Hold body sideways on forearm, raise hips to form straight line.', 'video' => 'https://www.youtube.com/watch?v=NXr4Fwkuq0Y', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800']
                ]
            ],
            5 => [ // Full Body Conditioning Focus
                'name' => "Friday Full Body Conditioning (Week A)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Burpees', 'sets' => $setsCount, 'reps' => 8 + $repScale, 'duration' => 45, 'instructions' => 'Drop, jump back, pushup, jump forward, jump up.', 'video' => 'https://www.youtube.com/watch?v=dZgVxmf6jkA', 'image' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80'],
                    ['name' => 'Bodyweight Squats', 'sets' => $setsCount, 'reps' => 15 + $repScale, 'duration' => 60, 'instructions' => 'Hips back, thighs parallel to floor, drive through heels.', 'video' => 'https://www.youtube.com/watch?v=aclHkVaku9U', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Standard Push Ups', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Lower chest near floor, push up, keep body linear.', 'video' => 'https://www.youtube.com/watch?v=IODxDxX7oi4', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'High Knees', 'sets' => $setsCount, 'reps' => 30 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Run in place, drive knees up, engage arms in motion.', 'video' => 'https://www.youtube.com/watch?v=QA6Vn5tV_mY', 'image' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80'],
                    ['name' => 'Forearm Plank Hold', 'sets' => $setsCount, 'reps' => 1, 'duration' => 50 + ($repScale * 5), 'instructions' => 'Keep body straight, squeeze glutes, do not sag hips.', 'video' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80']
                ]
            ],
            6 => [ // Cardio Agility
                'name' => "Saturday Cardio Speed & Agility (Week A)",
                'type' => 'cardio',
                'exercises' => [
                    ['name' => 'Jumping Jacks', 'sets' => $setsCount, 'reps' => 30 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Fast, rhythmic jumping jacks. Focus on light landing.', 'video' => 'https://www.youtube.com/watch?v=7Pxr4xOrhNk', 'image' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80'],
                    ['name' => 'Mountain Climbers', 'sets' => $setsCount, 'reps' => 30 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Sprint in plank position, alternating knees rapidly.', 'video' => 'https://www.youtube.com/watch?v=cnyTQDSE884', 'image' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=800&q=80'],
                    ['name' => 'Plank Jacks', 'sets' => $setsCount, 'reps' => 15 + $repScale, 'duration' => 45, 'instructions' => 'In high plank, jump feet out and in repeatedly.', 'video' => 'https://www.youtube.com/watch?v=eU3P6S2v1Ww', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Bear Crawls', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Crawl on hands/toes with knees close to ground.', 'video' => 'https://www.youtube.com/watch?v=7ZfXGgVsh04', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Russian Twists', 'sets' => $setsCount, 'reps' => 20 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Sit, lean back, raise feet, twist torso left/right.', 'video' => 'https://www.youtube.com/watch?v=Nm0h97Y4uqA', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800']
                ]
            ],
            7 => [ // Rest & Recovery Focus
                'name' => "Sunday Active Rest & Mobility (Week A)",
                'type' => 'recovery',
                'exercises' => [
                    ['name' => 'Cat-Cow Stretch', 'sets' => 2, 'reps' => 10, 'duration' => 60, 'instructions' => 'On all fours, alternate arching and rounding back.', 'video' => 'https://www.youtube.com/watch?v=kqnua4r-UtY', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4'],
                    ['name' => 'Child\'s Pose Hold', 'sets' => 2, 'reps' => 1, 'duration' => 90, 'instructions' => 'Sit back on heels, stretch arms forward, rest forehead.', 'video' => 'https://www.youtube.com/watch?v=qYvZ2uH4Mso', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4'],
                    ['name' => 'Deep Squat Hold', 'sets' => 2, 'reps' => 1, 'duration' => 60, 'instructions' => 'Sit deep in squat, press elbows to knees to open hips.', 'video' => 'https://www.youtube.com/watch?v=M-vC6B0wQ8E', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Cobra Stretch', 'sets' => 2, 'reps' => 5, 'duration' => 60, 'instructions' => 'Lie face down, press hands down to lift chest, stretch abs.', 'video' => 'https://www.youtube.com/watch?v=Ad4b_Vd8VfA', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4'],
                    ['name' => 'Standing Quad Stretch', 'sets' => 2, 'reps' => 5, 'duration' => 60, 'instructions' => 'Pull heel to glute while standing, keep knees close together.', 'video' => 'https://www.youtube.com/watch?v=G3P_4cPhBng', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4']
                ]
            ]
        ];

        $gymPool = [
            1 => [ // Chest & Triceps Focus
                'name' => "Monday Chest & Triceps Power (Week A)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Dumbbell Bench Press', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Lie flat, press dumbbells straight up over chest.', 'video' => 'https://www.youtube.com/watch?v=mTaiQemkEpU', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Incline Dumbbell Press', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Press dumbbells from incline bench. Focus on upper chest.', 'video' => 'https://www.youtube.com/watch?v=8iPjx5UMj4g', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Dumbbell Chest Flyes', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Open arms wide in slight arc, squeeze chest at top.', 'video' => 'https://www.youtube.com/watch?v=eGjt4lk6g34', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Overhead Tricep Extension', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Hold dumbbell overhead with both hands, bend at elbows.', 'video' => 'https://www.youtube.com/watch?v=nRiJVZD5a83', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Bench Tricep Dips', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Lower hips off bench, bend elbows to 90 degrees, push up.', 'video' => 'https://www.youtube.com/watch?v=0326dy_-CzM', 'image' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80']
                ]
            ],
            2 => [ // Back & Biceps Focus
                'name' => "Tuesday Back & Biceps Sculpt (Week A)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Bent-Over Dumbbell Rows', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Hinge forward, pull dumbbells up to ribcage, squeeze back.', 'video' => 'https://www.youtube.com/watch?v=OL8yGrkXyiQ', 'image' => 'https://images.pexels.com/photos/4164761/pexels-photo-4164761.jpeg?w=800'],
                    ['name' => 'Single-Arm Dumbbell Rows', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Row dumbbell to hip with opposite knee/hand on bench.', 'video' => 'https://www.youtube.com/watch?v=dFzUjzsS968', 'image' => 'https://images.pexels.com/photos/4164761/pexels-photo-4164761.jpeg?w=800'],
                    ['name' => 'Dumbbell Bicep Curls', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Curl dumbbells upward, rotate wrists at top of lift.', 'video' => 'https://www.youtube.com/watch?v=ykJmrZ5v0Oo', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Dumbbell Hammer Curls', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Curl dumbbells with palms facing each other throughout.', 'video' => 'https://www.youtube.com/watch?v=zC3nLlEvin4', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Plank Hold (Core Focus)', 'sets' => $setsCount, 'reps' => 1, 'duration' => 60, 'instructions' => 'Hold solid plank, engage abs, keep head neutral.', 'video' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80']
                ]
            ],
            3 => [ // Legs & Calves Focus
                'name' => "Wednesday Legs & Glutes Power (Week A)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Dumbbell Goblet Squats', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Hold dumbbell at chest vertically, squat deep, push up.', 'video' => 'https://www.youtube.com/watch?v=Xjo_fY9Hl9w', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Dumbbell Romanian Deadlifts', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Hinge hips back, slide weights down shins, flat back, squeeze glutes.', 'video' => 'https://www.youtube.com/watch?v=JCXUYtWVF0Y', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Dumbbell Walking Lunges', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Hold weights, step forward, drop back knee, alternate.', 'video' => 'https://www.youtube.com/watch?v=D7KaRcUTQeE', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Weighted Calf Raises', 'sets' => $setsCount, 'reps' => 20 + $repScale, 'duration' => 60, 'instructions' => 'Hold dumbbells, lift heels as high as possible, lower slowly.', 'video' => 'https://www.youtube.com/watch?v=kDXt2XJ4Puw', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Weighted Russian Twists', 'sets' => $setsCount, 'reps' => 20 + $repScale, 'duration' => 60, 'instructions' => 'Hold small dumbbell, twist torso side-to-right, feet up.', 'video' => 'https://www.youtube.com/watch?v=Nm0h97Y4uqA', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800']
                ]
            ],
            4 => [ // Shoulders & Abs Focus
                'name' => "Thursday Shoulder & Abs Builder (Week A)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Dumbbell Shoulder Press', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Press weights straight up overhead from shoulder level.', 'video' => 'https://www.youtube.com/watch?v=qEwKCR5JCog', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Dumbbell Lateral Raises', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Raise arms to sides parallel to floor, lead with elbows.', 'video' => 'https://www.youtube.com/watch?v=3VcKaXatLD0', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Dumbbell Front Raises', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Raise weights in front to shoulder height, keep torso still.', 'video' => 'https://www.youtube.com/watch?v=hRJ61w9IQ9U', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Forearm Plank Hold', 'sets' => $setsCount, 'reps' => 1, 'duration' => 60, 'instructions' => 'Hold forearm plank position, keep core fully engaged.', 'video' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Dumbbell Bicycle Crunches', 'sets' => $setsCount, 'reps' => 16 + $repScale, 'duration' => 60, 'instructions' => 'Standard bicycle crunches. Squeeze core on every twist.', 'video' => 'https://www.youtube.com/watch?v=Iwyvozckjak', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80']
                ]
            ],
            5 => [ // Full Body Conditioning Focus
                'name' => "Friday Full Body Conditioning (Week A)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Dumbbell Thrusters', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Hold weights at shoulders, squat deep, press overhead as you stand.', 'video' => 'https://www.youtube.com/watch?v=qnOikHllwWc', 'image' => 'https://images.pexels.com/photos/5178382/pexels-photo-5178382.jpeg?w=800'],
                    ['name' => 'Dumbbell Kettlebell Swings', 'sets' => $setsCount, 'reps' => 15 + $repScale, 'duration' => 60, 'instructions' => 'Hinge hips, swing dumbbell between legs, drive hips forward.', 'video' => 'https://www.youtube.com/watch?v=S2S6c9vplgU', 'image' => 'https://images.pexels.com/photos/5178382/pexels-photo-5178382.jpeg?w=800'],
                    ['name' => 'Renegade Rows', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Perform row from pushup position on dumbbells, alternate arms.', 'video' => 'https://www.youtube.com/watch?v=U2XhI1J_YJk', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Push Ups', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Standard bodyweight push ups to failure.', 'video' => 'https://www.youtube.com/watch?v=IODxDxX7oi4', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Mountain Climbers', 'sets' => $setsCount, 'reps' => 20 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Fast speed climber steps to spike heart rate.', 'video' => 'https://www.youtube.com/watch?v=cnyTQDSE884', 'image' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=800&q=80']
                ]
            ],
            6 => [ // Core Strength & Balance Focus
                'name' => "Saturday Core Strength & Balance (Week A)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Forearm Plank Hold', 'sets' => $setsCount, 'reps' => 1, 'duration' => 60 + ($repScale * 5), 'instructions' => 'Squeeze core and glutes. Keep body perfectly straight.', 'video' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Side Plank Hold (Left/Right)', 'sets' => $setsCount, 'reps' => 1, 'duration' => 30 + ($repScale * 3), 'instructions' => 'Hold side plank, lift hips high, extend top arm.', 'video' => 'https://www.youtube.com/watch?v=NXr4Fwkuq0Y', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Weighted Bird Dog', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'From all fours, extend opposite arm/leg holding small weight.', 'video' => 'https://www.youtube.com/watch?v=wiF5XMDjsVM', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Weighted Dead Bug', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Slow, controlled dead bugs holding light weights.', 'video' => 'https://www.youtube.com/watch?v=g_BYB0R1bf8', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Weighted Russian Twists', 'sets' => $setsCount, 'reps' => 20 + $repScale, 'duration' => 45, 'instructions' => 'Rotate torso side to side while holding weight.', 'video' => 'https://www.youtube.com/watch?v=Nm0h97Y4uqA', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800']
                ]
            ],
            7 => [ // Rest & Mobility Focus
                'name' => "Sunday Active Rest & Mobility (Week A)",
                'type' => 'recovery',
                'exercises' => [
                    ['name' => 'Cat-Cow Stretch', 'sets' => 2, 'reps' => 10, 'duration' => 60, 'instructions' => 'Arch and hollow spine to release tension.', 'video' => 'https://www.youtube.com/watch?v=kqnua4r-UtY', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4'],
                    ['name' => 'Child\'s Pose Hold', 'sets' => 2, 'reps' => 1, 'duration' => 90, 'instructions' => 'Sit back on heels, relax lower back completely.', 'video' => 'https://www.youtube.com/watch?v=qYvZ2uH4Mso', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4'],
                    ['name' => 'Deep Squat Hold', 'sets' => 2, 'reps' => 1, 'duration' => 60, 'instructions' => 'Hold deep squat to release hips and lower back.', 'video' => 'https://www.youtube.com/watch?v=M-vC6B0wQ8E', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Cobra Stretch', 'sets' => 2, 'reps' => 5, 'duration' => 60, 'instructions' => 'Cobra pose to stretch abdominal wall.', 'video' => 'https://www.youtube.com/watch?v=Ad4b_Vd8VfA', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4'],
                    ['name' => 'Standing Quad Stretch', 'sets' => 2, 'reps' => 5, 'duration' => 60, 'instructions' => 'Stretch quads, stand tall.', 'video' => 'https://www.youtube.com/watch?v=G3P_4cPhBng', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4']
                ]
            ]
        ];
    } else {
        // WEEK B
        $bodyweightPool = [
            1 => [ // Chest, Push & Abs Focus
                'name' => "Monday Push & Abs Burner (Week B)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Diamond Push Ups', 'sets' => $setsCount, 'reps' => 8 + $repScale, 'duration' => 60, 'instructions' => 'Place hands close together in a diamond shape under your chest. Keep body straight and push.', 'video' => 'https://www.youtube.com/watch?v=J0DnG1_S92I', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Decline Push Ups', 'sets' => $setsCount, 'reps' => 8 + $repScale, 'duration' => 60, 'instructions' => 'Elevate your feet on a chair or bench, place hands on floor and push up.', 'video' => 'https://www.youtube.com/watch?v=SKPab2YC8RI', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Plank-to-Pushup', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Start in forearm plank, push up one hand at a time to high plank, lower back down.', 'video' => 'https://www.youtube.com/watch?v=kDFt2XJ4Puw', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Russian Twists', 'sets' => $setsCount, 'reps' => 20 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Lean back slightly, lift feet, twist torso from side to side control.', 'video' => 'https://www.youtube.com/watch?v=Nm0h97Y4uqA', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Flutter Kicks', 'sets' => $setsCount, 'reps' => 25 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Lie on back, hands under hips, kick legs up and down rapidly.', 'video' => 'https://www.youtube.com/watch?v=eU3P6S2v1Ww', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80']
                ]
            ],
            2 => [ // Lower Body / Leg Focus
                'name' => "Tuesday Lower Body Sculpt (Week B)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Plank Walkouts', 'sets' => $setsCount, 'reps' => 8 + $repScale, 'duration' => 60, 'instructions' => 'Stand tall, hinge at hips, walk hands out to high plank, then walk back and stand.', 'video' => 'https://www.youtube.com/watch?v=VfwCQ14soUo', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Superman Holds', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Lie face down, lift chest and thighs off ground, hold for 2s, lower slowly.', 'video' => 'https://www.youtube.com/watch?v=z6PJMT2y8GQ', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Single-Leg Romanian Deadlifts', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Stand on one leg, hinge forward at hips while extending opposite leg straight back.', 'video' => 'https://www.youtube.com/watch?v=5z-hVvPjNkw', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Side Lunges', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Step wide to side, bend knee and sit hips back, keep other leg straight.', 'video' => 'https://www.youtube.com/watch?v=FUX6Pz8vV0s', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Glute Bridge Holds', 'sets' => $setsCount, 'reps' => 1, 'duration' => 45 + ($repScale * 5), 'instructions' => 'Drive through heels to lift hips, squeeze glutes at top, hold position.', 'video' => 'https://www.youtube.com/watch?v=A3nSgJ11j8I', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80']
                ]
            ],
            3 => [ // Cardio Endurance & Agility
                'name' => "Wednesday Metabolic Conditioning (Week B)",
                'type' => 'cardio',
                'exercises' => [
                    ['name' => 'High Knees', 'sets' => $setsCount, 'reps' => 30 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Sprint in place, bringing knees high to chest level.', 'video' => 'https://www.youtube.com/watch?v=QA6Vn5tV_mY', 'image' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80'],
                    ['name' => 'Skater Jumps', 'sets' => $setsCount, 'reps' => 16 + $repScale, 'duration' => 45, 'instructions' => 'Jump sideways from foot to foot, landing softly in a partial squat.', 'video' => 'https://www.youtube.com/watch?v=R9Z8bX6H2lM', 'image' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80'],
                    ['name' => 'Half Burpees', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 45, 'instructions' => 'Squat, jump feet back to plank, jump feet forward, stand up (no pushup/jump).', 'video' => 'https://www.youtube.com/watch?v=dZgVxmf6jkA', 'image' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80'],
                    ['name' => 'Plank Jacks', 'sets' => $setsCount, 'reps' => 16 + $repScale, 'duration' => 45, 'instructions' => 'In forearm plank, jump feet out wide then back together quickly.', 'video' => 'https://www.youtube.com/watch?v=eU3P6S2v1Ww', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Abdominal Sit-Ups', 'sets' => $setsCount, 'reps' => 15 + $repScale, 'duration' => 45, 'instructions' => 'Lie on back, bend knees, sit up fully, engage core.', 'video' => 'https://www.youtube.com/watch?v=jDwoBqPH0nk', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80']
                ]
            ],
            4 => [ // Core Strength & Stability
                'name' => "Thursday Core & Pillar Stability (Week B)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Pike Pushups', 'sets' => $setsCount, 'reps' => 8 + $repScale, 'duration' => 60, 'instructions' => 'Form a V-shape with hips high, lower head toward floor between hands, push up.', 'video' => 'https://www.youtube.com/watch?v=sposDXOI054', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Crab Walk', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Sit, hands behind you, lift hips, walk backward and forward.', 'video' => 'https://www.youtube.com/watch?v=7ZfXGgVsh04', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Hollow Body Hold', 'sets' => $setsCount, 'reps' => 1, 'duration' => 30 + ($repScale * 3), 'instructions' => 'Lie on back, lift shoulder blades and legs off ground, press low back down.', 'video' => 'https://www.youtube.com/watch?v=wiF5XMDjsVM', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'V-Ups', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Lie on back, lift torso and legs together to touch hands to feet.', 'video' => 'https://www.youtube.com/watch?v=7M5W4Z9b4kI', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Side Plank Crunches', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'In side plank, dip hips down and lift back up sequentially.', 'video' => 'https://www.youtube.com/watch?v=NXr4Fwkuq0Y', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800']
                ]
            ],
            5 => [ // Full Body Conditioning Focus
                'name' => "Friday Full Body Conditioning (Week B)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Jumping Jacks', 'sets' => $setsCount, 'reps' => 30 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Jacks with fast pacing.', 'video' => 'https://www.youtube.com/watch?v=7Pxr4xOrhNk', 'image' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80'],
                    ['name' => 'Diamond Push Ups', 'sets' => $setsCount, 'reps' => 8 + $repScale, 'duration' => 60, 'instructions' => 'Hands close, form diamond under chest.', 'video' => 'https://www.youtube.com/watch?v=J0DnG1_S92I', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Reverse Lunges', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Step back, keep weight on front heel.', 'video' => 'https://www.youtube.com/watch?v=O-L4P7Sldf0', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Mountain Climbers', 'sets' => $setsCount, 'reps' => 25 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Drive knees dynamically in a high plank.', 'video' => 'https://www.youtube.com/watch?v=cnyTQDSE884', 'image' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=800&q=80'],
                    ['name' => 'Hollow Body Hold', 'sets' => $setsCount, 'reps' => 1, 'duration' => 30 + ($repScale * 3), 'instructions' => 'Lift arms and legs off ground, press lower back down.', 'video' => 'https://www.youtube.com/watch?v=wiF5XMDjsVM', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800']
                ]
            ],
            6 => [ // Cardio Agility
                'name' => "Saturday Cardio Speed & Agility (Week B)",
                'type' => 'cardio',
                'exercises' => [
                    ['name' => 'Bear Crawl Shoulder Taps', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'On hands and toes, knees bent off ground. Tap alternate shoulders.', 'video' => 'https://www.youtube.com/watch?v=VfwCQ14soUo', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Leg Raises', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Lie on back, raise legs straight to 90 degrees, lower slowly.', 'video' => 'https://www.youtube.com/watch?v=g_BYB0R1bf8', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Russian Twists', 'sets' => $setsCount, 'reps' => 20 + ($repScale * 2), 'duration' => 45, 'instructions' => 'Lean back, twist torso left and right.', 'video' => 'https://www.youtube.com/watch?v=Nm0h97Y4uqA', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Bicycle Crunches', 'sets' => $setsCount, 'reps' => 15 + ($repScale * 2), 'duration' => 60, 'instructions' => 'Alternate elbow to knee with shoulder blades raised.', 'video' => 'https://www.youtube.com/watch?v=Iwyvozckjak', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Forearm Plank Hold', 'sets' => $setsCount, 'reps' => 1, 'duration' => 60 + ($repScale * 5), 'instructions' => 'Keep core tight, body flat.', 'video' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80']
                ]
            ],
            7 => [ // Rest & Recovery Focus
                'name' => "Sunday Active Rest & Mobility (Week B)",
                'type' => 'recovery',
                'exercises' => [
                    ['name' => 'Dynamic Chest Opener', 'sets' => 2, 'reps' => 10, 'duration' => 60, 'instructions' => 'Alternate extending arms wide.', 'video' => 'https://www.youtube.com/watch?v=kqnua4r-UtY', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4'],
                    ['name' => 'Hamstring Stretch', 'sets' => 2, 'reps' => 5, 'duration' => 60, 'instructions' => 'Hinge forward to stretch.', 'video' => 'https://www.youtube.com/watch?v=qYvZ2uH4Mso', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4'],
                    ['name' => 'Calf Stretch', 'sets' => 2, 'reps' => 5, 'duration' => 60, 'instructions' => 'Press heel down back.', 'video' => 'https://www.youtube.com/watch?v=-M4-G8p8fmc', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4'],
                    ['name' => 'Child\'s Pose', 'sets' => 2, 'reps' => 1, 'duration' => 90, 'instructions' => 'Sit back on heels, stretch forward.', 'video' => 'https://www.youtube.com/watch?v=qYvZ2uH4Mso', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4'],
                    ['name' => 'Cobra Stretch', 'sets' => 2, 'reps' => 5, 'duration' => 60, 'instructions' => 'Lift chest up off floor.', 'video' => 'https://www.youtube.com/watch?v=Ad4b_Vd8VfA', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4']
                ]
            ]
        ];

        $gymPool = [
            1 => [ // Chest & Triceps Focus
                'name' => "Monday Chest & Triceps Power (Week B)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Dumbbell Floor Press', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Lie on floor, press dumbbells straight up. Rest triceps on floor at bottom.', 'video' => 'https://www.youtube.com/watch?v=uUGDRwge4F8', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Decline Dumbbell Press', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Press dumbbells from decline bench. Emphasize lower chest.', 'video' => 'https://www.youtube.com/watch?v=LfyQBUKR8ok', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Dumbbell Pullovers', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Hold single dumbbell overhead, lower behind head, pull back over chest.', 'video' => 'https://www.youtube.com/watch?v=hBqqsI0uW8M', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Single-Arm Overhead Tricep Extension', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Hold dumbbell in one hand overhead, bend elbow to lower weight, extend upward.', 'video' => 'https://www.youtube.com/watch?v=nRiJVZD5a83', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Diamond Push Ups', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Hands close together on floor forming a diamond under chest. Lower and push.', 'video' => 'https://www.youtube.com/watch?v=J0DnG1_S92I', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80']
                ]
            ],
            2 => [ // Back & Biceps Focus
                'name' => "Tuesday Back & Biceps Sculpt (Week B)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Dumbbell Deadlifts', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Stand tall, hinge at hips, lower weights down legs, keep back straight, push up.', 'video' => 'https://www.youtube.com/watch?v=JCXUYtWVF0Y', 'image' => 'https://images.pexels.com/photos/4164761/pexels-photo-4164761.jpeg?w=800'],
                    ['name' => 'Dumbbell Incline Rows', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Lie chest down on an incline bench, row dumbbells to ribs.', 'video' => 'https://www.youtube.com/watch?v=OL8yGrkXyiQ', 'image' => 'https://images.pexels.com/photos/4164761/pexels-photo-4164761.jpeg?w=800'],
                    ['name' => 'Dumbbell Hammer Curls (Seated)', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Sit, curl dumbbells with palms facing each other to target brachialis.', 'video' => 'https://www.youtube.com/watch?v=zC3nLlEvin4', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Concentration Curls', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Sit, rest elbow against inner thigh, curl dumbbell slowly.', 'video' => 'https://www.youtube.com/watch?v=Jvj2wV0FBHY', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Superman Holds', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Lie face down, lift chest and thighs, hold back squeeze for 2s.', 'video' => 'https://www.youtube.com/watch?v=z6PJMT2y8GQ', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80']
                ]
            ],
            3 => [ // Legs & Calves Focus
                'name' => "Wednesday Legs & Glutes Power (Week B)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Dumbbell Sumo Squats', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Stand wide, toes pointed out. Hold dumbbell between legs, squat low.', 'video' => 'https://www.youtube.com/watch?v=cl7E4oTsnZc', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Dumbbell Step-Ups', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Hold dumbbells, step up onto a sturdy bench, drive opposite knee up, step down.', 'video' => 'https://www.youtube.com/watch?v=Lq31ifae-k4', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Dumbbell Woodchops', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Hold dumbbell with both hands. Rotate torso to swing weight from hip diagonally overhead.', 'video' => 'https://www.youtube.com/watch?v=zC3nLlEvin4', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Weighted Side Lunges', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Hold dumbbells, step side, squat hips back, push up to center.', 'video' => 'https://www.youtube.com/watch?v=FUX6Pz8vV0s', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Plank Jacks (Core/Leg)', 'sets' => $setsCount, 'reps' => 15 + $repScale, 'duration' => 60, 'instructions' => 'In high plank, jump feet wide and back together repeatedly.', 'video' => 'https://www.youtube.com/watch?v=eU3P6S2v1Ww', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80']
                ]
            ],
            4 => [ // Shoulders & Abs Focus
                'name' => "Thursday Shoulder & Abs Builder (Week B)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Seated Dumbbell Press', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Sit on bench, press dumbbells overhead. Avoid arching back.', 'video' => 'https://www.youtube.com/watch?v=qEwKCR5JCog', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Dumbbell Arnold Press', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Press dumbbells while rotating palms from facing chest to facing forward.', 'video' => 'https://www.youtube.com/watch?v=6V05P1VMDV0', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Dumbbell Rear Delt Flyes', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Hinge at hips, raise weights out to sides to target posterior deltoids.', 'video' => 'https://www.youtube.com/watch?v=ttvfGg9d76c', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Weighted Crunches', 'sets' => $setsCount, 'reps' => 15 + $repScale, 'duration' => 60, 'instructions' => 'Lie down, hold dumbbell on chest, lift shoulders off floor using abs.', 'video' => 'https://www.youtube.com/watch?v=jDwoBqPH0nk', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Hanging Knee Raises', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Hang from pull-up bar, raise knees toward chest, lower controlled.', 'video' => 'https://www.youtube.com/watch?v=wiF5XMDjsVM', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80']
                ]
            ],
            5 => [ // Full Body Conditioning Focus
                'name' => "Friday Full Body Conditioning (Week B)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Dumbbell Manmakers', 'sets' => $setsCount, 'reps' => 8 + $repScale, 'duration' => 60, 'instructions' => 'Pushup on dumbbells, row left then right, jump feet in, clean and thruster.', 'video' => 'https://www.youtube.com/watch?v=qnOikHllwWc', 'image' => 'https://images.pexels.com/photos/5178382/pexels-photo-5178382.jpeg?w=800'],
                    ['name' => 'Dumbbell Snatch', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Pull dumbbell from floor straight overhead in one fluid motion. Alternate arms.', 'video' => 'https://www.youtube.com/watch?v=9520DJiFmv4', 'image' => 'https://images.pexels.com/photos/5178382/pexels-photo-5178382.jpeg?w=800'],
                    ['name' => 'Dumbbell Goblet Squats', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Squat with a single heavy dumbbell held at chest.', 'video' => 'https://www.youtube.com/watch?v=Xjo_fY9Hl9w', 'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80'],
                    ['name' => 'Pushups', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Standard bodyweight pushups to failure.', 'video' => 'https://www.youtube.com/watch?v=IODxDxX7oi4', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Renegade Rows', 'sets' => $setsCount, 'reps' => 10 + $repScale, 'duration' => 60, 'instructions' => 'Row dumbbells from plank position alternately.', 'video' => 'https://www.youtube.com/watch?v=U2XhI1J_YJk', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800']
                ]
            ],
            6 => [ // Core Strength & Balance Focus
                'name' => "Saturday Core Strength & Balance (Week B)",
                'type' => 'strength',
                'exercises' => [
                    ['name' => 'Lying Leg Raises', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Lie on back, keep legs straight and lift them to 90 degrees, lower slowly.', 'video' => 'https://www.youtube.com/watch?v=wiF5XMDjsVM', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Weighted Russian Twists', 'sets' => $setsCount, 'reps' => 20 + $repScale, 'duration' => 45, 'instructions' => 'Twist torso side to side holding a dumbbell.', 'video' => 'https://www.youtube.com/watch?v=Nm0h97Y4uqA', 'image' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Dumbbell Farmer\'s Walk', 'sets' => $setsCount, 'reps' => 2, 'duration' => 60, 'instructions' => 'Hold heavy dumbbells at sides, walk in a straight path with tight posture.', 'video' => 'https://www.youtube.com/watch?v=kDXt2XJ4Puw', 'image' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Hanging Knee Raises', 'sets' => $setsCount, 'reps' => 12 + $repScale, 'duration' => 60, 'instructions' => 'Raise knees toward chest while hanging from bar.', 'video' => 'https://www.youtube.com/watch?v=wiF5XMDjsVM', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80'],
                    ['name' => 'Forearm Plank', 'sets' => $setsCount, 'reps' => 1, 'duration' => 60 + ($repScale * 5), 'instructions' => 'Hold straight posture on forearms, engage abs.', 'video' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw', 'image' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80']
                ]
            ],
            7 => [ // Rest & Mobility Focus
                'name' => "Sunday Active Rest & Mobility (Week B)",
                'type' => 'recovery',
                'exercises' => [
                    ['name' => 'Dynamic Chest Opener', 'sets' => 2, 'reps' => 10, 'duration' => 60, 'instructions' => 'Alternate extending arms wide.', 'video' => 'https://www.youtube.com/watch?v=kqnua4r-UtY', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4'],
                    ['name' => 'Hamstring Stretch', 'sets' => 2, 'reps' => 5, 'duration' => 60, 'instructions' => 'Hinge forward to stretch.', 'video' => 'https://www.youtube.com/watch?v=qYvZ2uH4Mso', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4'],
                    ['name' => 'Calf Stretch', 'sets' => 2, 'reps' => 5, 'duration' => 60, 'instructions' => 'Press heel down back.', 'video' => 'https://www.youtube.com/watch?v=-M4-G8p8fmc', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4'],
                    ['name' => 'Child\'s Pose', 'sets' => 2, 'reps' => 1, 'duration' => 90, 'instructions' => 'Sit back on heels, stretch forward.', 'video' => 'https://www.youtube.com/watch?v=qYvZ2uH4Mso', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4'],
                    ['name' => 'Cobra Stretch', 'sets' => 2, 'reps' => 5, 'duration' => 60, 'instructions' => 'Lift chest up off floor.', 'video' => 'https://www.youtube.com/watch?v=Ad4b_Vd8VfA', 'image' => 'https://images.pexels.com/photos/5510141/pexels-photo-5510141-hd_1080_1920_25fps.mp4']
                ]
            ]
        ];
    }
    
    // Pick active pool based on hasEquipment status
    $activePool = $hasEquipment ? $gymPool : $bodyweightPool;
    $todayWorkout = $activePool[$dayOfWeek];
    
    $workoutName = "{$firstName}'s " . $todayWorkout['name'];
    $workoutType = $todayWorkout['type'];

    $exercises = [];
    foreach ($todayWorkout['exercises'] as $ex) {
        $exercises[] = [
            'name' => $ex['name'],
            'sets' => $ex['sets'],
            'reps' => $ex['reps'],
            'duration' => $ex['duration'],
            'image_url' => $ex['image'],
            'video_url' => $ex['video'],
            'instructions' => $ex['instructions'],
            'search_term' => $ex['name']
        ];
    }
    
    // Build 2 upcoming workouts (using Day+1 and Day+2 split)
    $upcomingWorkouts = [];
    for ($offset = 1; $offset <= 2; $offset++) {
        $nextDayIndex = (($dayOfWeek + $offset - 1) % 7) + 1;
        $nextWorkout = $activePool[$nextDayIndex];
        
        $nextExercises = [];
        foreach ($nextWorkout['exercises'] as $ex) {
            $nextExercises[] = [
                'name' => $ex['name'],
                'sets' => $ex['sets'],
                'reps' => $ex['reps'],
                'duration' => $ex['duration'],
                'image_url' => $ex['image'],
                'video_url' => $ex['video'],
                'instructions' => $ex['instructions'],
                'search_term' => $ex['name']
            ];
        }
        
        $upcomingWorkouts[] = [
            'name' => "{$firstName}'s " . $nextWorkout['name'],
            'scheduled_date' => date('Y-m-d', strtotime("+$offset day")),
            'day_name' => date('l', strtotime("+$offset day")),
            'duration' => count($nextExercises) * 10,
            'exercises_count' => count($nextExercises),
            'exercises' => $nextExercises,
            'type' => $nextWorkout['type'],
            'difficulty' => $level
        ];
    }

    return [
        'todays_workout' => [
            'name' => $workoutName,
            'exercises' => $exercises,
            'exercises_count' => count($exercises),
            'duration' => count($exercises) * 10,
            'difficulty' => $level,
            'type' => $workoutType
        ],
        'upcoming_workouts' => $upcomingWorkouts
    ];
}

function sanitizeAndForceBodyweight(&$workoutData) {
    if (!isset($workoutData['todays_workout']['exercises'])) {
        return;
    }
    
    $bodyweightReplacements = [
        'Jumping Jacks' => [
            'name' => 'Jumping Jacks',
            'video_url' => 'https://www.youtube.com/watch?v=7Pxr4xOrhNk',
            'image_url' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80',
            'instructions' => 'Stand with feet together and arms at sides. Jump and spread legs while swinging arms overhead.'
        ],
        'Bodyweight Squats' => [
            'name' => 'Bodyweight Squats',
            'video_url' => 'https://www.youtube.com/watch?v=aclHkVaku9U',
            'image_url' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80',
            'instructions' => 'Lower your hips down and back as if sitting in a chair, keeping your chest up and weight on your heels.'
        ],
        'Mountain Climbers' => [
            'name' => 'Mountain Climbers',
            'video_url' => 'https://www.youtube.com/watch?v=cnyTQDSE884',
            'image_url' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=800&q=80',
            'instructions' => 'Start in a plank position. Alternately drive your knees toward your chest as fast as possible.'
        ],
        'Plank Hold' => [
            'name' => 'Plank Hold',
            'video_url' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw',
            'image_url' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80',
            'instructions' => 'Hold a forearm plank position with a straight back and core fully engaged.'
        ],
        'Push Ups' => [
            'name' => 'Push Ups',
            'video_url' => 'https://www.youtube.com/watch?v=IODxDxX7oi4',
            'image_url' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80',
            'instructions' => 'Keep your body in a straight line, lower your chest close to the floor, and push back up.'
        ],
        'Plank Shoulder Taps' => [
            'name' => 'Plank Shoulder Taps',
            'video_url' => 'https://www.youtube.com/watch?v=VfwCQ14soUo',
            'image_url' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800',
            'instructions' => 'In a high plank position, tap your left shoulder with your right hand, then tap your right shoulder with your left hand.'
        ]
    ];
    
    $equipmentKeywords = ['dumbbell', 'barbell', 'kettlebell', 'bench press', 'cable', 'machine', 'weight', 'curl', 'row', 'fly', 'thruster', 'smith', 'lat pulldown', 'leg press', 'leg extension', 'leg curl'];
    
    // Sanitize Today's Workout Exercises
    foreach ($workoutData['todays_workout']['exercises'] as $idx => &$exercise) {
        $nameLower = strtolower($exercise['name'] ?? '');
        $hasEquipmentInExercise = false;
        foreach ($equipmentKeywords as $kw) {
            if (strpos($nameLower, $kw) !== false) {
                $hasEquipmentInExercise = true;
                break;
            }
        }
        
        if ($hasEquipmentInExercise) {
            $replacement = $bodyweightReplacements['Push Ups']; // default
            
            if (strpos($nameLower, 'squat') !== false || strpos($nameLower, 'leg') !== false || strpos($nameLower, 'lunge') !== false) {
                $replacement = $bodyweightReplacements['Bodyweight Squats'];
            } elseif (strpos($nameLower, 'row') !== false || strpos($nameLower, 'pull') !== false || strpos($nameLower, 'deadlift') !== false || strpos($nameLower, 'back') !== false) {
                $replacement = $bodyweightReplacements['Plank Shoulder Taps'];
            } elseif (strpos($nameLower, 'cardio') !== false || strpos($nameLower, 'jump') !== false || strpos($nameLower, 'thruster') !== false) {
                $replacement = $bodyweightReplacements['Jumping Jacks'];
            }
            
            $exercise['name'] = $replacement['name'];
            $exercise['video_url'] = $replacement['video_url'];
            $exercise['image_url'] = $replacement['image_url'];
            $exercise['instructions'] = $replacement['instructions'];
            if (isset($exercise['search_term'])) {
                $exercise['search_term'] = $replacement['name'];
            }
        }
    }
    
    // Sanitize Upcoming Workouts
    if (isset($workoutData['upcoming_workouts']) && is_array($workoutData['upcoming_workouts'])) {
        foreach ($workoutData['upcoming_workouts'] as &$upcoming) {
            if (isset($upcoming['exercises']) && is_array($upcoming['exercises'])) {
                foreach ($upcoming['exercises'] as &$exercise) {
                    $nameLower = strtolower($exercise['name'] ?? '');
                    $hasEquipmentInExercise = false;
                    foreach ($equipmentKeywords as $kw) {
                        if (strpos($nameLower, $kw) !== false) {
                            $hasEquipmentInExercise = true;
                            break;
                        }
                    }
                    
                    if ($hasEquipmentInExercise) {
                        $replacement = $bodyweightReplacements['Push Ups'];
                        
                        if (strpos($nameLower, 'squat') !== false || strpos($nameLower, 'leg') !== false || strpos($nameLower, 'lunge') !== false) {
                            $replacement = $bodyweightReplacements['Bodyweight Squats'];
                        } elseif (strpos($nameLower, 'row') !== false || strpos($nameLower, 'pull') !== false || strpos($nameLower, 'deadlift') !== false || strpos($nameLower, 'back') !== false) {
                            $replacement = $bodyweightReplacements['Plank Shoulder Taps'];
                        } elseif (strpos($nameLower, 'cardio') !== false || strpos($nameLower, 'jump') !== false || strpos($nameLower, 'thruster') !== false) {
                            $replacement = $bodyweightReplacements['Jumping Jacks'];
                        }
                        
                        $exercise['name'] = $replacement['name'];
                        $exercise['video_url'] = $replacement['video_url'];
                        $exercise['image_url'] = $replacement['image_url'];
                        if (isset($exercise['instructions'])) {
                            $exercise['instructions'] = $replacement['instructions'];
                        }
                        if (isset($exercise['search_term'])) {
                            $exercise['search_term'] = $replacement['name'];
                        }
                    }
                }
            }
        }
    }
}

function callGemini($prompt, $apiKey, $primaryModel = 'gemini-1.5-pro-latest', $temp = 0.7) {
    $logFile = __DIR__ . '/gemini_debug.log';
    
    // Limit fallbacks to avoid excessive latency (max 3 models)
    $models = array_unique([$primaryModel, 'gemini-2.5-flash', 'gemini-1.5-flash']);
    
    foreach ($models as $modelName) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelName . ':generateContent?key=' . $apiKey;
        
        $data = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => $temp,
                'maxOutputTokens' => 2048,
                'response_mime_type' => 'application/json'
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Reduced timeout to prevent hangs
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
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

        // Abort fallback loop immediately if API key itself is blocked or invalid
        if ($httpCode === 403 || $httpCode === 401) {
            break;
        }
        
        usleep(200000); 
    }

    throw new Exception('All Gemini models failed or were throttled.');
}

function callHuggingFace(string $prompt, string $hfToken, string $model = 'google/gemma-3-4b-it'): string {
    if (empty($hfToken)) {
        throw new Exception("Hugging Face API token is not configured.");
    }

    $payload = json_encode([
        'model'       => $model,
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'max_tokens'  => 1500,
        'temperature' => 0.7,
    ]);

    $ch = curl_init('https://router.huggingface.co/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT,        60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $hfToken
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Hugging Face API Error (HTTP $httpCode): " . $response . " | " . $curlError);
    }

    $result = json_decode($response, true);
    $text   = $result['choices'][0]['message']['content'] ?? '';

    if (empty($text)) {
        throw new Exception("Unexpected response format from Hugging Face: " . $response);
    }

    // Strip markdown code fences
    $text = preg_replace('/^```json\s*/i', '', trim($text));
    $text = preg_replace('/```\s*$/i',     '', trim($text));
    return trim($text);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? null;
    
    // Self-Heal Database Schema (Ensure ai_engine column exists)
    try {
        $pdo->exec("ALTER TABLE user_profiles ADD COLUMN ai_engine VARCHAR(20) DEFAULT 'eco'");
    } catch (PDOException $e) {
        // Already exists
    }
    
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
    
    // Active AI Engine setting, managed solely by the Admin in the dashboard (defaults to Eco)
    $activeEngine = $profile['ai_engine'] ?? 'eco';
    $ecoMode = ($activeEngine === 'eco');
    
    // Dynamically force AI-generated workouts for Premium and Advanced Premium users to deliver full value!
    $tier = strtolower($profile['subscription_tier'] ?? 'free');
    if ($tier === 'premium' || $tier === 'advanced_premium') {
        $ecoMode = false;
    }
    $isTrialSession = false;

    if (!$ecoMode) {
        // Enforce paywall gatekeeper subscription check (unsubscribed users get exactly ONE free trial)
        $access = AISubscriptionGate::verifyAccess($pdo, $userId, 'premium', 'Premium AI Coach Workouts');
        if ($access['is_trial']) {
            $isTrialSession = true;
        }
    }
    
    // Get workout history (last 30 days for AI context)
    $historyStmt = $pdo->prepare("
        SELECT COUNT(*) as total_workouts,
               MAX(completed_date) as last_workout
        FROM workout_logs
        WHERE user_id = ? AND completed_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $historyStmt->execute([$userId]);
    $history = $historyStmt->fetch(PDO::FETCH_ASSOC);

    // Get THIS WEEK's workout count (Monday to Sunday)
    $weeklyStmt = $pdo->prepare("
        SELECT COUNT(*) as week_workouts
        FROM workout_logs
        WHERE user_id = ? AND YEARWEEK(completed_date, 1) = YEARWEEK(CURDATE(), 1)
    ");
    $weeklyStmt->execute([$userId]);
    $weeklyHistory = $weeklyStmt->fetch(PDO::FETCH_ASSOC);
    $weeklyCompleted = (int)($weeklyHistory['week_workouts'] ?? 0);

    // Get nutrition context for today
    $nutritionStmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(calories), 0) as total_calories,
            COALESCE(SUM(protein), 0) as total_protein,
            COALESCE(SUM(carbs), 0) as total_carbs,
            COALESCE(SUM(fats), 0) as total_fats
        FROM nutrition_logs
        WHERE user_id = ? AND logged_date = CURDATE()
    ");
    $nutritionStmt->execute([$userId]);
    $nutrition = $nutritionStmt->fetch(PDO::FETCH_ASSOC);
    $caloriesConsumed = intval($nutrition['total_calories']);
    $calorieGoal = intval($profile['daily_calorie_goal'] ?? 2000);

    // Fetch Today's Detailed Meals
    $mealsStmt = $pdo->prepare("SELECT meal_name, calories, protein, carbs, fats FROM nutrition_logs WHERE user_id = ? AND logged_date = CURDATE() ORDER BY created_at DESC LIMIT 5");
    $mealsStmt->execute([$userId]);
    $todayMeals = $mealsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Recent Workout History (God Mode Intelligence)
    $workoutHistoryStmt = $pdo->prepare("SELECT workout_name, duration_minutes, calories_burned, completed_date FROM workout_logs WHERE user_id = ? ORDER BY completed_date DESC LIMIT 3");
    $workoutHistoryStmt->execute([$userId]);
    $recentPerformance = $workoutHistoryStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch recently generated exercises to avoid repetitions
    $recentExercisesList = [];
    try {
        $recentPlansStmt = $pdo->prepare("
            SELECT plan_data FROM workout_plans
            WHERE user_id = ?
            ORDER BY plan_date DESC, id DESC
            LIMIT 5
        ");
        $recentPlansStmt->execute([$userId]);
        $recentPlans = $recentPlansStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($recentPlans as $p) {
            $data = json_decode($p['plan_data'], true);
            if (isset($data['todays_workout']['exercises'])) {
                foreach ($data['todays_workout']['exercises'] as $ex) {
                    if (isset($ex['name'])) {
                        $recentExercisesList[] = trim($ex['name']);
                    }
                }
            }
        }
        $recentExercisesList = array_unique($recentExercisesList);
    } catch (Exception $e) {
        error_log("Failed to fetch recent exercises for variety check: " . $e->getMessage());
    }

    // Get weight change (last 2 records)
    $weightStmt = $pdo->prepare("
        SELECT weight FROM weight_history 
        WHERE user_id = ? 
        ORDER BY recorded_date DESC LIMIT 2
    ");
    $weightStmt->execute([$userId]);
    $weights = $weightStmt->fetchAll(PDO::FETCH_COLUMN);
    $weightTrend = "stable";
    if (count($weights) >= 2) {
        if ($weights[0] > $weights[1]) $weightTrend = "increasing";
        else if ($weights[0] < $weights[1]) $weightTrend = "decreasing";
    }
    
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
        $hasEquipment = (bool)($profile['has_equipment'] ?? true);
        if (!$hasEquipment) {
            sanitizeAndForceBodyweight($workoutData);
        }
        $ai_provider = 'Database (Previously Generated)';
        goto finalize_response; // Skip AI generation
    }

    // 2. ECO MODE BYPASS: If in Eco Smart Engine mode, bypass AI generation entirely
    if ($ecoMode) {
        $workoutData = getLocalSmartWorkout($pdo, $userId, $profile, $hasEquipment, $CATEGORY_FALLBACK_VIDEOS);
        $ai_provider = 'Fitrova Smart Engine (Eco Mode)';
        goto finalize_response;
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
    
    // 2. Build Compressed AI prompt
    $prompt = $SYSTEM_PROMPT . "\n\n";
    $prompt .= "Profile: " . ($profile['first_name'] ?? 'User') . " | Goal: " . ($profile['fitness_goal'] ?? 'fitness') . " | Level: " . ($profile['activity_level'] ?? 'moderate') . " | Equipment: " . ($hasEquipment ? "Gym" : "None (Bodyweight Only)") . " | Age: " . ($profile['age'] ?? 'not specified') . "\n";
    $prompt .= "Stats: Workouts (30d): " . ($history['total_workouts'] ?? 0) . " | Days since last: " . $daysSince . " | Calories Today: " . $caloriesConsumed . "/" . $calorieGoal . " kcal | Trend: " . $weightTrend . "\n";

    if (!empty($todayMeals)) {
        $mealSummaries = [];
        foreach ($todayMeals as $m) {
            $mealSummaries[] = $m['meal_name'] . " (" . $m['calories'] . "kcal)";
        }
        $prompt .= "Meals Today: " . implode(', ', $mealSummaries) . "\n";
    }

    if (!empty($recentPerformance)) {
        $perfSummaries = [];
        foreach ($recentPerformance as $w) {
            $perfSummaries[] = $w['workout_name'] . " (" . $w['duration_minutes'] . "m, " . $w['calories_burned'] . "kcal)";
        }
        $prompt .= "Recent Workouts: " . implode(', ', $perfSummaries) . "\n";
    }

    if ($caloriesConsumed > $calorieGoal) {
        $prompt .= "Calorie Surplus: +" . ($caloriesConsumed - $calorieGoal) . " kcal. GOAL: Increase intensity/cardio.\n";
    } else if ($weightTrend === "increasing") {
        $prompt .= "Weight Trend Up. GOAL: Boost metabolism.\n";
    }

    if (!empty($recentExercisesList)) {
        $prompt .= "Recently performed exercises: " . implode(', ', $recentExercisesList) . ".\n";
        $prompt .= "VARIETY & PROGRESSION: To promote progression and prevent boredom, do NOT include any of these recently performed exercises in today's workout plan or the upcoming workouts. Select different exercises or different variations targeting the same muscle groups. The goal is progressive overload and muscle growth, not repeating content.\n";
    } else {
        $prompt .= "VARIETY & PROGRESSION: Design a workout with good variety. Ensure it focuses on muscle growth and progression, avoiding repeating the exact same sequence of exercises.\n";
    }

    if (!$hasEquipment) {
        $prompt .= "NO EQUIPMENT: Bodyweight exercises ONLY. No machinery/weights.\n";
    }
    if ($isBeginner) {
        $prompt .= "BEGINNER: Safe, easy exercises. search_term = slow instructional video (e.g. 'pushups tutorial'). instructions = 3-4 numbered steps.\n";
    } else {
        $prompt .= "ADVANCED: Standard exercise names. search_term = standard name. instructions = 1-2 cues.\n";
    }

    $prompt .= "CRITICAL: You MUST include exactly 5 high-quality, relevant exercises in todays_workout.exercises. Do not generate 3 or 4; there must be exactly 5 exercises. Do NOT generate the 'exercises' key/array for upcoming_workouts (it will be auto-populated by the backend).\n";

    $prompt .= "\nFormat JSON:\n";
    $prompt .= "{\n";
    $prompt .= '  "todays_workout": {"name": "Title", "exercises": [{"name": "Name", "search_term": "query", "sets": 3, "reps": 10, "instructions": "cues"}], "exercises_count": 5, "duration": 50, "difficulty": "beginner", "type": "strength"},';
    $prompt .= '  "recovery_score": 90, "status": "READY FOR SESSION", "missed_workouts": [], "upcoming_workouts": [{"name": "Upper Body Power", "scheduled_date": "YYYY-MM-DD", "duration": 45, "exercises_count": 5}]';
    $prompt .= "}\n";
    
    // Primary AI Generation: DeepSeek -> Gemini -> Hugging Face fallback
    $ai_provider = 'Fitrova Smart Engine';
    $workoutData = null;

    if (!empty($DEEPSEEK_API_KEY)) {
        try {
            $deepseekModel = (strpos($PRIMARY_MODEL, 'deepseek') !== false) ? $PRIMARY_MODEL : 'deepseek-chat';
            $dsResponse = callDeepSeek($prompt, $DEEPSEEK_API_KEY, $SYSTEM_PROMPT, $AI_TEMPERATURE, $deepseekModel);
            $parsedData = json_decode($dsResponse, true);
            if ($parsedData && isset($parsedData['todays_workout'])) {
                $workoutData = $parsedData;
                $ai_provider = 'DeepSeek AI';
            }
        } catch (Exception $dsEx) {
            error_log("DeepSeek API call failed: " . $dsEx->getMessage() . ". Falling back to Gemini...");
        }
    }

    if (!$workoutData || !isset($workoutData['todays_workout'])) {
        try {
            $aiResponse = callGemini($prompt, $GEMINI_API_KEY, 'gemini-1.5-flash', $AI_TEMPERATURE);
            
            $aiResponse = preg_replace('/```json\s*/i', '', $aiResponse);
            $aiResponse = preg_replace('/```\s*$/', '', $aiResponse);
            $aiResponse = trim($aiResponse);
            $parsedData = json_decode($aiResponse, true);
            if ($parsedData && isset($parsedData['todays_workout'])) {
                $workoutData = $parsedData;
                $ai_provider = 'Google Gemini Pro';
            }
        } catch (Exception $e) {
            error_log("Gemini API call failed: " . $e->getMessage() . ". Attempting Hugging Face Serverless fallback...");
            
            // Load HF token
            $hfToken = ($settings['hf_token'] ?? '') ?: (getenv('HF_TOKEN') ?: '');
            if (!empty($hfToken)) {
                try {
                    $hfResponse = callGemma3($prompt, $hfToken, 800);
                    $hfResponse = preg_replace('/```json\s*/i', '', $hfResponse);
                    $hfResponse = preg_replace('/```\s*$/', '', $hfResponse);
                    $hfResponse = trim($hfResponse);
                    $parsedData = json_decode($hfResponse, true);
                    if ($parsedData && isset($parsedData['todays_workout'])) {
                        $workoutData = $parsedData;
                        $ai_provider = 'Hugging Face (Gemma 3 4B)';
                    } else {
                        $workoutData = ['error' => true, 'message' => 'Hugging Face returned invalid JSON data.'];
                    }
                } catch (Exception $hfEx) {
                    error_log("Hugging Face fallback failed: " . $hfEx->getMessage());
                    $workoutData = ['error' => true, 'message' => $hfEx->getMessage()];
                }
            } else {
                $workoutData = ['error' => true, 'message' => 'Hugging Face token not configured.'];
            }
        }
    }

    // SMART VIDEO MATCHING - Uses curated library + AI-Driven YouTube Search
    if ($workoutData && isset($workoutData['todays_workout']['exercises'])) {
        $workoutType = $workoutData['todays_workout']['type'] ?? 'general';

        foreach ($workoutData['todays_workout']['exercises'] as $idx => &$exercise) {
            $exerciseName = $exercise['name'] ?? 'general exercise';
            $searchTerm = $exercise['search_term'] ?? $exerciseName;
            
            // Check curated mapping first, fall back to dynamic YouTube API search if not found
            $media = findExerciseVideo($exerciseName, $searchTerm, $EXERCISE_VIDEO_MAP, $CATEGORY_FALLBACK_VIDEOS, $YOUTUBE_API_KEY, $workoutType, false);
            $exercise['video_url'] = $media['video'];
            $exercise['image_url'] = $media['image'];

            // Register this dynamic AI content inside the global Exercise Library!
            saveExerciseToLibrary($pdo, $exercise, $workoutType, $workoutData['todays_workout']['difficulty'] ?? 'intermediate');
        }
    }

    // 1. AUTO-HEALING: If AI fails or returns malformed data, use the high-quality backup generator
    if (!$workoutData || isset($workoutData['error']) || !isset($workoutData['todays_workout'])) {
        error_log("⚠️ AI generation error or malformed data, fallback to local smart generator.");
        $workoutData = getLocalSmartWorkout($pdo, $userId, $profile, $hasEquipment, $CATEGORY_FALLBACK_VIDEOS);
        $ai_provider = 'Fitrova Smart Engine (Backup)';
    } else {
        // Keep the dynamically assigned provider (Gemini or Hugging Face)
        
        // SAVE NEW PLAN TO DATABASE
        try {
            $saveStmt = $pdo->prepare("
                INSERT INTO workout_plans (user_id, name, workout_type, duration_minutes, plan_data, plan_date, is_active)
                VALUES (?, ?, ?, ?, ?, CURDATE(), 1)
            ");
            $saveStmt->execute([
                $userId,
                $workoutData['todays_workout']['name'] ?? 'Daily Workout',
                $workoutData['todays_workout']['type'] ?? 'mixed',
                intval($workoutData['todays_workout']['duration'] ?? 45),
                json_encode($workoutData)
            ]);

            // If this was generated as their one-off free trial session, mark trial as consumed!
            if ($isTrialSession) {
                AISubscriptionGate::consumeTrial($pdo, $userId);
            }
        } catch (Exception $e) {
            error_log("Failed to save workout plan: " . $e->getMessage());
        }
    }

finalize_response:
    $hasEquipment = (bool)($profile['has_equipment'] ?? true);
    if (!$hasEquipment) {
        sanitizeAndForceBodyweight($workoutData);
    }

    // Auto-register today's exercises in the dynamic database library (handles loaded cached plans)
    if (isset($workoutData['todays_workout']['exercises']) && is_array($workoutData['todays_workout']['exercises'])) {
        $workoutType = $workoutData['todays_workout']['type'] ?? 'strength';
        $difficulty = $workoutData['todays_workout']['difficulty'] ?? 'intermediate';
        foreach ($workoutData['todays_workout']['exercises'] as $ex) {
            saveExerciseToLibrary($pdo, $ex, $workoutType, $difficulty);
        }
    }

    // Insert dynamic workout status as an AI insight notification
    if (isset($workoutData['status']) && !empty($workoutData['status'])) {
        // Check if this notification already exists for today to avoid spamming
        $checkInsight = $pdo->prepare("
            SELECT id FROM ai_insights 
            WHERE user_id = ? AND insight_text = ? AND created_at >= CURDATE()
        ");
        $checkInsight->execute([$userId, $workoutData['status']]);
        if (!$checkInsight->fetch()) {
            $insightStmt = $pdo->prepare("
                INSERT INTO ai_insights (user_id, insight_text, insight_type, is_read)
                VALUES (?, ?, 'tip', FALSE)
            ");
            $insightStmt->execute([$userId, $workoutData['status']]);
        }
    }

    // 2. MANDATORY METADATA ENRICHMENT (Ensures frontend fields like 'completed' never crash)
    // Weekly goal: count only workouts done this calendar week (Mon-Sun), capped at goal (4)
    $goalPerWeek = 4;
    $workoutData['weekly_progress'] = [
        'completed' => min($weeklyCompleted, $goalPerWeek),
        'goal'      => $goalPerWeek
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
                    ['name' => 'Bird Dog', 'sets' => 3, 'reps' => 12, 'duration' => 60],
                    ['name' => 'Dead Bug', 'sets' => 3, 'reps' => 12, 'duration' => 60],
                    ['name' => 'Side Plank', 'sets' => 3, 'reps' => 30, 'duration' => 60],
                ];
            }
            
            // Match videos for upcoming exercises too
            foreach ($upcoming['exercises'] as &$ex) {
                // Check curated mapping first, fall back to dynamic YouTube API search if not found
                $m = findExerciseVideo($ex['name'], $ex['search_term'] ?? $ex['name'], $EXERCISE_VIDEO_MAP, $CATEGORY_FALLBACK_VIDEOS, $YOUTUBE_API_KEY, 'general', false);
                $ex['video_url'] = $m['video'];
                $ex['image_url'] = $m['image'];

                // Register this dynamic AI content inside the global Exercise Library!
                saveExerciseToLibrary($pdo, $ex, $upcoming['type'] ?? 'general', $upcoming['difficulty'] ?? 'intermediate');
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
                'exercises_count' => 5,
                'exercises' => $hasEquipment ? [
                    ['name' => 'Push Ups', 'sets' => 3, 'reps' => 15, 'video_url' => 'https://www.youtube.com/watch?v=IODxDxX7oi4', 'image_url' => 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800'],
                    ['name' => 'Dumbbell Rows', 'sets' => 3, 'reps' => 12, 'video_url' => 'https://www.youtube.com/watch?v=OL8yGrkXyiQ', 'image_url' => 'https://images.pexels.com/photos/4164761/pexels-photo-4164761.jpeg?w=800'],
                    ['name' => 'Dumbbell Chest Press', 'sets' => 3, 'reps' => 10, 'video_url' => 'https://www.youtube.com/watch?v=mTaiQemkEpU', 'image_url' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Dumbbell Bicep Curls', 'sets' => 3, 'reps' => 12, 'video_url' => 'https://www.youtube.com/watch?v=ykJmrZ5v0Oo', 'image_url' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Dumbbell Lateral Raises', 'sets' => 3, 'reps' => 15, 'video_url' => 'https://www.youtube.com/watch?v=3VcKaXatLD0', 'image_url' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800']
                ] : [
                    ['name' => 'Push Ups', 'sets' => 3, 'reps' => 15, 'video_url' => 'https://www.youtube.com/watch?v=IODxDxX7oi4', 'image_url' => 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800'],
                    ['name' => 'Mountain Climbers', 'sets' => 3, 'reps' => 20, 'video_url' => 'https://www.youtube.com/watch?v=cnyTQDSE884', 'image_url' => 'https://images.pexels.com/photos/5178382/pexels-photo-5178382.jpeg?w=800'],
                    ['name' => 'Plank Shoulder Taps', 'sets' => 3, 'reps' => 15, 'video_url' => 'https://www.youtube.com/watch?v=VfwCQ14soUo', 'image_url' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Tricep Dips', 'sets' => 3, 'reps' => 12, 'video_url' => 'https://www.youtube.com/watch?v=0326dy_-CzM', 'image_url' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800'],
                    ['name' => 'Bear Crawls', 'sets' => 3, 'reps' => 10, 'video_url' => 'https://www.youtube.com/watch?v=7ZfXGgVsh04', 'image_url' => 'https://images.pexels.com/photos/3838937/pexels-photo-3838937.jpeg?w=800']
                ]
            ],
            [
                'name' => 'Core and Stability',
                'scheduled_date' => date('Y-m-d', strtotime('+2 days')),
                'day_name' => date('l', strtotime('+2 days')),
                'duration' => 30,
                'exercises_count' => 5,
                'exercises' => [
                    ['name' => 'Forearm Plank', 'sets' => 3, 'reps' => 60, 'video_url' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw', 'image_url' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Russian Twists', 'sets' => 3, 'reps' => 20, 'video_url' => 'https://www.youtube.com/watch?v=Nm0h97Y4uqA', 'image_url' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Bird Dog', 'sets' => 3, 'reps' => 12, 'video_url' => 'https://www.youtube.com/watch?v=wiF5XMDjsVM', 'image_url' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Dead Bug', 'sets' => 3, 'reps' => 12, 'video_url' => 'https://www.youtube.com/watch?v=g_BYB0R1bf8', 'image_url' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800'],
                    ['name' => 'Side Plank', 'sets' => 3, 'reps' => 30, 'video_url' => 'https://www.youtube.com/watch?v=NXr4Fwkuq0Y', 'image_url' => 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800']
                ]
            ]
        ];
    }
    
    // 4. SEND CLEAN RESPONSE
    $workoutData['ai_provider'] = $ai_provider;
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
