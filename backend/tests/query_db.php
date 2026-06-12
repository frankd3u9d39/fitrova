<?php
require_once __DIR__ . '/../config/db_config.php';

echo "=== USERS ===\n";
$users = $pdo->query("SELECT id, first_name, last_name, email FROM users")->fetchAll();
print_r($users);

echo "=== USER PROFILES ===\n";
$profiles = $pdo->query("SELECT id, user_id, age, gender, height, current_weight, weight, fitness_goal, diet_preference, allergies, medical_conditions FROM user_profiles")->fetchAll();
print_r($profiles);

echo "=== RECENT MEALS (Last 7 Days) ===\n";
$meals = $pdo->query("SELECT id, user_id, meal_name, calories, protein, carbs, fats, meal_type, logged_date FROM nutrition_logs ORDER BY id DESC LIMIT 10")->fetchAll();
print_r($meals);

echo "=== AI INSIGHTS ===\n";
$insights = $pdo->query("SELECT id, user_id, insight_text, insight_type, is_read, created_at FROM ai_insights ORDER BY id DESC LIMIT 5")->fetchAll();
print_r($insights);
