<?php
// backend/save_profile.php
require_once __DIR__ . '/../../../config/db_config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['userId'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing userId parameter"]);
    exit();
}

$user_id = $data['userId'];
$survey_step = $data['survey_step'] ?? 'Personalization';
$age = isset($data['age']) ? (int)$data['age'] : null;
$gender = $data['gender'] ?? null;
$height = isset($data['height']) ? (float)$data['height'] : null;
$weight = isset($data['weight']) ? (float)$data['weight'] : null;
$activity_level = $data['activityLevel'] ?? null;
$fitness_goal = $data['selectedGoal'] ?? null;
$target_weight = isset($data['targetWeight']) ? (float)$data['targetWeight'] : null;
$target_date = $data['targetDate'] ?? null;
$diet_preference = $data['selectedDiet'] ?? null;
$allergies = isset($data['selectedAllergies']) ? json_encode($data['selectedAllergies']) : null;
$medical_conditions = isset($data['selectedConditions']) ? json_encode($data['selectedConditions']) : null;
$has_equipment = isset($data['hasEquipment']) ? (int)$data['hasEquipment'] : null;
$subscription_tier = $data['subscription_tier'] ?? null;
$subscription_expiry = $data['subscription_expiry'] ?? null;

try {
    // If a value is provided, it replaces it. If null, we want COALESCE so it keeps the original value during updates.
    $stmt = $pdo->prepare("
        INSERT INTO user_profiles 
        (user_id, age, gender, height, weight, activity_level, fitness_goal, target_weight, target_date, diet_preference, allergies, medical_conditions, has_equipment, survey_step, subscription_tier, subscription_expiry) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        age=COALESCE(VALUES(age), age), 
        gender=COALESCE(VALUES(gender), gender), 
        height=COALESCE(VALUES(height), height), 
        weight=COALESCE(VALUES(weight), weight), 
        activity_level=COALESCE(VALUES(activity_level), activity_level), 
        fitness_goal=COALESCE(VALUES(fitness_goal), fitness_goal), 
        target_weight=COALESCE(VALUES(target_weight), target_weight), 
        target_date=COALESCE(VALUES(target_date), target_date), 
        diet_preference=COALESCE(VALUES(diet_preference), diet_preference), 
        allergies=COALESCE(VALUES(allergies), allergies), 
        medical_conditions=COALESCE(VALUES(medical_conditions), medical_conditions),
        has_equipment=COALESCE(VALUES(has_equipment), has_equipment),
        survey_step=COALESCE(VALUES(survey_step), survey_step),
        subscription_tier=COALESCE(VALUES(subscription_tier), subscription_tier),
        subscription_expiry=COALESCE(VALUES(subscription_expiry), subscription_expiry)
    ");
    
    $stmt->execute([
        $user_id, $age, $gender, $height, $weight, $activity_level, $fitness_goal, 
        $target_weight, $target_date, $diet_preference, $allergies, $medical_conditions, $has_equipment, $survey_step,
        $subscription_tier, $subscription_expiry
    ]);
    
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Profile saved successfully"
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error while saving profile"]);
}
?>
