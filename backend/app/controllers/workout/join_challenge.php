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
    if (!isset($input['user_id']) || !isset($input['challenge_key'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'User ID and challenge key are required']);
        exit();
    }

    $userId = intval($input['user_id']);
    $challengeKey = trim($input['challenge_key']);
    $action = isset($input['action']) ? $input['action'] : 'join'; // 'join' or 'leave'

    if ($action === 'join') {
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_challenges (user_id, challenge_key) VALUES (?, ?)");
        $stmt->execute([$userId, $challengeKey]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM user_challenges WHERE user_id = ? AND challenge_key = ?");
        $stmt->execute([$userId, $challengeKey]);
    }

    echo json_encode(['status' => 'success', 'message' => 'Challenge status updated successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
