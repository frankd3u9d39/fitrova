<?php
// backend/app/controllers/system/get_app_update.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

try {
    // Fetch the latest active app update record
    $stmt = $pdo->query("
        SELECT version, is_active, force_update, message, update_url 
        FROM app_updates 
        WHERE is_active = 1 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $update = $stmt ? $stmt->fetch() : null;

    if ($update) {
        echo json_encode([
            'status' => 'success',
            'data'   => [
                'version'      => $update['version'],
                'is_active'    => (bool)$update['is_active'],
                'force_update' => (bool)$update['force_update'],
                'message'      => $update['message'],
                'update_url'   => $update['update_url']
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'data'   => null
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to check app update: ' . $e->getMessage()
    ]);
}
?>
