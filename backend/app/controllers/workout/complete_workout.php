<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $userId     = $input['user_id']      ?? null;
    $workoutName = $input['workout_name'] ?? null;
    $duration   = $input['duration']     ?? 0; // expected in minutes

    if (!$userId || !$workoutName) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing user_id or workout_name']);
        exit();
    }

    // ─────────────────────────────────────────────────
    // 1. LOG THE COMPLETED WORKOUT
    // ─────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO workout_logs (user_id, workout_name, duration_minutes, completed_date)
        VALUES (?, ?, ?, CURDATE())
    ");
    $stmt->execute([$userId, $workoutName, $duration]);
    $logId = $pdo->lastInsertId();

    // ─────────────────────────────────────────────────
    // 2. ACHIEVEMENT AUTO-UNLOCK ENGINE
    // ─────────────────────────────────────────────────
    $newlyUnlocked = [];

    /**
     * Helper: Silently unlock an achievement if not already unlocked.
     * Returns true if it was newly unlocked.
     */
    $unlock = function(int $achievementId) use ($pdo, $userId, &$newlyUnlocked): bool {
        // Check if already unlocked
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM user_achievements 
            WHERE user_id = ? AND achievement_id = ?
        ");
        $checkStmt->execute([$userId, $achievementId]);
        if ($checkStmt->fetchColumn() > 0) {
            return false; // already unlocked
        }

        // Unlock it
        $insertStmt = $pdo->prepare("
            INSERT IGNORE INTO user_achievements (user_id, achievement_id)
            VALUES (?, ?)
        ");
        $insertStmt->execute([$userId, $achievementId]);

        // Fetch its title for the response
        $titleStmt = $pdo->prepare("SELECT title FROM achievements WHERE id = ?");
        $titleStmt->execute([$achievementId]);
        $title = $titleStmt->fetchColumn();
        if ($title) $newlyUnlocked[] = $title;

        return true;
    };

    // ── Achievement #8: "First Step" – First ever workout completed ──
    $totalWorkouts = $pdo->prepare("SELECT COUNT(*) FROM workout_logs WHERE user_id = ?");
    $totalWorkouts->execute([$userId]);
    $totalCount = (int)$totalWorkouts->fetchColumn();

    if ($totalCount >= 1) {
        $unlock(8); // First Step
    }

    // ── Achievement #2: "Iron Will" – 50 completed workouts ──
    if ($totalCount >= 50) {
        $unlock(2); // Iron Will
    }

    // ── Achievement #3: "Iron Will Rank II" – 100 completed workouts ──
    if ($totalCount >= 100) {
        $unlock(3); // Iron Will Rank II
    }

    // ── Achievement #9: "Month Strong" – 30+ active workout days in last 30 days ──
    $activeDaysStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT completed_date) 
        FROM workout_logs 
        WHERE user_id = ? AND completed_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $activeDaysStmt->execute([$userId]);
    $activeDays = (int)$activeDaysStmt->fetchColumn();

    if ($activeDays >= 30) {
        $unlock(9); // Month Strong
    }

    // ── Achievement #1: "7-Day Streak" – 7 consecutive days with at least 1 workout ──
    // Walk back from today checking each of the last 7 days
    $streakCount = 0;
    for ($i = 0; $i < 7; $i++) {
        $dateToCheck = date('Y-m-d', strtotime("-$i days"));
        $dayCheckStmt = $pdo->prepare("
            SELECT COUNT(*) FROM workout_logs 
            WHERE user_id = ? AND completed_date = ?
        ");
        $dayCheckStmt->execute([$userId, $dateToCheck]);
        if ((int)$dayCheckStmt->fetchColumn() > 0) {
            $streakCount++;
        } else {
            break; // Streak is broken
        }
    }

    if ($streakCount >= 7) {
        $unlock(1); // 7-Day Streak
    }

    // ─────────────────────────────────────────────────
    // 3. RETURN RESPONSE WITH UNLOCKED ACHIEVEMENTS
    // ─────────────────────────────────────────────────
    echo json_encode([
        'status'           => 'success',
        'message'          => 'Workout completed successfully',
        'log_id'           => $logId,
        'newly_unlocked'   => $newlyUnlocked,
        'total_workouts'   => $totalCount,
        'streak_days'      => $streakCount,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Failed to save workout: ' . $e->getMessage()
    ]);
}

