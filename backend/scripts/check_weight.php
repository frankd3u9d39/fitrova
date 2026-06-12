<?php
require 'backend/config/db_config.php';

// 1. Does weight_history table exist?
echo "=== CHECKING TABLES ===\n";
$tables = $pdo->query("SHOW TABLES LIKE 'weight_history'")->fetchAll(PDO::FETCH_COLUMN);
echo "weight_history exists: " . (count($tables) > 0 ? "YES" : "NO") . "\n";

if (count($tables) > 0) {
    echo "\n=== weight_history SCHEMA ===\n";
    $cols = $pdo->query("DESCRIBE weight_history")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo $c['Field'] . " (" . $c['Type'] . ")\n";
    }

    echo "\n=== weight_history DATA (all users) ===\n";
    $rows = $pdo->query("SELECT * FROM weight_history ORDER BY user_id, recorded_date DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) === 0) {
        echo "TABLE IS EMPTY — no weight entries recorded.\n";
    } else {
        foreach ($rows as $r) {
            echo "user_id={$r['user_id']}  date={$r['recorded_date']}  weight={$r['weight']}\n";
        }
    }
} else {
    echo "\n❌ weight_history table does NOT exist!\n";
}

// 2. Check user_profiles for current_weight
echo "\n=== user_profiles.current_weight / weight ===\n";
$rows = $pdo->query("SELECT user_id, weight, current_weight, target_weight FROM user_profiles LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "user_id={$r['user_id']}  weight={$r['weight']}  current_weight={$r['current_weight']}  target={$r['target_weight']}\n";
}
