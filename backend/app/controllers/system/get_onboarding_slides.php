<?php
// backend/app/controllers/system/get_onboarding_slides.php
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
    // 1. Check if onboarding is globally enabled
    $settingStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'onboarding_enabled' LIMIT 1");
    $settingStmt->execute();
    $onboardingEnabledVal = $settingStmt->fetchColumn();
    $onboardingEnabled = ($onboardingEnabledVal === 'true' || $onboardingEnabledVal === null); // Default to true if not set

    $slides = [];
    if ($onboardingEnabled) {
        // 2. Fetch active onboarding slides ordered by sort_order
        $stmt = $pdo->query("
            SELECT id, title, description, sort_order 
            FROM onboarding_slides 
            WHERE is_active = 1 
            ORDER BY sort_order ASC
        ");
        if ($stmt) {
            $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode([
        'status' => 'success',
        'data'   => $slides
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch onboarding slides: ' . $e->getMessage()
    ]);
}
?>
