<?php
require_once __DIR__ . '/../backend/config/db_config.php';

echo "=== ALL TABLES IN DATABASE ===\n";
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    print_r($tables);
    
    foreach ($tables as $t) {
        echo "\n--- Schema for table $t ---\n";
        $describe = $pdo->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($describe as $col) {
            echo "  {$col['Field']} ({$col['Type']})\n";
        }
        $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "  Total Rows: $count\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
