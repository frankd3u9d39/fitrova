<?php
require_once __DIR__ . '/../backend/config/db_config.php';
$stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
$stmt->execute([1]);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
?>
