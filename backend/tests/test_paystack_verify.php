<?php
// backend/tests/test_paystack_verify.php

require_once __DIR__ . '/../config/db_config.php';

$reference = 'FTR-6A1CD0983C90B-1780273304';

try {
    echo "Starting test verification for reference: $reference\n";

    // 1. Fetch Paystack keys from system_settings
    $settingsStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'paystack_secret_key'");
    $paystackSecretKey = $settingsStmt->fetchColumn();

    if (empty($paystackSecretKey)) {
        throw new Exception('Paystack billing system is temporarily unconfigured');
    }
    
    echo "Paystack Secret Key: " . substr($paystackSecretKey, 0, 10) . "...\n";

    // 2. Query Paystack Transaction Verification Endpoint
    $url = "https://api.paystack.co/transaction/verify/" . urlencode($reference);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $paystackSecretKey,
        "Cache-Control: no-cache"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    echo "Paystack API Response Code: $httpCode\n";
    if ($curlError) {
        echo "Curl Error: $curlError\n";
    }

    if ($httpCode !== 200) {
        throw new Exception("Paystack Verification request failed: HTTP {$httpCode} | {$curlError} | Response: $response");
    }

    $result = json_decode($response, true);
    if (!$result['status'] || $result['data']['status'] !== 'success') {
        throw new Exception("Transaction verification failed");
    }

    echo "Paystack status checked successful.\n";

    // 3. Process Successful Transaction
    $paystackData = $result['data'];
    $userId = intval($paystackData['metadata']['user_id'] ?? 0);
    $tier = trim(strtolower($paystackData['metadata']['subscription_tier'] ?? 'premium'));
    $amountChargedKobo = intval($paystackData['amount']);
    $currency = $paystackData['currency'] ?? 'NGN';

    echo "Parsed Metadata -> User ID: $userId, Tier: $tier, Amount: $amountChargedKobo kobo\n";

    if (!$userId) {
        // Fallback: search our local payment_transactions ledger if metadata is missing
        $ledgerStmt = $pdo->prepare("SELECT user_id, subscription_tier FROM payment_transactions WHERE reference = ?");
        $ledgerStmt->execute([$reference]);
        $ledger = $ledgerStmt->fetch(PDO::FETCH_ASSOC);
        if ($ledger) {
            $userId = intval($ledger['user_id']);
            $tier = strtolower($ledger['subscription_tier']);
            echo "Ledger Fallback Found -> User ID: $userId, Tier: $tier\n";
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
    echo "Updated local payment_transactions ledger.\n";

    // 5. Update user profile to active paid subscription state (30-day membership length)
    $expiryDate = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $updateProfile = $pdo->prepare("
        UPDATE user_profiles 
        SET subscription_tier = ?, subscription_expiry = ? 
        WHERE user_id = ?
    ");
    $updateProfile->execute([$tier, $expiryDate, $userId]);
    echo "Updated user_profiles table successfully.\n";

    echo "ALL VERIFICATION STEPS PASSED SUCCESSFULLY!\n";

} catch (Exception $e) {
    echo "EXCEPTION TRIGGERED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
