<?php
require_once __DIR__ . '/../config/db_config.php';

try {
    echo "=== LOCAL TABLES ===\n";
    $q = $pdo->query("SHOW TABLES");
    while($row = $q->fetch(PDO::FETCH_NUM)) {
        echo $row[0] . "\n";
    }

    echo "\n=== AIVEN TABLES ===\n";
    $host = 'fitroval-db123-ibehpromise30-af36.g.aivencloud.com';
    $port = '11816';
    $dbname = 'defaultdb';
    $username = 'avnadmin';
    $password = 'AVNS_Sz6-RnTLGjBHbi49wvp';
    $apdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $aq = $apdo->query("SHOW TABLES");
    while($row = $aq->fetch(PDO::FETCH_NUM)) {
        echo $row[0] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
