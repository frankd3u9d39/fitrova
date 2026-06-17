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
    if (!isset($input['user_id']) || !isset($input['action'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'User ID and action are required']);
        exit();
    }

    $userId = intval($input['user_id']);
    $action = trim($input['action']);
    $targetId = isset($input['target_id']) ? intval($input['target_id']) : null;

    if ($action !== 'get_received' && !$targetId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Target user ID is required']);
        exit();
    }

    switch ($action) {
        case 'send_request':
            // Check if reverse request already exists (i.e. target already requested us)
            // If so, auto-accept it!
            $reverseStmt = $pdo->prepare("SELECT id FROM user_connections WHERE requester_id = ? AND receiver_id = ?");
            $reverseStmt->execute([$targetId, $userId]);
            $reverseExists = $reverseStmt->fetchColumn();

            if ($reverseExists) {
                $updateStmt = $pdo->prepare("UPDATE user_connections SET status = 'accepted' WHERE id = ?");
                $updateStmt->execute([$reverseExists]);
                $msg = 'Connected!';
            } else {
                $stmt = $pdo->prepare("INSERT IGNORE INTO user_connections (requester_id, receiver_id, status) VALUES (?, ?, 'pending')");
                $stmt->execute([$userId, $targetId]);
                $msg = 'Request sent successfully';
            }
            echo json_encode(['status' => 'success', 'message' => $msg]);
            break;

        case 'accept_request':
            $stmt = $pdo->prepare("UPDATE user_connections SET status = 'accepted' WHERE requester_id = ? AND receiver_id = ?");
            $stmt->execute([$targetId, $userId]);
            echo json_encode(['status' => 'success', 'message' => 'Request accepted']);
            break;

        case 'decline_request':
            $stmt = $pdo->prepare("DELETE FROM user_connections WHERE requester_id = ? AND receiver_id = ? AND status = 'pending'");
            $stmt->execute([$targetId, $userId]);
            echo json_encode(['status' => 'success', 'message' => 'Request declined']);
            break;

        case 'cancel_request':
            $stmt = $pdo->prepare("DELETE FROM user_connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)");
            $stmt->execute([$userId, $targetId, $targetId, $userId]);
            echo json_encode(['status' => 'success', 'message' => 'Request cancelled/connection removed']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
