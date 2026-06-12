<?php
require_once __DIR__ . '/../backend/config/db_config.php';

$stmt = $pdo->prepare("SELECT plan_data FROM workout_plans WHERE id = 19");
$stmt->execute();
$row = $stmt->fetch();
if ($row) {
    $data = json_decode($row['plan_data'], true);
    echo "Today's plan exercises:\n";
    if (isset($data['todays_workout']['exercises'])) {
        foreach ($data['todays_workout']['exercises'] as $ex) {
            echo "- " . $ex['name'] . " (video: " . ($ex['video_url'] ?? 'none') . ")\n";
        }
    }
    echo "\nUpcoming workouts:\n";
    if (isset($data['upcoming_workouts'])) {
        foreach ($data['upcoming_workouts'] as $w) {
            echo $w['name'] . ":\n";
            if (isset($w['exercises'])) {
                foreach ($w['exercises'] as $ex) {
                    echo "  - " . $ex['name'] . " (video: " . ($ex['video_url'] ?? 'none') . ")\n";
                }
            }
        }
    }
}
?>
