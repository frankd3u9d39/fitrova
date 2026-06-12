<?php
require 'backend/config/db_config.php';

// Simulate what get_dashboard_data.php returns for weight
$userId = 1;
$weightStmt = $pdo->prepare("
    SELECT weight, recorded_date
    FROM weight_history
    WHERE user_id = ? AND recorded_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY recorded_date ASC
");
$weightStmt->execute([$userId]);
$weightHistory = $weightStmt->fetchAll(PDO::FETCH_ASSOC);

echo "Weight history returned to dashboard (user 1):\n";
foreach ($weightHistory as $w) {
    echo "  {$w['recorded_date']} => {$w['weight']} kg\n";
}
echo "\nTotal entries: " . count($weightHistory) . "\n";

// Also verify current_weight
$cur = $pdo->prepare("SELECT current_weight, weight, target_weight FROM user_profiles WHERE user_id = ?");
$cur->execute([$userId]);
$p = $cur->fetch(PDO::FETCH_ASSOC);
echo "\ncurrent_weight: {$p['current_weight']} kg\n";
echo "base weight: {$p['weight']} kg\n";
echo "target_weight: {$p['target_weight']} kg\n";
