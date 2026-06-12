<?php
require_once __DIR__ . '/../backend/config/db_config.php';

$stmt = $pdo->query("SELECT * FROM system_settings");
echo "=== System Settings ===\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['setting_key'] . " = " . substr($row['setting_value'], 0, 50) . (strlen($row['setting_value']) > 50 ? "..." : "") . "\n";
}
?>
