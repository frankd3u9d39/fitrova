<?php
// backend/login.php
require_once __DIR__ . '/../../../config/db_config.php';

header('Content-Type: application/json');

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing email or password"]);
    exit();
}

$email = trim($data['email']);
$password = $data['password'];

try {
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

    if ($user && password_verify($password, $user['password_hash'])) {
        // Successful login
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Login successful",
            "user" => [
                "id" => $user['id'],
                "firstName" => $user['first_name'],
                "lastName" => $user['last_name'],
                "email" => $user['email'],
                "isVerified" => (bool) $user['is_verified'],
                "surveyStep" => $user['survey_step'] ?? 'Personalization',
                "profile" => [
                    "age" => $user['age'],
                    "gender" => $user['gender'],
                    "height" => $user['height'],
                    "weight" => $user['weight'],
                    "activityLevel" => $user['activity_level'],
                    "goal" => $user['fitness_goal'], // Initial goal from Personalization
                    "selectedGoal" => $user['fitness_goal'], // From GoalSetting
                    "targetWeight" => $user['target_weight'],
                    "targetDate" => $user['target_date'],
                    "selectedDiet" => $user['diet_preference'],
                    "selectedAllergies" => $user['allergies'] ? json_decode($user['allergies']) : [],
                    "selectedConditions" => $user['medical_conditions'] ? json_decode($user['medical_conditions']) : []
                ]
            ]
        ]);
    } else {
        // Invalid credentials
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid email or password"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error during login"]);
}
?>