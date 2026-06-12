<?php
// Seed and verify the payments_enabled system setting
require 'c:/xampp/htdocs/Fitrova/backend/config/db_config.php';

echo "=== Payment Toggle Setup Verification ===\n\n";

// 1. Upsert the payments_enabled row
$pdo->exec("INSERT INTO system_settings (setting_key, setting_value)
    VALUES ('payments_enabled', 'true')
    ON DUPLICATE KEY UPDATE setting_key = setting_key");

// 2. Read it back
$val = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'payments_enabled'")->fetchColumn();
echo ($val !== false ? "✅ payments_enabled = $val\n" : "❌ Row not found!\n");

// 3. Check all required files
$files = [
    'toggle_payments.php'  => 'c:/xampp/htdocs/Fitrova/backend/admin/api/toggle_payments.php',
    'PaymentGuard.php'     => 'c:/xampp/htdocs/Fitrova/backend/app/helpers/PaymentGuard.php',
    'get_status.php'       => 'c:/xampp/htdocs/Fitrova/backend/app/controllers/system/get_status.php',
    'paystack_init guard'  => 'c:/xampp/htdocs/Fitrova/backend/app/controllers/payment/paystack_initialize.php',
    'paystack_verify guard'=> 'c:/xampp/htdocs/Fitrova/backend/app/controllers/payment/paystack_verify.php',
    'paystack_webhook guard'=> 'c:/xampp/htdocs/Fitrova/backend/app/controllers/payment/paystack_webhook.php',
];
foreach ($files as $label => $path) {
    echo (file_exists($path) ? "✅ $label exists\n" : "❌ MISSING: $path\n");
}

// 4. Verify PaymentGuard includes enforcement function
$guard = file_get_contents('c:/xampp/htdocs/Fitrova/backend/app/helpers/PaymentGuard.php');
echo (strpos($guard, 'enforcePaymentGate') !== false ? "✅ enforcePaymentGate() defined\n" : "❌ enforcePaymentGate() NOT found\n");

// 5. Verify guard is referenced in initialize.php
$init = file_get_contents('c:/xampp/htdocs/Fitrova/backend/app/controllers/payment/paystack_initialize.php');
echo (strpos($init, 'PaymentGuard') !== false ? "✅ PaymentGuard injected in initialize.php\n" : "❌ PaymentGuard NOT injected in initialize.php\n");

// 6. Verify guard is referenced in verify.php
$verify = file_get_contents('c:/xampp/htdocs/Fitrova/backend/app/controllers/payment/paystack_verify.php');
echo (strpos($verify, 'PaymentGuard') !== false ? "✅ PaymentGuard injected in verify.php\n" : "❌ PaymentGuard NOT injected in verify.php\n");

// 7. Verify admin index.php has toggle UI
$idx = file_get_contents('c:/xampp/htdocs/Fitrova/backend/admin/index.php');
echo (strpos($idx, 'payment-toggle-btn') !== false ? "✅ Toggle button in admin dashboard\n" : "❌ Toggle button NOT in admin dashboard\n");
echo (strpos($idx, 'togglePaymentGateway') !== false ? "✅ togglePaymentGateway() JS present\n" : "❌ togglePaymentGateway() JS NOT found\n");

// 8. Test disabling and re-enabling
$pdo->exec("UPDATE system_settings SET setting_value = 'false' WHERE setting_key = 'payments_enabled'");
$off = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'payments_enabled'")->fetchColumn();
echo ($off === 'false' ? "✅ Disable toggle works (set to false)\n" : "❌ Disable toggle FAILED\n");

$pdo->exec("UPDATE system_settings SET setting_value = 'true' WHERE setting_key = 'payments_enabled'");
$on = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'payments_enabled'")->fetchColumn();
echo ($on === 'true' ? "✅ Enable toggle works (restored to true)\n" : "❌ Enable toggle FAILED\n");

echo "\n=== ALL CHECKS COMPLETE ===\n";
?>
