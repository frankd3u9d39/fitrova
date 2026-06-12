-- Initialize Fitrova Database
CREATE DATABASE IF NOT EXISTS fitrova_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fitrova_db;

-- Create database user (if not exists through environment)
CREATE USER IF NOT EXISTS 'fitrova_user'@'%' IDENTIFIED BY 'fitrovapass';
GRANT ALL PRIVILEGES ON fitrova_db.* TO 'fitrova_user'@'%';
FLUSH PRIVILEGES;

-- Run the main schema
SOURCE /docker-entrypoint-initdb.d/schema.sql;

-- Insert initial data (optional)
INSERT IGNORE INTO users (email, password_hash, first_name, last_name, user_type, subscription_tier, is_verified) VALUES
('admin@fitrova.com', '$2y$10$YourHashedPasswordHere', 'Admin', 'User', 'admin', 'advanced_premium', 1);

-- Insert default exercises
INSERT IGNORE INTO exercise_library (name, category, difficulty, description, youtube_video_id, muscle_groups) VALUES
('Bench Press', 'Strength', 'intermediate', 'Classic chest exercise using barbell', 'gRVjAtPip0Y', 'chest,triceps,shoulders'),
('Squat', 'Strength', 'intermediate', 'Full body compound movement', 'aclHkVaku9U', 'quadriceps,glutes,hamstrings'),
('Deadlift', 'Strength', 'advanced', 'Posterior chain exercise', 'r4MzxtBKyNE', 'back,glutes,hamstrings'),
('Pull-up', 'Strength', 'intermediate', 'Upper body pulling exercise', 'eGo4IYlbE5g', 'back,biceps'),
('Push-up', 'Strength', 'beginner', 'Bodyweight chest exercise', 'IODxDxX7oi4', 'chest,triceps,shoulders'),
('Plank', 'Core', 'beginner', 'Core stability exercise', 'ASdvN_XEl_c', 'core,shoulders'),
('Lunges', 'Strength', 'beginner', 'Single leg exercise', 'QOVaHwm-Q6U', 'quadriceps,glutes'),
('Bicep Curls', 'Strength', 'beginner', 'Arm isolation exercise', 'ykJmrZ5v0Oo', 'biceps'),
('Tricep Dips', 'Strength', 'intermediate', 'Tricep focused exercise', '0326dy_-CzM', 'triceps,chest');

-- Insert default achievements
INSERT IGNORE INTO achievements (name, description, badge_icon, points_required, criteria_type) VALUES
('First Workout', 'Complete your first workout session', '🏆', 1, 'workouts_completed'),
('Week Warrior', 'Complete 7 workouts in a week', '🔥', 7, 'workouts_completed'),
('Monthly Master', 'Complete 20 workouts in a month', '👑', 20, 'workouts_completed'),
('Weight Loss Goal', 'Lose 5kg of body weight', '⚖️', 5, 'weight_lost'),
('Nutrition Expert', 'Log 50 meals', '🍎', 50, 'meals_logged'),
('Streak Keeper', 'Maintain a 30-day workout streak', '📅', 30, 'streak_days'),
('Form Perfectionist', 'Score 90+ on form check', '🎯', 90, 'form_score'),
('YouTube Analyzer', 'Analyze 10 YouTube workouts', '📹', 10, 'videos_analyzed');