<?php
require_once __DIR__ . '/../backend/config/db_config.php';

echo "Running independent retroactive sync of all generated workout plans to exercise_library...\n";

function localSaveExerciseToLibrary($pdo, $exercise, $workoutType = 'strength', $difficulty = 'intermediate') {
    $name = trim($exercise['name'] ?? '');
    if (empty($name)) return;
    
    // Check if it already exists (case-insensitive)
    $stmt = $pdo->prepare("SELECT id FROM exercise_library WHERE LOWER(name) = LOWER(?)");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        return; // Already exists
    }
    
    // Determine category
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
    
    // Determine difficulty
    $diffLower = strtolower($difficulty);
    $diffValue = 'intermediate';
    if ($diffLower === 'beginner' || $diffLower === 'intermediate' || $diffLower === 'advanced') {
        $diffValue = $diffLower;
    }
    
    $keywords = implode(', ', array_unique(array_filter([
        strtolower($name), 
        strtolower($category),
        strtolower($diffValue)
    ])));
    
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
        echo "[+] Registered new exercise in database: {$name}\n";
    } catch (Exception $e) {
        echo "[-] Failed to insert exercise: " . $e->getMessage() . "\n";
    }
}

$plansStmt = $pdo->query("SELECT id, plan_data FROM workout_plans");
$plans = $plansStmt->fetchAll(PDO::FETCH_ASSOC);

$totalInserted = 0;

foreach ($plans as $plan) {
    $data = json_decode($plan['plan_data'], true);
    if (!$data) continue;
    
    // Sync today's exercises
    if (isset($data['todays_workout']['exercises']) && is_array($data['todays_workout']['exercises'])) {
        $workoutType = $data['todays_workout']['type'] ?? 'strength';
        $difficulty = $data['todays_workout']['difficulty'] ?? 'intermediate';
        foreach ($data['todays_workout']['exercises'] as $ex) {
            $name = trim($ex['name'] ?? '');
            if (empty($name)) continue;
            
            // Check if already in library
            $stmt = $pdo->prepare("SELECT id FROM exercise_library WHERE LOWER(name) = LOWER(?)");
            $stmt->execute([$name]);
            if (!$stmt->fetch()) {
                localSaveExerciseToLibrary($pdo, $ex, $workoutType, $difficulty);
                $totalInserted++;
            }
        }
    }
    
    // Sync upcoming exercises
    if (isset($data['upcoming_workouts']) && is_array($data['upcoming_workouts'])) {
        foreach ($data['upcoming_workouts'] as $upcoming) {
            $workoutType = $upcoming['type'] ?? 'strength';
            $difficulty = $upcoming['difficulty'] ?? 'intermediate';
            if (isset($upcoming['exercises']) && is_array($upcoming['exercises'])) {
                foreach ($upcoming['exercises'] as $ex) {
                    $name = trim($ex['name'] ?? '');
                    if (empty($name)) continue;
                    
                    // Check if already in library
                    $stmt = $pdo->prepare("SELECT id FROM exercise_library WHERE LOWER(name) = LOWER(?)");
                    $stmt->execute([$name]);
                    if (!$stmt->fetch()) {
                        localSaveExerciseToLibrary($pdo, $ex, $workoutType, $difficulty);
                        $totalInserted++;
                    }
                }
            }
        }
    }
}

echo "\nRetroactive sync completed successfully. Total new exercises registered: {$totalInserted}.\n";
?>
