<?php
require_once __DIR__ . '/../backend/config/db_config.php';
$stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'ai_model_primary'");
$stmt->execute(['gemini-2.0-flash']);
echo "Updated model to gemini-2.0-flash\n";
