<?php
// backend/app/controllers/auth/google_auth.php
require_once __DIR__ . '/../../../config/db_config.php';

header('Content-Type: application/json');

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['idToken'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing Google ID Token"]);
    exit();
}

$idToken = trim($data['idToken']);

$isMockToken = false;
$tokenInfo = null;

// Enforce mock bypass for local development testing inside Expo Go
if (strpos($idToken, 'dev-mock-google-token-') === 0) {
    $isMockToken = true;
    if ($idToken === 'dev-mock-google-token-alex') {
        $tokenInfo = [
            'email' => 'alex.mercer@gmail.com',
            'given_name' => 'Alex',
            'family_name' => 'Mercer',
            'aud' => '43263891199-5ubrvske51q0a304rkpummdi6cfqk8io.apps.googleusercontent.com'
        ];
    } else if ($idToken === 'dev-mock-google-token-sarah') {
        $tokenInfo = [
            'email' => 'sarah.connor@gmail.com',
            'given_name' => 'Sarah',
            'family_name' => 'Connor',
            'aud' => '43263891199-5ubrvske51q0a304rkpummdi6cfqk8io.apps.googleusercontent.com'
        ];
    } else if (strpos($idToken, 'dev-mock-google-token-custom:') === 0) {
        $parts = explode(':', substr($idToken, strlen('dev-mock-google-token-custom:')));
        $tokenInfo = [
            'email' => $parts[0] ?? 'custom@gmail.com',
            'given_name' => $parts[1] ?? 'Google',
            'family_name' => $parts[2] ?? 'User',
            'aud' => '43263891199-5ubrvske51q0a304rkpummdi6cfqk8io.apps.googleusercontent.com'
        ];
    }
}

if (!$isMockToken) {
    // 1. Verify Google ID Token using Google API Token Info
    $verifyUrl = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($idToken);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verifyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$responseBody) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid Google ID Token. Verification failed."]);
        exit();
    }

    $tokenInfo = json_decode($responseBody, true);
}

if (!$tokenInfo || !isset($tokenInfo['email'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid token payload structure"]);
    exit();
}

// 2. Validate Audience (aud) to prevent spoofing from other apps
$allowedClients = [
    '43263891199-5ubrvske51q0a304rkpummdi6cfqk8io.apps.googleusercontent.com', // Web Client ID
    '43263891199-hiabdpdt09kdrkmn4ljnsjcfe0mis2l5.apps.googleusercontent.com'  // Android Client ID
];

$tokenAudience = $tokenInfo['aud'] ?? '';

if (!in_array($tokenAudience, $allowedClients)) {
    http_response_code(403);
    echo json_encode([
        "status" => "error", 
        "message" => "Unauthorized client application audience. Google Verification failed."
    ]);
    exit();
}

// Extract verified user profile details from token
$email = trim($tokenInfo['email']);
$firstName = isset($tokenInfo['given_name']) ? trim($tokenInfo['given_name']) : '';
$lastName = isset($tokenInfo['family_name']) ? trim($tokenInfo['family_name']) : '';

// Fallback to name parsing if first/last name are missing but full name exists
if (empty($firstName) && isset($tokenInfo['name'])) {
    $parts = explode(' ', trim($tokenInfo['name']), 2);
    $firstName = $parts[0];
    $lastName = $parts[1] ?? '';
}

if (empty($firstName)) {
    $firstName = 'Google';
}
if (empty($lastName)) {
    $lastName = 'User';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email format"]);
    exit();
}

try {
    // 1. Check if user already exists
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.password_hash, u.is_verified, 
               p.survey_step, p.age, p.gender, p.height, p.weight, p.activity_level, p.fitness_goal,
               p.target_weight, p.target_date, p.diet_preference, p.allergies, p.medical_conditions
        FROM users u 
        LEFT JOIN user_profiles p ON u.id = p.user_id 
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // User exists, log them in immediately
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Google Login successful",
            "user" => [
                "id" => (int)$user['id'],
                "firstName" => $user['first_name'],
                "lastName" => $user['last_name'],
                "email" => $user['email'],
                "isVerified" => (bool)$user['is_verified'],
                "surveyStep" => $user['survey_step'] ?? 'Personalization',
                "profile" => [
                    "age" => $user['age'] !== null ? (int)$user['age'] : null,
                    "gender" => $user['gender'],
                    "height" => $user['height'] !== null ? (float)$user['height'] : null,
                    "weight" => $user['weight'] !== null ? (float)$user['weight'] : null,
                    "activityLevel" => $user['activity_level'],
                    "goal" => $user['fitness_goal'],
                    "selectedGoal" => $user['fitness_goal'],
                    "targetWeight" => $user['target_weight'] !== null ? (float)$user['target_weight'] : null,
                    "targetDate" => $user['target_date'],
                    "selectedDiet" => $user['diet_preference'],
                    "selectedAllergies" => $user['allergies'] ? json_decode($user['allergies']) : [],
                    "selectedConditions" => $user['medical_conditions'] ? json_decode($user['medical_conditions']) : []
                ]
            ]
        ]);
    } else {
        // User does not exist, register them
        // Generate a secure random password for local hash safety
        $randomPassword = bin2hex(random_bytes(16));
        $passwordHash = password_hash($randomPassword, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, is_verified) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$firstName, $lastName, $email, $passwordHash]);
        
        $userId = $pdo->lastInsertId();

        // Initialize user profile default state so they start at Personalization step
        $stmtProfile = $pdo->prepare("INSERT INTO user_profiles (user_id, survey_step) VALUES (?, 'Personalization')");
        $stmtProfile->execute([$userId]);

        http_response_code(201); // Created
        echo json_encode([
            "status" => "success",
            "message" => "Google Sign-Up successful",
            "user" => [
                "id" => (int)$userId,
                "firstName" => $firstName,
                "lastName" => $lastName,
                "email" => $email,
                "isVerified" => true,
                "surveyStep" => "Personalization",
                "profile" => [
                    "age" => null,
                    "gender" => null,
                    "height" => null,
                    "weight" => null,
                    "activityLevel" => null,
                    "goal" => null,
                    "selectedGoal" => null,
                    "targetWeight" => null,
                    "targetDate" => null,
                    "selectedDiet" => null,
                    "selectedAllergies" => [],
                    "selectedConditions" => []
                ]
            ]
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error during Google Authentication: " . $e->getMessage()]);
}
?>
