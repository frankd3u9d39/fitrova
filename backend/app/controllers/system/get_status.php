<?php
// backend/app/controllers/system/get_status.php
// Public, read-only endpoint the mobile app queries to check system feature flags.
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
    // Batch-fetch all relevant system-level flags in one query
    $stmt = $pdo->query("
        SELECT setting_key, setting_value
        FROM system_settings
        WHERE setting_key IN ('payments_enabled', 'monetization_enabled', 'maintenance_mode')
    ");
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

    $paymentsEnabled = ($rows['payments_enabled'] ?? 'true') !== 'false';
    $maintenanceMode = ($rows['maintenance_mode'] ?? 'false') === 'true';
    $monetizationEnabled = ($rows['monetization_enabled'] ?? 'false') === 'true';

    echo json_encode([
        'status' => 'success',
        'data'   => [
            'payments_enabled'     => $paymentsEnabled,
            'maintenance_mode'     => $maintenanceMode,
            'monetization_enabled' => $monetizationEnabled,
        ]
    ]);
} catch (Exception $e) {
    // Fail open – assume everything is enabled if DB is unavailable
    echo json_encode([
        'status' => 'success',
        'data'   => [
            'payments_enabled' => true,
            'maintenance_mode' => false,
        ]
    ]);
}
?>
