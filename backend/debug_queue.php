<?php
require_once __DIR__ . '/config/db_config.php';
$stmt = $pdo->query("SELECT * FROM queue ORDER BY created_at DESC LIMIT 10");
$jobs = $stmt->fetchAll();
header('Content-Type: application/json');
echo json_encode($jobs, JSON_PRETTY_PRINT);
?>
