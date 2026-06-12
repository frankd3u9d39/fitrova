<?php
require 'backend/config/db_config.php';

$sqls = [
    "ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS unit_preference ENUM('metric','imperial') DEFAULT 'metric'",
    "ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS notification_enabled TINYINT(1) DEFAULT 1",
    "ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS language VARCHAR(10) DEFAULT 'en'",
];

foreach ($sqls as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: $sql\n";
    } catch (Exception $e) {
        echo "SKIP: " . $e->getMessage() . "\n";
    }
}
echo "Migration done.\n";
