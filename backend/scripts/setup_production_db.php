<?php
// backend/scripts/setup_production_db.php

require_once __DIR__ . '/../config/db_config.php';

echo "Initializing database tables...\n";

$queries = [
    // 1. users
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        is_verified TINYINT(1) DEFAULT 0,
        verification_code VARCHAR(6) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // 2. user_profiles
    "CREATE TABLE IF NOT EXISTS user_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        age INT DEFAULT NULL,
        gender VARCHAR(10) DEFAULT NULL,
        height DECIMAL(5,2) DEFAULT NULL,
        weight DECIMAL(5,2) DEFAULT NULL,
        current_weight DECIMAL(5,2) DEFAULT NULL,
        target_weight DECIMAL(5,2) DEFAULT NULL,
        activity_level VARCHAR(50) DEFAULT NULL,
        fitness_goal VARCHAR(100) DEFAULT NULL,
        target_date VARCHAR(50) DEFAULT NULL,
        diet_preference VARCHAR(100) DEFAULT NULL,
        allergies JSON DEFAULT NULL,
        medical_conditions JSON DEFAULT NULL,
        survey_step VARCHAR(50) DEFAULT 'Personalization',
        motto VARCHAR(255) DEFAULT 'Striving for 1% better every day',
        profile_picture TEXT DEFAULT NULL,
        daily_calorie_goal INT DEFAULT 2000,
        health_score INT DEFAULT 50,
        subscription_tier VARCHAR(20) DEFAULT 'free',
        trial_used TINYINT(1) DEFAULT 0,
        subscription_expiry DATETIME DEFAULT NULL,
        ai_engine VARCHAR(20) DEFAULT 'eco',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // 3. weight_history
    "CREATE TABLE IF NOT EXISTS weight_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        weight DECIMAL(5,2) NOT NULL,
        recorded_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_date (user_id, recorded_date)
    )",

    // 4. nutrition_logs
    "CREATE TABLE IF NOT EXISTS nutrition_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        meal_name VARCHAR(255),
        calories INT NOT NULL,
        protein DECIMAL(5,2) DEFAULT 0,
        carbs DECIMAL(5,2) DEFAULT 0,
        fats DECIMAL(5,2) DEFAULT 0,
        meal_type ENUM('breakfast', 'lunch', 'dinner', 'snack') DEFAULT 'snack',
        logged_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_date (user_id, logged_date)
    )",

    // 5. workout_plans
    "CREATE TABLE IF NOT EXISTS workout_plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        duration_minutes INT NOT NULL,
        difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'intermediate',
        workout_type ENUM('strength', 'cardio', 'flexibility', 'mixed') DEFAULT 'mixed',
        plan_date DATE DEFAULT NULL,
        plan_data TEXT DEFAULT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // 6. workout_logs
    "CREATE TABLE IF NOT EXISTS workout_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        workout_plan_id INT,
        workout_name VARCHAR(255) NOT NULL,
        duration_minutes INT NOT NULL,
        calories_burned INT DEFAULT 0,
        completed_date DATE NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (workout_plan_id) REFERENCES workout_plans(id) ON DELETE SET NULL,
        INDEX idx_user_date (user_id, completed_date)
    )",

    // 7. ai_insights
    "CREATE TABLE IF NOT EXISTS ai_insights (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        insight_text TEXT NOT NULL,
        insight_type ENUM('motivation', 'warning', 'achievement', 'tip') DEFAULT 'tip',
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_unread (user_id, is_read)
    )",

    // 8. personal_records
    "CREATE TABLE IF NOT EXISTS personal_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        exercise_name VARCHAR(100) NOT NULL,
        weight_kg DECIMAL(5,2) NOT NULL,
        recorded_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // 9. exercise_library
    "CREATE TABLE IF NOT EXISTS exercise_library (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        keywords TEXT NOT NULL,
        video_url TEXT,
        image_url TEXT,
        category ENUM('cardio', 'strength', 'core', 'recovery', 'full_body') DEFAULT 'strength',
        difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // 10. system_settings
    "CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        description TEXT,
        category ENUM('ai', 'system', 'ui', 'nutrition') DEFAULT 'system',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    // 11. queue
    "CREATE TABLE IF NOT EXISTS queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        handler VARCHAR(255) NOT NULL,
        payload TEXT NOT NULL,
        status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        attempts INT DEFAULT 0,
        last_error TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    // 12. pending_verifications
    "CREATE TABLE IF NOT EXISTS pending_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        code VARCHAR(6) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // 13. ai_food_recommendations
    "CREATE TABLE IF NOT EXISTS ai_food_recommendations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        recommendation_date DATE NOT NULL,
        recommendations_json TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY idx_user_date (user_id, recommendation_date)
    )",

    // 14. achievements
    "CREATE TABLE IF NOT EXISTS achievements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) UNIQUE NOT NULL,
        description TEXT NOT NULL,
        badge_icon VARCHAR(50) NOT NULL,
        points_required INT NOT NULL,
        criteria_type VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // 15. user_achievements
    "CREATE TABLE IF NOT EXISTS user_achievements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        achievement_id INT NOT NULL,
        earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
        UNIQUE KEY idx_user_achievement (user_id, achievement_id)
    )",

    // 16. payment_transactions
    "CREATE TABLE IF NOT EXISTS payment_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        reference VARCHAR(100) UNIQUE NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'NGN',
        subscription_tier VARCHAR(50) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        paystack_response JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

    // 17. form_check_logs
    "CREATE TABLE IF NOT EXISTS form_check_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        exercise_name VARCHAR(255),
        score INT,
        status VARCHAR(50),
        summary TEXT,
        tips TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )"
];

