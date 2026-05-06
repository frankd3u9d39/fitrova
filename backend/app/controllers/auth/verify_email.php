<?php
// backend/app/controllers/auth/verify_email.php
require_once __DIR__ . '/../../../config/db_config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !isset($data['code'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email and code are required"]);
    exit();
}

$email = trim($data['email']);
$code = trim($data['code']);

try {
    // 1. Check pending_verifications first (for new users)
    $stmt = $pdo->prepare("SELECT id FROM pending_verifications WHERE email = ? AND code = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email, $code]);
    $pending = $stmt->fetch();

    if ($pending) {
        // Success for a new user flow
        // Optional: delete the pending record
        $delStmt = $pdo->prepare("DELETE FROM pending_verifications WHERE email = ?");
        $delStmt->execute([$email]);

        echo json_encode([
            "status" => "success", 
            "message" => "Email verified successfully",
            "type" => "new_user"
        ]);
        exit();
    }

    // 2. Fallback: Check users table (for existing unverified users if any)
    $stmt = $pdo->prepare("SELECT id, verification_code FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['verification_code'] === $code) {
        $updateStmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
        $updateStmt->execute([$user['id']]);

        echo json_encode([
            "status" => "success", 
            "message" => "Email verified successfully",
            "userId" => $user['id'],
            "type" => "existing_user"
        ]);
        exit();
    }

    // If neither matched
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid verification code"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Verification failed: " . $e->getMessage()]);
}
?>
