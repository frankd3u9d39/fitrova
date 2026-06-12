<?php
require 'backend/config/db_config.php';

echo "=== USERS TABLE ===\n";
$r = $pdo->query('DESCRIBE users');
foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo $col['Field'] . ' (' . $col['Type'] . ")\n";
}

echo "\n=== USER_PROFILES TABLE ===\n";
$r2 = $pdo->query('DESCRIBE user_profiles');
foreach ($r2->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo $col['Field'] . ' (' . $col['Type'] . ")\n";
}
