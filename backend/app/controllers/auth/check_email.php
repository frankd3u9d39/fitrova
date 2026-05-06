<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database configuration
require_once __DIR__ . '/../../../config/db_config.php';

// Abstract API configuration (Moved from frontend for security and reliability)
$ABSTRACT_API_KEY = '6ea8da050ed2489cbc0ab07df4b4602a';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['email'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email is required'
        ]);
        exit();
    }
    
    $email = trim($input['email']);
    
    // 1. Check if email exists in database
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $result = [
        'success' => true,
        'exists' => (bool)$user,
        'message' => $user ? 'Email already registered' : 'Email available',
        'verification' => null
    ];

    // 2. If email doesn't exist in DB, verify its validity via Abstract API
    if (!$user) {
        $url = "https://emailreputation.abstractapi.com/v1/?api_key=$ABSTRACT_API_KEY&email=" . urlencode($email);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Often needed for local XAMPP environments
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $http_code === 200) {
            $data = json_decode($response, true);
            
            // Extract relevant verification data logic
            $deliverability = $data['email_deliverability']['status'] ?? 'unknown';
            $quality = $data['email_quality'] ?? [];
            
            $result['verification'] = [
                'isValid' => ($data['email_deliverability']['is_format_valid']['value'] ?? true),
                'isDisposable' => ($data['is_disposable_email']['value'] ?? false) || ($quality['is_disposable']['value'] ?? false),
                'isFreeEmail' => ($data['is_free_email']['value'] ?? false) || ($quality['is_free_email']['value'] ?? false),
                'deliverable' => strtoupper($deliverability),
                'qualityScore' => (float)($quality['score'] ?? ($data['quality_score'] ?? 0.5))
            ];
        } else {
            // Fallback if API is unreachable
            $result['verification'] = [
                'isValid' => true,
                'isDisposable' => false,
                'isFreeEmail' => false,
                'deliverable' => 'UNKNOWN',
                'qualityScore' => 0.5,
                'api_error' => true
            ];
        }
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
