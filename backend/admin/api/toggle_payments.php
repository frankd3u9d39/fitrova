<?php
// backend/admin/api/toggle_payments.php
// Admin-only API to read/write the global payment flow toggle.

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db_config.php';

try {
    // ------------------------------------------------------------------
    // GET  — return current toggle state
    // ------------------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'payments_enabled' LIMIT 1");
        $value = $stmt ? $stmt->fetchColumn() : null;

        // If the row doesn't exist yet, create it defaulting to ON
        if ($value === false || $value === null) {
            $pdo->exec("
                INSERT INTO system_settings (setting_key, setting_value)
                VALUES ('payments_enabled', 'true')
                ON DUPLICATE KEY UPDATE setting_key = setting_key
            ");
            $value = 'true';
        }

        echo json_encode([
            'status'           => 'success',
            'payments_enabled' => ($value === 'true'),
        ]);
        exit();
    }

    // ------------------------------------------------------------------
    // POST — flip the toggle
    // ------------------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['payments_enabled'])) {
            throw new Exception('Missing payments_enabled field in request body');
        }

        $newState = $input['payments_enabled'] ? 'true' : 'false';

        $pdo->exec("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES ('payments_enabled', '{$newState}')
            ON DUPLICATE KEY UPDATE setting_value = '{$newState}'
        ");

        echo json_encode([
            'status'           => 'success',
            'payments_enabled' => ($newState === 'true'),
            'message'          => $newState === 'true'
                ? 'Payment flows are now ENABLED globally.'
                : 'Payment flows are now DISABLED globally.',
        ]);
        exit();
    }

    throw new Exception('Method not allowed');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
