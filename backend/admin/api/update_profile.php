<?php
// backend/admin/api/update_profile.php

header('Content-Type: application/json');

// Session is already started and pdo is loaded by auth_check
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit();
}

if (!$adminUser) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Admin user not found."]);
    exit();
}

// Support both application/json and application/x-www-form-urlencoded
$data = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?? [];
} else {
    $data = $_POST;
}

$firstName = trim($data['first_name'] ?? '');
$lastName  = trim($data['last_name'] ?? '');
$email     = trim($data['email'] ?? '');
$password  = $data['password'] ?? '';
$profilePic = trim($data['profile_picture'] ?? '');

// Validation
if (empty($firstName) || empty($lastName) || empty($email)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "First name, last name, and email are required fields."]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email format."]);
    exit();
}

try {
    $pdo->beginTransaction();

    // Check email uniqueness (exclude current admin ID)
    $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $emailCheck->execute([$email, $adminUser['id']]);
    if ($emailCheck->fetch()) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "This email is already in use by another user."]);
        $pdo->rollBack();
        exit();
    }

    // Update users table
    if (!empty($password)) {
        // Validation: minimum length
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters long."]);
            $pdo->rollBack();
            exit();
        }
        $pwHash = password_hash($password, PASSWORD_BCRYPT);
        $updateUser = $pdo->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?, password_hash = ?
            WHERE id = ?
        ");
        $updateUser->execute([$firstName, $lastName, $email, $pwHash, $adminUser['id']]);
    } else {
        $updateUser = $pdo->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?
            WHERE id = ?
        ");
        $updateUser->execute([$firstName, $lastName, $email, $adminUser['id']]);
    }

    // Update user_profiles table (for profile_picture)
    $updateProfile = $pdo->prepare("
        UPDATE user_profiles 
        SET profile_picture = ?
        WHERE user_id = ?
    ");
    $updateProfile->execute([$profilePic ? $profilePic : null, $adminUser['id']]);

    $pdo->commit();

    // Sync session state
    $_SESSION['admin_name'] = $firstName . ' ' . $lastName;
    $_SESSION['admin_email'] = $email;

    echo json_encode([
        "status" => "success",
        "message" => "Profile updated successfully.",
        "data" => [
            "first_name" => $firstName,
            "last_name" => $lastName,
            "email" => $email,
            "profile_picture" => $profilePic
        ]
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Failed to update admin profile: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error occurred. Could not update profile."]);
}
?>
