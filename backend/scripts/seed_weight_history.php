<?php
require 'backend/config/db_config.php';

// Seed realistic 7-day weight history for all users that have a starting weight
$users = $pdo->query("SELECT user_id, weight FROM user_profiles WHERE weight IS NOT NULL AND weight > 0")->fetchAll(PDO::FETCH_ASSOC);

$seeded = 0;
foreach ($users as $u) {
    $baseWeight = floatval($u['weight']);
    
    // Generate 7 days of plausible weight fluctuation (±0.3 kg per day drift)
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        
        // Check if entry already exists for this date
        $check = $pdo->prepare("SELECT id FROM weight_history WHERE user_id = ? AND recorded_date = ?");
        $check->execute([$u['user_id'], $date]);
        if ($check->fetchColumn()) continue;
        
        // Small random fluctuation: trend slightly downward by 0.05 per day from base
        $delta = (mt_rand(-20, 10) / 100); // between -0.20 and +0.10 kg
        $dayWeight = round($baseWeight + $delta - ($i * 0.05), 1); // slight downward trend
        if ($dayWeight < 40) $dayWeight = 40; // floor
        
        $pdo->prepare("INSERT INTO weight_history (user_id, weight, recorded_date) VALUES (?, ?, ?)")
            ->execute([$u['user_id'], $dayWeight, $date]);
        $seeded++;
    }
    
    // Update current_weight to today's value
    $today = date('Y-m-d');
    $todayW = $pdo->prepare("SELECT weight FROM weight_history WHERE user_id = ? AND recorded_date = ?");
    $todayW->execute([$u['user_id'], $today]);
    $todayWeight = $todayW->fetchColumn();
    if ($todayWeight) {
        $pdo->prepare("UPDATE user_profiles SET current_weight = ? WHERE user_id = ?")
            ->execute([$todayWeight, $u['user_id']]);
    }
}

echo "✅ Seeded $seeded weight entries across " . count($users) . " users.\n";

// Verify
echo "\n=== Sample data for user 1 ===\n";
$rows = $pdo->query("SELECT recorded_date, weight FROM weight_history WHERE user_id = 1 ORDER BY recorded_date ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "{$r['recorded_date']}  →  {$r['weight']} kg\n";
}
