<?php
require_once __DIR__ . '/backend/config/db_config.php';

$sql = file_get_contents(__DIR__ . '/database/migrations/v2_enterprise_upgrade.sql');

try {
    $pdo->exec($sql);
    echo "Migration Successful: Enterprise Tables Created.\n";
} catch (PDOException $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
}
