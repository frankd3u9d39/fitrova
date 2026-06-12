<?php
require_once __DIR__ . '/../backend/config/db_config.php';

$stmt = $pdo->query("SELECT id, name, category, difficulty, keywords, video_url, image_url FROM exercise_library");
echo "=== Exercise Library ===\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
?>
