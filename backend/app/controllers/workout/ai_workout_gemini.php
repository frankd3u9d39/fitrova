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

$PRIMARY_MODEL = $settings['ai_model_primary'] ?? 'gemini-3.1-flash';
$SYSTEM_PROMPT = $settings['ai_system_prompt'] ?? 'You are a professional fitness trainer. Generate a personalized workout plan.';
$AI_TEMPERATURE = (float)($settings['ai_temperature'] ?? 0.7);
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

function getLocalSmartWorkout($profile, $hasEquipment, $categoryFallbacks) {
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
    
    // Choose workout type and difficulty
    $workoutType = 'strength';
    $workoutName = "{$firstName}'s Strength & Tone Starter";
    
    if (strpos($goal, 'lose') !== false || strpos($goal, 'weight') !== false) {
        $workoutType = 'cardio';
        $workoutName = "{$firstName}'s High-Intensity Metabolic Burner";
    } elseif (strpos($goal, 'muscle') !== false || strpos($goal, 'gain') !== false) {
        $workoutType = 'strength';
        $workoutName = "{$firstName}'s Hypertrophy Muscle Builder";
    } elseif (strpos($goal, 'endurance') !== false || strpos($goal, 'cardio') !== false) {
        $workoutType = 'cardio';
        $workoutName = "{$firstName}'s Aerobic Endurance Conditioning";
    } elseif (strpos($goal, 'health') !== false || strpos($goal, 'general') !== false) {
        $workoutType = 'Full Body';
        $workoutName = "{$firstName}'s Foundational Health & Tone";
    }
    
    $exercises = [];
    if (!$hasEquipment) {
        // Bodyweight Exercises
        if ($workoutType === 'cardio') {
            $exercises = [
                [
                    'name' => 'Jumping Jacks',
                    'sets' => 3, 'reps' => 25, 'duration' => 45,
                    'image_url' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=7Pxr4xOrhNk',
                    'instructions' => 'Stand with feet together and arms at sides. Jump and spread legs while swinging arms overhead.'
                ],
                [
                    'name' => 'Bodyweight Squats',
                    'sets' => 3, 'reps' => 15, 'duration' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=aclHkVaku9U',
                    'instructions' => 'Lower your hips down and back as if sitting in a chair, keeping your chest up and weight on your heels.'
                ],
                [
                    'name' => 'Mountain Climbers',
                    'sets' => 3, 'reps' => 30, 'duration' => 45,
                    'image_url' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=cnyTQDSE884',
                    'instructions' => 'Start in a plank position. Alternately drive your knees toward your chest as fast as possible.'
                ],
                [
                    'name' => 'Plank Hold',
                    'sets' => 3, 'reps' => 1, 'duration' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw',
                    'instructions' => 'Hold a forearm plank position with a straight back and core fully engaged.'
                ],
                [
                    'name' => 'High Knees',
                    'sets' => 3, 'reps' => 40, 'duration' => 45,
                    'image_url' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=QA6Vn5tV_mY',
                    'instructions' => 'Run in place, bringing knees up high to chest level while keeping core engaged.'
                ]
            ];
        } else {
            $exercises = [
                [
                    'name' => 'Push Ups',
                    'sets' => 3, 'reps' => 12, 'duration' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=IODxDxX7oi4',
                    'instructions' => 'Keep your body in a straight line, lower your chest close to the floor, and push back up.'
                ],
                [
                    'name' => 'Bodyweight Squats',
                    'sets' => 3, 'reps' => 15, 'duration' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=aclHkVaku9U',
                    'instructions' => 'Lower your hips down and back as if sitting in a chair, keeping your chest up and weight on your heels.'
                ],
                [
                    'name' => 'Mountain Climbers',
                    'sets' => 3, 'reps' => 20, 'duration' => 45,
                    'image_url' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=cnyTQDSE884',
                    'instructions' => 'Start in a plank position. Alternately drive your knees toward your chest as fast as possible.'
                ],
                [
                    'name' => 'Plank Hold',
                    'sets' => 3, 'reps' => 1, 'duration' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw',
                    'instructions' => 'Hold a forearm plank position with a straight back and core fully engaged.'
                ],
                [
                    'name' => 'Burpees',
                    'sets' => 3, 'reps' => 10, 'duration' => 45,
                    'image_url' => 'https://images.unsplash.com/photo-1601422407692-ec4eeec1d9b3?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=dZgVxmf6jkA',
                    'instructions' => 'Drop to a squat, kick feet back, do a pushup, jump feet forward, and explosively jump up.'
                ]
            ];
        }
    } else {
        // Equipment (Gym/Dumbbell) Exercises
        if ($workoutType === 'cardio') {
            $exercises = [
                [
                    'name' => 'Dumbbell Goblet Squats',
                    'sets' => 3, 'reps' => 12, 'duration' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=Xjo_fY9Hl9w',
                    'instructions' => 'Hold a dumbbell vertically at your chest and perform a deep squat, keeping your chest proud.'
                ],
                [
                    'name' => 'Dumbbell Thrusters',
                    'sets' => 3, 'reps' => 10, 'duration' => 45,
                    'image_url' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=qnOikHllwWc',
                    'instructions' => 'Hold dumbbells at shoulders, squat down, and explosively press them overhead as you stand.'
                ],
                [
                    'name' => 'Dumbbell Rows',
                    'sets' => 3, 'reps' => 12, 'duration' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=OL8yGrkXyiQ',
                    'instructions' => 'Bend at the hips, keep your back flat, and pull dumbbells up to your ribcage.'
                ],
                [
                    'name' => 'Plank Hold',
                    'sets' => 3, 'reps' => 1, 'duration' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw',
                    'instructions' => 'Hold a forearm plank position with a straight back and core fully engaged.'
                ],
                [
                    'name' => 'Dumbbell Kettlebell Swings',
                    'sets' => 3, 'reps' => 15, 'duration' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=S2S6c9vplgU',
                    'instructions' => 'Hinge at the hips, swing the dumbbell between your legs, and drive hips forward to swing it to shoulder height.'
                ]
            ];
        } else {
            $exercises = [
                [
                    'name' => 'Dumbbell Chest Press',
                    'sets' => 3, 'reps' => 10, 'duration' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=mTaiQemkEpU',
                    'instructions' => 'Lie flat on a bench, grip dumbbells at chest level, and press them straight up over your chest.'
                ],
                [
                    'name' => 'Dumbbell Goblet Squats',
                    'sets' => 3, 'reps' => 12, 'duration' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2158?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=Xjo_fY9Hl9w',
                    'instructions' => 'Hold a dumbbell vertically at your chest and perform a deep squat, keeping your chest proud.'
                ],
                [
                    'name' => 'Dumbbell Rows',
                    'sets' => 3, 'reps' => 10, 'duration' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=OL8yGrkXyiQ',
                    'instructions' => 'Bend at the hips, keep your back flat, and pull dumbbells up to your ribcage.'
                ],
                [
                    'name' => 'Plank Hold',
                    'sets' => 3, 'reps' => 1, 'duration' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw',
                    'instructions' => 'Hold a forearm plank position with a straight back and core fully engaged.'
                ],
                [
                    'name' => 'Dumbbell Shoulder Press',
                    'sets' => 3, 'reps' => 10, 'duration' => 60,
                    'image_url' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80',
                    'video_url' => 'https://www.youtube.com/watch?v=qEwKCR5JCog',
                    'instructions' => 'Press dumbbells straight overhead from shoulder level until arms are locked, then lower slowly.'
                ]
            ];
        }
    }
    
    $upcomingWorkouts = [
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
    
    // Priority Model from Admin Settings + active fallback models
    $models = [$primaryModel, 'gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-2.0-flash', 'gemini-2.5-flash', 'gemini-3.5-flash', 'gemini-flash-latest'];
    
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
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
        $workoutData = getLocalSmartWorkout($profile, $hasEquipment, $CATEGORY_FALLBACK_VIDEOS);
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

    if (!$hasEquipment) {
        $prompt .= "NO EQUIPMENT: Bodyweight exercises ONLY. No machinery/weights.\n";
    }
    if ($isBeginner) {
        $prompt .= "BEGINNER: Safe, easy exercises. search_term = slow instructional video (e.g. 'pushups tutorial'). instructions = 3-4 numbered steps.\n";
    } else {
        $prompt .= "ADVANCED: Standard exercise names. search_term = standard name. instructions = 1-2 cues.\n";
    }

    $prompt .= "CRITICAL: You MUST include exactly 5 high-quality, relevant exercises in todays_workout.exercises. Do not generate 3 or 4; there must be exactly 5 exercises. Also, for each upcoming workout in upcoming_workouts, you must include exactly 5 exercises in their exercises array.\n";

    $prompt .= "\nFormat JSON:\n";
    $prompt .= "{\n";
    $prompt .= '  "todays_workout": {"name": "Title", "exercises": [{"name": "Name", "search_term": "query", "sets": 3, "reps": 10, "instructions": "cues"}], "exercises_count": 5, "duration": 50, "difficulty": "beginner", "type": "strength"},';
    $prompt .= '  "recovery_score": 90, "status": "READY FOR SESSION", "missed_workouts": [], "upcoming_workouts": [{"name": "Upper Body Power", "scheduled_date": "YYYY-MM-DD", "duration": 45, "exercises_count": 5, "exercises": [{"name": "Name", "sets": 3, "reps": 10, "instructions": "cues"}]}]';
    $prompt .= "}\n";
    
    // Call Gemini with dynamic config
    try {
        $aiResponse = callGemini($prompt, $GEMINI_API_KEY, $PRIMARY_MODEL, $AI_TEMPERATURE);
        
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
            
            // Premium AI Coach: Skip curated mapping to fetch direct dynamic AI results & live YouTube API
            $media = findExerciseVideo($exerciseName, $searchTerm, $EXERCISE_VIDEO_MAP, $CATEGORY_FALLBACK_VIDEOS, $YOUTUBE_API_KEY, $workoutType, true);
            $exercise['video_url'] = $media['video'];
            $exercise['image_url'] = $media['image'];

            // Register this dynamic AI content inside the global Exercise Library!
            saveExerciseToLibrary($pdo, $exercise, $workoutType, $workoutData['todays_workout']['difficulty'] ?? 'intermediate');
        }
    }

    // 1. AUTO-HEALING: If AI fails or returns malformed data, use the high-quality backup generator
    if (!$workoutData || isset($workoutData['error']) || !isset($workoutData['todays_workout'])) {
        error_log("⚠️ Gemini AI error or malformed data, fallback to local smart generator.");
        $workoutData = getLocalSmartWorkout($profile, $hasEquipment, $CATEGORY_FALLBACK_VIDEOS);
        $ai_provider = 'Fitrova Smart Engine (Backup)';
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
                // Premium AI Coach: Skip curated mapping to fetch direct dynamic AI results & live YouTube API
                $m = findExerciseVideo($ex['name'], $ex['search_term'] ?? $ex['name'], $EXERCISE_VIDEO_MAP, $CATEGORY_FALLBACK_VIDEOS, $YOUTUBE_API_KEY, 'general', true);
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
