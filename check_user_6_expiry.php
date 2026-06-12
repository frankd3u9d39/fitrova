<?php
require_once __DIR__ . '/backend/config/db_config.php';
$up = $pdo->query("SELECT user_id, subscription_tier, subscription_expiry, scan_trial_used, trial_used, form_trial_used, diet_trial_used FROM user_profiles WHERE user_id = 6")->fetch();
print_r($up);
?>
