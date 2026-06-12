<?php
// backend/app/controllers/profile/update_profile.php
ini_set('post_max_size', '20M');
ini_set('upload_max_filesize', '20M');
ini_set('memory_limit', '64M');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $userId = $data['user_id'] ?? null;
    $firstName = $data['first_name'] ?? null;
    $lastName = $data['last_name'] ?? null;
    $email = $data['email'] ?? null;
    $motto = $data['motto'] ?? null;
    $profilePicture = $data['profile_picture'] ?? null;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing user_id']);
        exit();
    }
    
    $pdo->beginTransaction();
    
    // Update users table
    if ($firstName !== null || $lastName !== null || $email !== null) {
        $updateFields = [];
        $params = [];
        
        if ($firstName !== null) {
            $updateFields[] = "first_name = ?";
            $params[] = trim($firstName);
        }
        if ($lastName !== null) {
            $updateFields[] = "last_name = ?";
            $params[] = trim($lastName);
        }
        if ($email !== null) {
            $email = trim($email);
            // Check if email already exists for another user
            $emailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $emailStmt->execute([$email, $userId]);
            if ($emailStmt->fetch()) {
                throw new Exception('Email is already in use by another account');
            }
            $updateFields[] = "email = ?";
            $params[] = $email;
        }
        
        if (!empty($updateFields)) {
            $params[] = $userId;
            $stmt = $pdo->prepare("UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?");
            $stmt->execute($params);
        }
    }
    
    // Update user_profiles table for motto and profile_picture
    if ($motto !== null || $profilePicture !== null) {
        // Check if profile exists
        $profileStmt = $pdo->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
        $profileStmt->execute([$userId]);
        $profileExists = $profileStmt->fetch();
        
        if ($profileExists) {
            $profileFields = [];
            $profileParams = [];
            
            if ($motto !== null) {
                $profileFields[] = "motto = ?";
                $profileParams[] = trim($motto);
            }
            if ($profilePicture !== null) {
                $profileFields[] = "profile_picture = ?";
                $profileParams[] = $profilePicture;
            }
            
            if (!empty($profileFields)) {
                $profileParams[] = $userId;
                $stmt = $pdo->prepare("UPDATE user_profiles SET " . implode(", ", $profileFields) . " WHERE user_id = ?");
                $stmt->execute($profileParams);
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO user_profiles (user_id, motto, profile_picture) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $motto ? trim($motto) : null, $profilePicture]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Profile updated successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
