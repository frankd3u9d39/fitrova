<?php
// scratch/seed_notifications.php

require_once __DIR__ . '/../backend/config/db_config.php';

$userId = 1;

// Clean up any unread insights first to avoid duplicate spam on re-runs
$pdo->prepare("DELETE FROM ai_insights WHERE user_id = ?")->execute([$userId]);

// Define some mock notifications
$insights = [
    [
        'insight_text' => 'Dietary warning: Your sodium intake was 25% higher than your target yesterday. Drink extra water to flush out excess retention and avoid processed food today.',
        'insight_type' => 'warning'
    ],
    [
        'insight_text' => 'Coach Tip: Try drinking 500ml of green tea before your next workout to boost your metabolic rate by up to 4% and stay hydrated.',
        'insight_type' => 'tip'
    ],
    [
        'insight_text' => 'Form Tip: Excellent squat form detected on your form check video! Your hips moved parallel to the floor, ensuring maximum quad activation.',
        'insight_type' => 'tip'
    ],
    [
        'insight_text' => 'Achievement: 5-Day Workout Streak unlocked! You have consistently trained and stayed active. Keep pushing toward your weekly target!',
        'insight_type' => 'achievement'
    ],
    [
        'insight_text' => 'Daily Motivation: "Success isn\'t always about greatness. It\'s about consistency. Consistent hard work leads to success." Rise and grind, Champion!',
        'insight_type' => 'motivation'
    ],
];

echo "Inserting mock AI insights for user_id = {$userId}...\n";

$stmt = $pdo->prepare("
    INSERT INTO ai_insights (user_id, insight_text, insight_type, is_read, created_at)
    VALUES (?, ?, ?, FALSE, ?)
");

foreach ($insights as $index => $insight) {
    // Stagger dates slightly so they order nicely
    $time = date('Y-m-d H:i:s', strtotime("-{$index} hours"));
    $stmt->execute([
        $userId,
        $insight['insight_text'],
        $insight['insight_type'],
        $time
    ]);
    echo "✓ Seeded {$insight['insight_type']} notification\n";
}

echo "All mock notifications seeded successfully! Feel free to refresh the app dashboard to see them.\n";
?>
