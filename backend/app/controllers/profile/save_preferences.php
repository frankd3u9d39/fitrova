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
    $data = json_decode(file_get_contents('php://input'), true);

    $userId               = $data['user_id']               ?? null;
    $unitPreference       = $data['unit_preference']       ?? null; // 'metric' or 'imperial'
    $notificationEnabled  = $data['notification_enabled']  ?? null; // 1 or 0
    $language             = $data['language']              ?? null; // 'en', 'fr', etc.

    if (!$userId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing user_id']);
        exit();
    }

    $fields = [];
    $params = [];

    if ($unitPreference !== null) {
        $fields[] = 'unit_preference = ?';
        $params[] = $unitPreference;
    }
    if ($notificationEnabled !== null) {
        $fields[] = 'notification_enabled = ?';
        $params[] = (int)$notificationEnabled;
    }
    if ($language !== null) {
        $fields[] = 'language = ?';
        $params[] = $language;
    }

    if (empty($fields)) {
        echo json_encode(['status' => 'success', 'message' => 'Nothing to update']);
        exit();
    }

    // Upsert: update if exists, insert if not
    $checkStmt = $pdo->prepare('SELECT id FROM user_profiles WHERE user_id = ?');
    $checkStmt->execute([$userId]);
    $exists = $checkStmt->fetchColumn();

    if ($exists) {
        $params[] = $userId;
        $pdo->prepare('UPDATE user_profiles SET ' . implode(', ', $fields) . ' WHERE user_id = ?')
            ->execute($params);
    } else {
        $pdo->prepare("INSERT INTO user_profiles (user_id, unit_preference, notification_enabled, language) VALUES (?, ?, ?, ?)")
            ->execute([$userId, $unitPreference ?? 'metric', $notificationEnabled ?? 1, $language ?? 'en']);
    }

    echo json_encode(['status' => 'success', 'message' => 'Preferences saved']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
