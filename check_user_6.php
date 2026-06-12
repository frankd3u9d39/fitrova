<?php
require_once __DIR__ . '/backend/config/db_config.php';

$u = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE id = 6")->fetch();
print_r($u);

$up = $pdo->query("SELECT user_id, subscription_tier FROM user_profiles WHERE user_id = 6")->fetch();
print_r($up);
?>
