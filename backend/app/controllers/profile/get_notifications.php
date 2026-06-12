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
    // Support both GET and POST requests
    $userId = $_GET['user_id'] ?? null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['user_id'])) {
            $userId = $input['user_id'];
        }
    }
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
        exit();
    }
    
    $userId = intval($userId);
    
    // Fetch all notifications from ai_insights for this user
    $stmt = $pdo->prepare("
        SELECT id, insight_text, insight_type, is_read, created_at
        FROM ai_insights
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count unread notifications
    $unreadCountStmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count
        FROM ai_insights
        WHERE user_id = ? AND is_read = FALSE
    ");
    $unreadCountStmt->execute([$userId]);
    $unreadData = $unreadCountStmt->fetch(PDO::FETCH_ASSOC);
    $unreadCount = intval($unreadData['unread_count'] ?? 0);
    
    echo json_encode([
        'status' => 'success',
        'unread_count' => $unreadCount,
        'data' => $notifications
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
