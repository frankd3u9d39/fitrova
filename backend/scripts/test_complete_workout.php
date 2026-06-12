<?php
// Quick test: call complete_workout.php logic directly with same DB config
require_once __DIR__ . '/../config/db_config.php';

$userId = 1;
$workoutName = 'Achievement Test Session';
$duration = 45;

// Simulate the POST input
$_SERVER['REQUEST_METHOD'] = 'POST';

// --- Step 1: Log workout ---
$stmt = $pdo->prepare("
    INSERT INTO workout_logs (user_id, workout_name, duration_minutes, completed_date)
    VALUES (?, ?, ?, CURDATE())
");
$stmt->execute([$userId, $workoutName, $duration]);
$logId = $pdo->lastInsertId();
echo "✅ Logged workout row, ID: $logId\n";

// --- Step 2: Count total workouts ---
$s = $pdo->prepare("SELECT COUNT(*) FROM workout_logs WHERE user_id = ?");
$s->execute([$userId]);
$total = (int)$s->fetchColumn();
echo "📊 Total workouts for user $userId: $total\n";

// --- Step 3: Streak check ---
$streak = 0;
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $ds = $pdo->prepare("SELECT COUNT(*) FROM workout_logs WHERE user_id = ? AND completed_date = ?");
    $ds->execute([$userId, $d]);
    if ((int)$ds->fetchColumn() > 0) { $streak++; } else { break; }
}
echo "🔥 Current streak: $streak days\n";

// --- Step 4: Show which achievements would unlock ---
$toUnlock = [];
if ($total >= 1)   $toUnlock[] = [8, 'First Step'];
if ($total >= 50)  $toUnlock[] = [2, 'Iron Will'];
if ($total >= 100) $toUnlock[] = [3, 'Iron Will Rank II'];
if ($streak >= 7)  $toUnlock[] = [1, '7-Day Streak'];

echo "\n🏆 Achievements that qualify to unlock:\n";
foreach ($toUnlock as [$aid, $title]) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM user_achievements WHERE user_id = ? AND achievement_id = ?");
    $check->execute([$userId, $aid]);
    $already = (int)$check->fetchColumn() > 0;
    echo "  - [$aid] $title → " . ($already ? "already unlocked" : "NEW UNLOCK") . "\n";
}

echo "\n✅ Test complete.\n";
