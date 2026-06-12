<?php
require_once __DIR__ . '/../backend/config/db_config.php';

$userId = 6;
$profileStmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
$profileStmt->execute([$userId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

if ($profile) {
    echo "🎯 Goal: " . ($profile['fitness_goal'] ?? 'None') . "\n";
    echo "🍏 Diet: " . ($profile['diet_preference'] ?? 'None') . "\n";
    echo "⚠️ Allergies: " . ($profile['allergies'] ?? 'None') . "\n";
    echo "🩺 Conditions: " . ($profile['medical_conditions'] ?? 'None') . "\n";
    echo "🎂 Age: " . ($profile['age'] ?? 'None') . "\n";
    echo "⚖️ Weight: " . ($profile['weight'] ?? 'None') . "\n";
    echo "📏 Height: " . ($profile['height'] ?? 'None') . "\n";
} else {
    echo "❌ Profile not found for User 6!\n";
}
?>
