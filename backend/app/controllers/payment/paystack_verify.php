<?php
// backend/app/controllers/payment/paystack_verify.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Prevent warnings breaking JSON output
error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';
require_once __DIR__ . '/../../helpers/PaymentGuard.php';

try {
    // Enforce global payment kill-switch before any processing
    enforcePaymentGate($pdo);
    $input = json_decode(file_get_contents('php://input'), true);
    $reference = isset($input['reference']) ? trim($input['reference']) : null;

    if (empty($reference)) {
        throw new Exception('Transaction reference is required');
    }

    // 1. Fetch Paystack keys from system_settings
    $settingsStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'paystack_secret_key'");
    $paystackSecretKey = $settingsStmt->fetchColumn();

    if (empty($paystackSecretKey)) {
        throw new Exception('Paystack billing system is temporarily unconfigured');
    }

    // 2. Query Paystack Transaction Verification Endpoint
    $url = "https://api.paystack.co/transaction/verify/" . urlencode($reference);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local XAMPP compatibility
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $paystackSecretKey,
        "Cache-Control: no-cache"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Paystack Verification request failed: HTTP {$httpCode} | {$curlError}");
    }

    $result = json_decode($response, true);
    if (!$result['status'] || $result['data']['status'] !== 'success') {
        // Mark transaction as failed locally if Paystack marks it failed
        $updateFail = $pdo->prepare("UPDATE payment_transactions SET status = 'failed', paystack_response = ? WHERE reference = ?");
        $updateFail->execute([$response, $reference]);
        
        throw new Exception("Transaction verification failed: " . ($result['data']['gateway_response'] ?? 'Not successful'));
    }

    // 3. Process Successful Transaction
    $paystackData = $result['data'];
    $userId = intval($paystackData['metadata']['user_id'] ?? 0);
    $tier = trim(strtolower($paystackData['metadata']['subscription_tier'] ?? 'premium'));
    $amountChargedKobo = intval($paystackData['amount']);
    $currency = $paystackData['currency'] ?? 'NGN';

    if (!$userId) {
        // Fallback: search our local payment_transactions ledger if metadata is missing
        $ledgerStmt = $pdo->prepare("SELECT user_id, subscription_tier FROM payment_transactions WHERE reference = ?");
        $ledgerStmt->execute([$reference]);
        $ledger = $ledgerStmt->fetch(PDO::FETCH_ASSOC);
        if ($ledger) {
            $userId = intval($ledger['user_id']);
            $tier = strtolower($ledger['subscription_tier']);
        } else {
            throw new Exception("Unable to associate transaction with a local user");
        }
    }

    // 4. Update ledger database transaction records
    $updateLedger = $pdo->prepare("
        UPDATE payment_transactions 
        SET status = 'success', paystack_response = ? 
        WHERE reference = ?
    ");
    $updateLedger->execute([$response, $reference]);

    // 5. Update user profile to active paid subscription state (30-day membership length)
    $expiryDate = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $updateProfile = $pdo->prepare("
        UPDATE user_profiles 
        SET subscription_tier = ?, subscription_expiry = ?, ai_engine = 'premium' 
        WHERE user_id = ?
    ");
    $updateProfile->execute([$tier, $expiryDate, $userId]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Payment verified and subscription activated successfully',
        'data' => [
            'reference' => $reference,
            'user_id' => $userId,
            'subscription_tier' => $tier,
            'subscription_expiry' => $expiryDate,
            'currency' => $currency,
            'amount_kobo' => $amountChargedKobo
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
