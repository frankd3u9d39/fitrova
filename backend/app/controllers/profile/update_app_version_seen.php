<?php
// backend/app/controllers/profile/update_app_version_seen.php
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
    $version = $data['version'] ?? null;

    if (!$userId || !$version) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing user_id or version']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE users SET app_version_seen = ? WHERE id = ?");
    $stmt->execute([$version, $userId]);

    echo json_encode([
        'status' => 'success',
        'message' => 'App version seen status updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update app version seen status: ' . $e->getMessage()
    ]);
}
?>
