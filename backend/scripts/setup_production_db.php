<?php
// backend/scripts/setup_production_db.php

require_once __DIR__ . '/../config/db_config.php';

// Drop legacy achievements schema to let it rebuild with correct columns
try {
    $checkLegacy = $pdo->query("SHOW COLUMNS FROM achievements LIKE 'badge_icon'")->fetch();
    if ($checkLegacy) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $pdo->exec("DROP TABLE IF EXISTS user_achievements;");
        $pdo->exec("DROP TABLE IF EXISTS achievements;");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        echo "Dropped legacy achievements tables.\n";
    }
} catch (PDOException $e) {
    // Table doesn't exist yet
}

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
        title VARCHAR(100) NOT NULL,
        description VARCHAR(255) NOT NULL,
        icon VARCHAR(50) NOT NULL,
        category ENUM('Training','Nutrition','Milestones') NOT NULL,
        color VARCHAR(7) DEFAULT '#D1FAE5',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // 15. user_achievements
    "CREATE TABLE IF NOT EXISTS user_achievements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        achievement_id INT NOT NULL,
        unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
    )",

    // 18. user_challenges
    "CREATE TABLE IF NOT EXISTS user_challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        challenge_key VARCHAR(100) NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY idx_user_challenge (user_id, challenge_key)
    )",

    // 19. user_connections
    "CREATE TABLE IF NOT EXISTS user_connections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        requester_id INT NOT NULL,
        receiver_id INT NOT NULL,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY idx_user_connection (requester_id, receiver_id)
    )",

    // 20. challenge_messages
    "CREATE TABLE IF NOT EXISTS challenge_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        challenge_key VARCHAR(100) NOT NULL,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        parent_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES challenge_messages(id) ON DELETE SET NULL
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
    "ALTER TABLE user_profiles ADD COLUMN has_equipment TINYINT(1) DEFAULT 0",
    "ALTER TABLE user_profiles ADD COLUMN unit_preference ENUM('metric','imperial') DEFAULT 'metric'",
    "ALTER TABLE user_profiles ADD COLUMN notification_enabled TINYINT(1) DEFAULT 1",
    "ALTER TABLE user_profiles ADD COLUMN language VARCHAR(10) DEFAULT 'en'",
    "ALTER TABLE user_profiles ADD COLUMN form_trial_used TINYINT(1) DEFAULT 0",
    "ALTER TABLE user_profiles ADD COLUMN scan_trial_used TINYINT(1) DEFAULT 0",
    "ALTER TABLE user_profiles ADD COLUMN diet_trial_used TINYINT(1) DEFAULT 0",
    "ALTER TABLE user_profiles MODIFY COLUMN profile_picture LONGTEXT DEFAULT NULL",
    "ALTER TABLE workout_plans ADD COLUMN plan_date DATE DEFAULT NULL",
    "ALTER TABLE workout_plans ADD COLUMN plan_data TEXT DEFAULT NULL",
    "ALTER TABLE workout_plans MODIFY COLUMN workout_type VARCHAR(50) DEFAULT 'mixed'",
    "ALTER TABLE challenge_messages ADD COLUMN parent_id INT DEFAULT NULL",
    "ALTER TABLE challenge_messages ADD FOREIGN KEY (parent_id) REFERENCES challenge_messages(id) ON DELETE SET NULL"
];

