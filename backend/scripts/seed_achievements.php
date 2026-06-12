<?php
// backend/scripts/seed_achievements.php

require_once __DIR__ . '/../config/db_config.php';

try {
    echo "=== SEEDING ACHIEVEMENTS ===\n";
    
    // 1. Clear existing empty or duplicate data to ensure clean seed
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE achievements;");
    $pdo->exec("TRUNCATE TABLE user_achievements;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    // 2. Insert standard achievements
    $achievements = [
        [1, '7-Day Streak', 'Consistency King', 'flash', 'Training', '#D1FAE5'],
        [2, 'Iron Will', '50 Heavy Sessions', 'barbell', 'Training', '#D1FAE5'],
        [3, 'Iron Will Rank II', '100 Heavy Sessions', 'barbell', 'Training', '#E5E7EB'],
        [4, 'Sprint Master', 'Max Velocity Hit', 'speedometer', 'Training', '#D1FAE5'],
        [5, 'Protein Pro', 'Macro Precision', 'restaurant', 'Nutrition', '#D1FAE5'],
        [6, 'Water God', 'Stay Hydrated', 'water', 'Nutrition', '#E5E7EB'],
        [7, 'Leafy Legend', 'Eat Your Greens', 'leaf', 'Nutrition', '#E5E7EB'],
        [8, 'First Step', 'Journey Begun', 'footsteps', 'Milestones', '#D1FAE5'],
        [9, 'Month Strong', '30 Days Active', 'calendar', 'Milestones', '#D1FAE5'],
        [10, 'Year Warrior', '365 Days of Fitness', 'trophy', 'Milestones', '#E5E7EB']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO achievements (id, title, description, icon, category, color) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($achievements as $a) {
        $stmt->execute($a);
        echo "Seeded achievement: {$a[1]}\n";
    }
    
    // 3. Unlock some standard achievements for test users (User 1, User 3, User 6)
    $testUsers = [1, 3, 6];
    $unlockedIds = [1, 2, 4, 5, 8, 9]; // Seed unlocked achievements
    
    $uaStmt = $pdo->prepare("INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)");
    foreach ($testUsers as $uid) {
        // Verify user exists first
        $uCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
        $uCheck->execute([$uid]);
        if ($uCheck->fetchColumn() > 0) {
            foreach ($unlockedIds as $aid) {
                $uaStmt->execute([$uid, $aid]);
            }
            echo "Unlocked default achievements progress for User ID: $uid\n";
        }
    }
    
    echo "Achievements seeded and unlocked successfully!\n";
} catch (PDOException $e) {
    die("Database Error seeding achievements: " . $e->getMessage() . "\n");
}
