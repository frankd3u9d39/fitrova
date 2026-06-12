<?php
// backend/app/controllers/payment/paystack_initialize.php
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
    
    $userId = isset($input['user_id']) ? intval($input['user_id']) : null;
    $tier = isset($input['subscription_tier']) ? trim(strtolower($input['subscription_tier'])) : 'premium';

    if (!$userId) {
        throw new Exception('User ID is required');
    }

    if ($tier !== 'premium' && $tier !== 'advanced_premium') {
        throw new Exception('Invalid subscription tier requested');
    }

    // 1. Fetch user's email from the 'users' table
    $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['email'])) {
        throw new Exception('User email not found in database');
    }
    
    $email = $user['email'];

    // 2. Fetch Paystack keys from system_settings
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('paystack_public_key', 'paystack_secret_key')");
    $settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $paystackSecretKey = $settings['paystack_secret_key'] ?? '';

    if (empty($paystackSecretKey)) {
        throw new Exception('Paystack billing system is temporarily unconfigured');
    }

    // 3. Determine Naira Pricing (Converted to Kobo for Paystack API: 1 NGN = 100 kobo)
    $amountInNaira = 1500.00; // Premium Tier
    if ($tier === 'advanced_premium') {
        $amountInNaira = 3000.00; // Advanced Premium Tier
    }
    $amountInKobo = intval($amountInNaira * 100);

    // 4. Generate unique transaction reference
    $reference = "FTR-" . strtoupper(uniqid()) . "-" . time();

    // 5. Query Paystack Transaction Initialization
    $url = "https://api.paystack.co/transaction/initialize";
    $fields = [
        'email' => $email,
        'amount' => $amountInKobo,
        'reference' => $reference,
        'callback_url' => 'http://10.45.232.176:8082/Fitrova/backend/app/controllers/payment/paystack_callback.php',
        'metadata' => [
            'user_id' => $userId,
            'subscription_tier' => $tier
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local XAMPP compatibility
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $paystackSecretKey,
        "Content-Type: application/json",
        "Cache-Control: no-cache"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Paystack Initialization failed: HTTP {$httpCode} | {$curlError} | {$response}");
    }

    $result = json_decode($response, true);
    if (!$result['status']) {
        throw new Exception("Paystack returned error status: " . ($result['message'] ?? 'Unknown error'));
    }

    $authorizationUrl = $result['data']['authorization_url'];

    // 6. Log transaction to payment_transactions ledger
    $saveStmt = $pdo->prepare("
        INSERT INTO payment_transactions (user_id, reference, amount, currency, subscription_tier, status)
        VALUES (?, ?, ?, 'NGN', ?, 'pending')
    ");
    $saveStmt->execute([$userId, $reference, $amountInNaira, $tier]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Paystack transaction initialized',
        'data' => [
            'authorization_url' => $authorizationUrl,
            'reference' => $reference,
            'amount' => $amountInNaira,
            'currency' => 'NGN'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
