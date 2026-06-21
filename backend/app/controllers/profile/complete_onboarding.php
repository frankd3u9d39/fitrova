<?php
// backend/app/controllers/profile/complete_onboarding.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $userId = $data['user_id'] ?? null;

    if (!$userId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing user_id']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE users SET has_completed_onboarding = 1 WHERE id = ?");
    $stmt->execute([$userId]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Onboarding status updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update onboarding status: ' . $e->getMessage()
    ]);
}
?>
