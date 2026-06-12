<?php
// backend/app/controllers/auth/forgot_password.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    if (empty($action)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Action is required"]);
        exit();
    }

    if ($action === 'send_code') {
        if (!isset($data['email']) || empty(trim($data['email']))) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Email is required"]);
            exit();
        }

        $email = trim($data['email']);

        // Verify if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "This email is not registered with Fitrova."]);
            exit();
        }

        // Generate a 6-digit random code
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Save to pending verifications table
        $stmt = $pdo->prepare("INSERT INTO pending_verifications (email, code) VALUES (?, ?)");
        $stmt->execute([$email, $code]);

        // Queue verification email job using the existing queue handler
        $jobPayload = json_encode([
            'email' => $email,
            'code' => $code
        ]);
        $jobStmt = $pdo->prepare("INSERT INTO queue (handler, payload) VALUES ('send_verification_email', ?)");
        $jobStmt->execute([$jobPayload]);

        echo json_encode([
            "status" => "success",
            "message" => "A password reset verification code has been sent to your email."
        ]);
        exit();

    } elseif ($action === 'verify_and_reset') {
        if (empty($data['email']) || empty($data['code']) || empty($data['new_password'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Email, code, and new password are required"]);
            exit();
        }

        $email = trim($data['email']);
        $code = trim($data['code']);
        $newPassword = $data['new_password'];

        if (strlen($newPassword) < 8) {
            http_response_code(422);
            echo json_encode(["status" => "error", "message" => "Password must be at least 8 characters long"]);
            exit();
        }

        // Verify code
        $stmt = $pdo->prepare("SELECT id FROM pending_verifications WHERE email = ? AND code = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email, $code]);
        $pending = $stmt->fetch();

        if (!$pending) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Invalid or expired verification code."]);
            exit();
        }

        // Delete all verification codes for this email
        $delStmt = $pdo->prepare("DELETE FROM pending_verifications WHERE email = ?");
        $delStmt->execute([$email]);

        // Hash and update password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $updateStmt->execute([$newHash, $email]);

        echo json_encode([
            "status" => "success",
            "message" => "Your password has been successfully reset. You can now log in with your new password!"
        ]);
        exit();

    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid action specified."]);
        exit();
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
}
?>
