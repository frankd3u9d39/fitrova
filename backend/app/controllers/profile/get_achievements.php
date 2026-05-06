<?php
// backend/app/controllers/profile/get_achievements.php
require_once __DIR__ . '/../../../config/db_config.php';

header('Content-Type: application/json');

// Get user ID
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    // Try to get from JSON body
    $data = json_decode(file_get_contents("php://input"), true);
    if (isset($data['user_id'])) {
        $user_id = intval($data['user_id']);
    }
}

if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Valid User ID is required"]);
    exit();
}

try {
    // Fetch all achievements and left join with user_achievements to check if unlocked
    $stmt = $pdo->prepare("
        SELECT 
            a.id, 
            a.title, 
            a.description, 
            a.icon, 
            a.category, 
            a.color,
            CASE WHEN ua.unlocked_at IS NOT NULL THEN 1 ELSE 0 END as unlocked
        FROM achievements a
        LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
        ORDER BY a.category, a.id
    ");
    $stmt->execute([$user_id]);
    $achievements = $stmt->fetchAll();

    // Format boolean fields
    foreach ($achievements as &$a) {
        $a['unlocked'] = (bool)$a['unlocked'];
        $a['id'] = (string)$a['id']; // String for frontend compatibility
    }

    echo json_encode([
        "status" => "success",
        "data" => $achievements
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error fetching achievements: " . $e->getMessage()]);
}
?>
