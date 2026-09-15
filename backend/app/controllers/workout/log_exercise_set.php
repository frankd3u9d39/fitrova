<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $userId       = intval($input['user_id'] ?? 0);
    $exerciseName = trim($input['exercise_name'] ?? '');
    $setNumber    = intval($input['set_number'] ?? 1);
    $weightKg     = isset($input['weight_kg']) && $input['weight_kg'] !== '' ? (float)$input['weight_kg'] : null;
    $reps         = isset($input['reps']) && $input['reps'] !== '' ? (int)$input['reps'] : null;

    if (!$userId || $exerciseName === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'user_id and exercise_name are required']);
        exit();
    }

    // A set needs at least a weight or a rep count to be worth logging
    if ($weightKg === null && $reps === null) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Provide at least a weight or a rep count']);
        exit();
    }

    // Determine the previous best weight for this exercise before this set
    $bestStmt = $pdo->prepare("SELECT MAX(weight_kg) FROM personal_records WHERE user_id = ? AND exercise_name = ?");
    $bestStmt->execute([$userId, $exerciseName]);
    $previousBest = $bestStmt->fetchColumn();
    $previousBest = $previousBest !== null ? (float)$previousBest : null;

    $isPr = $weightKg !== null && ($previousBest === null || $weightKg > $previousBest);

    $insertStmt = $pdo->prepare("
        INSERT INTO exercise_set_logs (user_id, exercise_name, set_number, weight_kg, reps, is_pr)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->execute([$userId, $exerciseName, $setNumber, $weightKg, $reps, $isPr ? 1 : 0]);

    if ($isPr) {
        $prStmt = $pdo->prepare("
            INSERT INTO personal_records (user_id, exercise_name, weight_kg, recorded_date)
            VALUES (?, ?, ?, CURDATE())
        ");
        $prStmt->execute([$userId, $exerciseName, $weightKg]);
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'is_pr'          => $isPr,
            'previous_best'  => $previousBest,
            'weight_kg'      => $weightKg,
            'reps'           => $reps,
            'set_number'     => $setNumber,
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
