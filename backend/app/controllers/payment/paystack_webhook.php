<?php
// backend/app/controllers/payment/paystack_webhook.php
header('Content-Type: application/json');

// Webhook payload parsing
$input = file_get_contents('php://input');

require_once __DIR__ . '/../../../config/db_config.php';
require_once __DIR__ . '/../../helpers/PaymentGuard.php';

try {
    // Enforce global payment kill-switch.
    // For webhooks, when disabled we still return 200 to Paystack
    // (so they don't retry) but we skip all DB mutations.
    $paymentsGateStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'payments_enabled' LIMIT 1");
    $paymentsEnabled  = $paymentsGateStmt ? $paymentsGateStmt->fetchColumn() : 'true';
    if ($paymentsEnabled === 'false') {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Webhook acknowledged (payments paused)']);
        exit();
    }
    // 1. Fetch Paystack secret key from system_settings to verify signature
    $settingsStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'paystack_secret_key'");
    $paystackSecretKey = $settingsStmt->fetchColumn();

    if (empty($paystackSecretKey)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Paystack webhook verification key not found']);
        exit();
    }

    // 2. Validate Paystack Signature Header
    $paystackHeaderSignature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
    $calculatedSignature = hash_hmac('sha512', $input, $paystackSecretKey);

    if ($paystackHeaderSignature !== $calculatedSignature) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized signature mismatch']);
        exit();
    }

    // 3. Process the event payload
    $event = json_decode($input, true);
    $eventType = $event['event'] ?? '';

    if ($eventType === 'charge.success') {
        $paystackData = $event['data'];
        $reference = $paystackData['reference'] ?? '';
        $status = $paystackData['status'] ?? '';
        
        $userId = intval($paystackData['metadata']['user_id'] ?? 0);
        $tier = trim(strtolower($paystackData['metadata']['subscription_tier'] ?? 'premium'));

        if ($status === 'success' && !empty($reference)) {
            // Retrieve user details locally if missing from metadata
            if (!$userId) {
                $ledgerStmt = $pdo->prepare("SELECT user_id, subscription_tier FROM payment_transactions WHERE reference = ?");
                $ledgerStmt->execute([$reference]);
                $ledger = $ledgerStmt->fetch(PDO::FETCH_ASSOC);
                if ($ledger) {
                    $userId = intval($ledger['user_id']);
                    $tier = strtolower($ledger['subscription_tier']);
                }
            }

            if ($userId) {
                // Update local transaction ledger status to 'success'
                $updateLedger = $pdo->prepare("
                    UPDATE payment_transactions 
                    SET status = 'success', paystack_response = ? 
                    WHERE reference = ?
                ");
                $updateLedger->execute([$input, $reference]);

                // Update user profile to active paid subscription state (30-day membership length)
                $expiryDate = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $updateProfile = $pdo->prepare("
                    UPDATE user_profiles 
                    SET subscription_tier = ?, subscription_expiry = ?, ai_engine = 'premium' 
                    WHERE user_id = ?
                ");
                $updateProfile->execute([$tier, $expiryDate, $userId]);
            }
        }
    }

    // Paystack requires responding with 200 OK to acknowledge all webhook deliveries
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Event acknowledged']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
