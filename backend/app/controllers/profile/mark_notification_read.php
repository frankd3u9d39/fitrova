<?php
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
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Support GET user_id & notification_id as well
    $userId = $input['user_id'] ?? $_GET['user_id'] ?? null;
    $notificationId = $input['notification_id'] ?? $_GET['notification_id'] ?? null;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
        exit();
    }
    
    $userId = intval($userId);
    
    if ($notificationId) {
        // Mark specific notification as read
        $notificationId = intval($notificationId);
        $stmt = $pdo->prepare("
            UPDATE ai_insights 
            SET is_read = TRUE 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notificationId, $userId]);
        $message = 'Notification marked as read';
    } else {
        // Mark all notifications as read for this user
        $stmt = $pdo->prepare("
            UPDATE ai_insights 
            SET is_read = TRUE 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$userId]);
        $message = 'All notifications marked as read';
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => $message
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
