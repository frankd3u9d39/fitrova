<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

try {
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $exerciseName = trim($_GET['exercise_name'] ?? '');

    if (!$userId || $exerciseName === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'user_id and exercise_name are required']);
        exit();
    }

    // Most recent session's sets for this exercise: find the latest logged
    // date, then return every set logged on that date, in set order.
    $lastDateStmt = $pdo->prepare("
        SELECT DATE(logged_at) as last_date
        FROM exercise_set_logs
        WHERE user_id = ? AND exercise_name = ?
        ORDER BY logged_at DESC
        LIMIT 1
    ");
    $lastDateStmt->execute([$userId, $exerciseName]);
    $lastDate = $lastDateStmt->fetchColumn();

    $previousSets = [];
    if ($lastDate) {
        $setsStmt = $pdo->prepare("
            SELECT set_number, weight_kg, reps, is_pr
            FROM exercise_set_logs
            WHERE user_id = ? AND exercise_name = ? AND DATE(logged_at) = ?
            ORDER BY set_number ASC
        ");
        $setsStmt->execute([$userId, $exerciseName, $lastDate]);
        $previousSets = $setsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Current best (from personal_records, the same table Profile already reads)
    $bestStmt = $pdo->prepare("
        SELECT MAX(weight_kg) as best_weight
        FROM personal_records
        WHERE user_id = ? AND exercise_name = ?
    ");
    $bestStmt->execute([$userId, $exerciseName]);
    $bestWeight = $bestStmt->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'last_session_date' => $lastDate ?: null,
            'previous_sets' => array_map(function ($s) {
                return [
                    'set_number' => (int)$s['set_number'],
                    'weight_kg'  => $s['weight_kg'] !== null ? (float)$s['weight_kg'] : null,
                    'reps'       => $s['reps'] !== null ? (int)$s['reps'] : null,
                    'is_pr'      => (bool)$s['is_pr'],
                ];
            }, $previousSets),
            'best_weight_kg' => $bestWeight !== null ? (float)$bestWeight : null,
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
