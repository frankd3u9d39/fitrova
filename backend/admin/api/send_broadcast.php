<?php
// backend/admin/api/send_broadcast.php
// Admin-only API to broadcast a message to all users.

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../config/db_config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $message = $input['message'] ?? '';

    if (empty(trim($message))) {
        throw new Exception('Broadcast message cannot be empty');
    }

    // 1. Get all active users
    $usersQuery = $pdo->query("SELECT id FROM users");
    $users = $usersQuery->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        throw new Exception('No registered users found to receive broadcast.');
    }

    // 2. Insert into ai_insights for every user
    $pdo->beginTransaction();
    $insertStmt = $pdo->prepare("
        INSERT INTO ai_insights (user_id, insight_text, insight_type, is_read)
        VALUES (?, ?, 'motivation', FALSE)
    ");

    foreach ($users as $user) {
        $insertStmt->execute([$user['id'], $message]);
    }
    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Broadcast message sent successfully to all users.',
        'recipient_count' => count($users)
    ]);
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
