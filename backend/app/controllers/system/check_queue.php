<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

try {
    $stmt = $pdo->query("SELECT id, handler, status, attempts, last_error, created_at FROM queue ORDER BY id DESC LIMIT 15");
    $jobs = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    // Also check if SMTP_USER is set and BREVO_API_KEY is configured
    $smtpUser = getenv('SMTP_USER') ?: 'jackcojahk@gmail.com';
    $hasBrevoKey = !empty(getenv('BREVO_API_KEY'));

    echo json_encode([
        'status' => 'success',
        'smtp_user' => $smtpUser,
        'has_brevo_key' => $hasBrevoKey,
        'jobs' => $jobs
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
