<?php
require_once __DIR__ . '/../backend/config/db_config.php';

$stmt = $pdo->prepare("UPDATE exercise_library SET video_url = ? WHERE name = ?");
$stmt->execute([
    'https://www.youtube.com/watch?v=7Pxr4xOrhNk',
    'Jumping Jacks'
]);

echo "Database updated! Jumping Jacks now uses the new verified embeddable video URL.\n";
?>
