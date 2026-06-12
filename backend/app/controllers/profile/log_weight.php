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
    $data = json_decode(file_get_contents('php://input'), true);

    $userId = $data['user_id'] ?? null;
    $weight = $data['weight']  ?? null;
    $date   = $data['date']    ?? date('Y-m-d');

    if (!$userId || !$weight) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing user_id or weight']);
        exit();
    }

    $weight = round(floatval($weight), 1);

    if ($weight < 20 || $weight > 500) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Weight must be between 20 and 500 kg']);
        exit();
    }

    // Upsert: update if entry for today exists, insert otherwise
    $checkStmt = $pdo->prepare("SELECT id FROM weight_history WHERE user_id = ? AND recorded_date = ?");
    $checkStmt->execute([$userId, $date]);
    $existing = $checkStmt->fetchColumn();

    if ($existing) {
        $pdo->prepare("UPDATE weight_history SET weight = ? WHERE id = ?")
            ->execute([$weight, $existing]);
    } else {
        $pdo->prepare("INSERT INTO weight_history (user_id, weight, recorded_date) VALUES (?, ?, ?)")
            ->execute([$userId, $weight, $date]);
    }

    // Always update current_weight in user_profiles
    $pdo->prepare("UPDATE user_profiles SET current_weight = ? WHERE user_id = ?")
        ->execute([$weight, $userId]);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Weight logged successfully',
        'weight'  => $weight,
        'date'    => $date,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