foreach ($alterations as $alteration) {
    try {
        $pdo->exec($alteration);
    } catch (PDOException $e) {
        // Safe to ignore if column already exists or is already modified
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

// Seed default test users for community features
$testUsers = [
    ['email' => 'sarah.j@fitrova.com', 'first_name' => 'Sarah', 'last_name' => 'Jenkins', 'profile_picture' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150', 'motto' => 'Lover of cardio and early runs!'],
    ['email' => 'michael.c@fitrova.com', 'first_name' => 'Michael', 'last_name' => 'Chen', 'profile_picture' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150', 'motto' => 'Consistency over intensity.'],
    ['email' => 'jessica.t@fitrova.com', 'first_name' => 'Jessica', 'last_name' => 'Taylor', 'profile_picture' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=150', 'motto' => 'Building strong habits everyday.'],
    ['email' => 'david.r@fitrova.com', 'first_name' => 'David', 'last_name' => 'Ross', 'profile_picture' => null, 'motto' => 'Pumping iron and hitting PRs!'],
    ['email' => 'emily.d@fitrova.com', 'first_name' => 'Emily', 'last_name' => 'Davis', 'profile_picture' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150', 'motto' => 'Hydrated and happy!']
];

$dummyHash = password_hash('password123', PASSWORD_BCRYPT);
foreach ($testUsers as $tu) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (email, password_hash, first_name, last_name, is_verified) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$tu['email'], $dummyHash, $tu['first_name'], $tu['last_name']]);
        
        // Find user_id
        $userIdStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $userIdStmt->execute([$tu['email']]);
        $tuId = $userIdStmt->fetchColumn();
        
        if ($tuId) {
            // Seed profile
            $profileStmt = $pdo->prepare("INSERT IGNORE INTO user_profiles (user_id, motto, profile_picture) VALUES (?, ?, ?)");
            $profileStmt->execute([$tuId, $tu['motto'], $tu['profile_picture']]);
        }
    } catch (PDOException $e) {
        // Suppress
    }
}
echo "✅ Seeded default test users for community.\n";

// Seed default challenge participation
$challengeKeys = ['weight_shred_loss', 'muscle_growth_bulk', 'weight_maintenance_stabilize', 'hiit_stamina_blast', 'cardio_consistency_run', 'hydration_hero_water'];
try {
    // Fetch all seeded test users
    $uStmt = $pdo->query("SELECT id FROM users WHERE email IN ('sarah.j@fitrova.com', 'michael.c@fitrova.com', 'jessica.t@fitrova.com', 'david.r@fitrova.com', 'emily.d@fitrova.com')");
    $testUserIds = $uStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($testUserIds)) {
        foreach ($challengeKeys as $ck) {
            foreach ($testUserIds as $idx => $tuId) {
                if (($idx + strlen($ck)) % 2 == 0) {
                    $pdo->prepare("INSERT IGNORE INTO user_challenges (user_id, challenge_key) VALUES (?, ?)")
                        ->execute([$tuId, $ck]);
                }
            }
        }
    }
    echo "✅ Seeded test users participation in challenges.\n";
} catch (PDOException $e) {
    // Suppress
}

// Seed default chat messages
$initialMessages = [
    'weight_shred_loss' => [
        ['name' => 'Sarah', 'msg' => 'Struggling to stay under my calories today, but going strong!'],
        ['name' => 'Michael', 'msg' => 'Keep it up Sarah! Try drinking some green tea, helps curb hunger.']
    ],
    'muscle_growth_bulk' => [
        ['name' => 'David', 'msg' => 'Hit a new bench PR today! 90kg for reps!'],
        ['name' => 'Michael', 'msg' => 'Huge lift David! Bulking season is paying off.']
    ],
    'hydration_hero_water' => [
        ['name' => 'Emily', 'msg' => 'Just finished my 3rd liter! Feeling so much more energetic.'],
        ['name' => 'Jessica', 'msg' => 'Same here! It is crazy how much hydration affects focus.']
    ]
];

try {
    foreach ($initialMessages as $ck => $msgs) {
        foreach ($msgs as $m) {
            // Find user id by first name
            $uIdStmt = $pdo->prepare("SELECT id FROM users WHERE first_name = ? LIMIT 1");
            $uIdStmt->execute([$m['name']]);
            $uId = $uIdStmt->fetchColumn();
            if ($uId) {
                $pdo->prepare("INSERT IGNORE INTO challenge_messages (challenge_key, user_id, message) VALUES (?, ?, ?)")
                    ->execute([$ck, $uId, $m['msg']]);
            }
        }
    }
    echo "✅ Seeded initial challenge chat messages.\n";
} catch (PDOException $e) {
    // Suppress
}

// Seed default achievements via the main seed script
try {
    ob_start();
    require_once __DIR__ . '/seed_achievements.php';
    ob_end_clean();
    echo "✅ Seeded achievements library.\n";
} catch (Exception $e) {
    echo "ℹ_ Achievements seeding skipped or errored: " . $e->getMessage() . "\n";
}

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
    ['hf_token', '', 'Hugging Face Access Token for Serverless Inference model fallbacks (Gemma 2)', 'ai'],
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
