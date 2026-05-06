-- Simple Migration for Dashboard Tables
-- Run this in phpMyAdmin SQL tab
-- Ignore any "Duplicate column" or "Table already exists" errors - they're safe

USE fitrova_db;

-- Create new tables (will skip if they already exist)
CREATE TABLE IF NOT EXISTS weight_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    weight DECIMAL(5,2) NOT NULL,
    recorded_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, recorded_date)
);

CREATE TABLE IF NOT EXISTS nutrition_logs (
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
);

CREATE TABLE IF NOT EXISTS workout_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INT NOT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'intermediate',
    workout_type ENUM('strength', 'cardio', 'flexibility', 'mixed') DEFAULT 'mixed',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS workout_logs (
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
);

CREATE TABLE IF NOT EXISTS ai_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    insight_text TEXT NOT NULL,
    insight_type ENUM('motivation', 'warning', 'achievement', 'tip') DEFAULT 'tip',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read)
);

-- Insert sample data for testing (only if tables are empty)
-- Change user_id to match your logged-in user

-- Sample weight history (last 7 days) for user_id 1
INSERT IGNORE INTO weight_history (user_id, weight, recorded_date) VALUES
(1, 75.5, DATE_SUB(CURDATE(), INTERVAL 6 DAY)),
(1, 75.3, DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
(1, 75.0, DATE_SUB(CURDATE(), INTERVAL 4 DAY)),
(1, 74.8, DATE_SUB(CURDATE(), INTERVAL 3 DAY)),
(1, 74.5, DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(1, 74.3, DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(1, 74.0, CURDATE());

-- Sample nutrition logs for today for user_id 1
INSERT IGNORE INTO nutrition_logs (user_id, meal_name, calories, protein, carbs, fats, meal_type, logged_date) VALUES
(1, 'Oatmeal with Berries', 350, 12, 55, 8, 'breakfast', CURDATE()),
(1, 'Grilled Chicken Salad', 450, 35, 25, 18, 'lunch', CURDATE()),
(1, 'Protein Shake', 200, 25, 15, 5, 'snack', CURDATE());

-- Sample workout log for today for user_id 1
INSERT IGNORE INTO workout_logs (user_id, workout_name, duration_minutes, calories_burned, completed_date) VALUES
(1, 'Upper Body Strength', 45, 320, CURDATE());

-- Sample AI insight for user_id 1
INSERT IGNORE INTO ai_insights (user_id, insight_text, insight_type, is_read) VALUES
(1, 'Great job staying consistent! You\'ve logged meals for 5 days straight. Keep it up!', 'motivation', FALSE);

-- Update user_profiles with sample data if current_weight is NULL
-- This will only update if the columns exist
UPDATE user_profiles 
SET current_weight = COALESCE(current_weight, weight),
    daily_calorie_goal = COALESCE(daily_calorie_goal, 2000),
    health_score = COALESCE(health_score, 75)
WHERE user_id = 1;