foreach ($queries as $index => $query) {
    try {
        $pdo->exec($query);
        echo "✅ Table " . ($index + 1) . " created/verified successfully.\n";
    } catch (PDOException $e) {
        die("❌ Table creation failed on query " . ($index + 1) . ": " . $e->getMessage() . "\n");
    }
}

// Self-Heal existing tables by adding columns that might be missing
$alterations = [
    "ALTER TABLE user_profiles ADD COLUMN subscription_tier VARCHAR(20) DEFAULT 'free'",
    "ALTER TABLE user_profiles ADD COLUMN trial_used TINYINT(1) DEFAULT 0",
    "ALTER TABLE user_profiles ADD COLUMN subscription_expiry DATETIME DEFAULT NULL",
    "ALTER TABLE user_profiles ADD COLUMN ai_engine VARCHAR(20) DEFAULT 'eco'",
    "ALTER TABLE workout_plans ADD COLUMN plan_date DATE DEFAULT NULL",
    "ALTER TABLE workout_plans ADD COLUMN plan_data TEXT DEFAULT NULL"
];

foreach ($alterations as $alteration) {
    try {
        $pdo->exec($alteration);
    } catch (PDOException $e) {
        // Safe to ignore if column already exists
    }
}

echo "\nSeeding default database values...\n";

// Seed default Admin User
$adminHash = password_hash('adminpassword123', PASSWORD_BCRYPT);
try {
    $pdo->prepare("INSERT IGNORE INTO users (email, password_hash, first_name, last_name, is_verified) VALUES (?, ?, 'Admin', 'User', 1)")
        ->execute(['admin@fitrova.com', $adminHash]);
    echo "✅ Seeded default admin account (admin@fitrova.com / adminpassword123).\n";
} catch (PDOException $e) {
    echo "ℹ️ Admin user seeding skipped or already exists.\n";
}

// Seed default achievements
$achievements = [
    ['First Workout', 'Complete your first workout session', '🏆', 1, 'workouts_completed'],
    ['Week Warrior', 'Complete 7 workouts in a week', '🔥', 7, 'workouts_completed'],
    ['Monthly Master', 'Complete 20 workouts in a month', '👑', 20, 'workouts_completed'],
    ['Weight Loss Goal', 'Lose 5kg of body weight', '⚖️', 5, 'weight_lost'],
    ['Nutrition Expert', 'Log 50 meals', '🍎', 50, 'meals_logged'],
    ['Streak Keeper', 'Maintain a 30-day workout streak', '📅', 30, 'streak_days'],
    ['Form Perfectionist', 'Score 90+ on form check', '🎯', 90, 'form_score'],
    ['YouTube Analyzer', 'Analyze 10 YouTube workouts', '📹', 10, 'videos_analyzed']
];

foreach ($achievements as $ach) {
    try {
        $pdo->prepare("INSERT IGNORE INTO achievements (name, description, badge_icon, points_required, criteria_type) VALUES (?, ?, ?, ?, ?)")
            ->execute($ach);
    } catch (PDOException $e) {
        // Suppress
    }
}
echo "✅ Seeded achievements library.\n";

// Seed default exercises
$exercises = [
    ['Bench Press', 'bench press,chest press,barbell press', 'https://www.youtube.com/watch?v=gRVjAtPip0Y', 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800', 'strength'],
    ['Push Ups', 'push up,pushup,push-up', 'https://www.youtube.com/watch?v=IODxDxX7oi4', 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800', 'strength'],
    ['Squats', 'squat,back squat,goblet squat', 'https://www.youtube.com/watch?v=aclHkVaku9U', 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800', 'strength'],
    ['Plank', 'plank,forearm plank', 'https://www.youtube.com/watch?v=ASdvN_XEl_c', 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800', 'core']
];

foreach ($exercises as $ex) {
    try {
        $pdo->prepare("INSERT IGNORE INTO exercise_library (name, keywords, video_url, image_url, category) VALUES (?, ?, ?, ?, ?)")
            ->execute($ex);
    } catch (PDOException $e) {
        // Suppress
    }
}
echo "✅ Seeded exercises library.\n";

// Seed system settings
$settings = [
    ['ai_model_primary', 'gemini-1.5-flash', 'The main AI model for workout generation', 'ai'],
    ['ai_system_prompt', 'You are a professional fitness trainer. Generate a personalized workout plan based on the users nutrition and weight trends.', 'The base personality of the AI', 'ai'],
    ['ai_temperature', '0.7', 'Creativity level of the AI (0.0 to 1.0)', 'ai'],
    ['maintenance_mode', 'false', 'Disable app access for maintenance', 'system']
];

foreach ($settings as $set) {
    try {
        $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, description, category) VALUES (?, ?, ?, ?)")
            ->execute($set);
    } catch (PDOException $e) {
        // Suppress
    }
}
echo "✅ Seeded system settings.\n";

echo "\n🎉 Database initialization finished successfully!\n";
