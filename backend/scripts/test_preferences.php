<?php
require 'backend/config/db_config.php';

// Test save_preferences logic directly
$userId = 1;

$fields = ['unit_preference = ?', 'notification_enabled = ?', 'language = ?'];
$params = ['imperial', 1, 'fr'];

$checkStmt = $pdo->prepare('SELECT id FROM user_profiles WHERE user_id = ?');
$checkStmt->execute([$userId]);
$exists = $checkStmt->fetchColumn();

if ($exists) {
    $params[] = $userId;
    $pdo->prepare('UPDATE user_profiles SET ' . implode(', ', $fields) . ' WHERE user_id = ?')->execute($params);
    echo "✅ Updated preferences for user $userId\n";
} else {
    echo "❌ User profile not found\n";
}

// Verify
$r = $pdo->prepare('SELECT unit_preference, notification_enabled, language FROM user_profiles WHERE user_id = ?');
$r->execute([$userId]);
$row = $r->fetch(PDO::FETCH_ASSOC);
print_r($row);
