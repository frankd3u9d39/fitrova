<?php
require_once __DIR__ . '/../backend/config/db_config.php';
$stmt = $pdo->query("SELECT * FROM form_check_logs ORDER BY id DESC LIMIT 5");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
