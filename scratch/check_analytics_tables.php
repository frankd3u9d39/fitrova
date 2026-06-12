<?php
require_once __DIR__ . '/../backend/config/db_config.php';
echo "Columns in payment_transactions:\n";
$stmt = $pdo->query("DESCRIBE payment_transactions");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\nTotal revenue by tier in database:\n";
$stmt = $pdo->query("SELECT subscription_tier, SUM(amount) as total_rev FROM payment_transactions WHERE status = 'success' GROUP BY subscription_tier");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
