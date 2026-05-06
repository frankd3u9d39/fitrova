<?php
// backend/app/controllers/auth/send_code.php
require_once __DIR__ . '/../../../config/db_config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email is required"]);
    exit();
}

$email = trim($data['email']);

// Optional: Check if email already exists in users table to prevent duplicate registration
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(["status" => "error", "message" => "Email already registered"]);
    exit();
}

$code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

try {
    // Save to pending verifications
    $stmt = $pdo->prepare("INSERT INTO pending_verifications (email, code) VALUES (?, ?)");
    $stmt->execute([$email, $code]);

    // Queue email job
    $jobPayload = json_encode([
        'email' => $email,
        'code' => $code
    ]);
    $jobStmt = $pdo->prepare("INSERT INTO queue (handler, payload) VALUES ('send_verification_email', ?)");
    $jobStmt->execute([$jobPayload]);

    echo json_encode([
        "status" => "success",
        "message" => "Verification code sent"
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to send code: " . $e->getMessage()]);
}
?>
