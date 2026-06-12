-- Fitrova Enterprise Expansion Schema

USE fitrova_db;

-- System Settings (Dynamic Configuration)
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    category ENUM('ai', 'system', 'ui', 'nutrition') DEFAULT 'system',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Exercise Library (Moving from code to DB)
CREATE TABLE IF NOT EXISTS exercise_library (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    keywords TEXT NOT NULL, -- JSON or comma-separated
    video_url TEXT,
    image_url TEXT,
    category ENUM('cardio', 'strength', 'core', 'recovery', 'full_body') DEFAULT 'strength',
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Initial AI Configurations
INSERT IGNORE INTO system_settings (setting_key, setting_value, description, category) VALUES 
('ai_model_primary', 'gemini-3.1-flash', 'The main AI model for workout generation', 'ai'),
('ai_system_prompt', 'You are a professional fitness trainer. Generate a personalized workout plan based on the users nutrition and weight trends.', 'The base personality of the AI', 'ai'),
('ai_temperature', '0.7', 'Creativity level of the AI (0.0 to 1.0)', 'ai'),
('ai_gemini_api_key', 'AIzaSyBQ8TYJ0rdLdklnK9zi2T0U8RVFDp8wmJI', 'API Key for Google Gemini services', 'ai'),
('ai_youtube_api_key', 'AIzaSyD-tOfE-vkGE4mBNzJLadLb_U6CCfztqUE', 'API Key for YouTube data services', 'ai'),
('maintenance_mode', 'false', 'Disable app access for maintenance', 'system');

-- Migration Script for Exercises (Sample)
INSERT IGNORE INTO exercise_library (name, keywords, video_url, image_url, category) VALUES
('Jumping Jacks', 'jumping jack,star jump', 'https://www.youtube.com/watch?v=7Pxr4xOrhNk', 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800', 'cardio'),
('Push Ups', 'push up,pushup,push-up', 'https://www.youtube.com/watch?v=IODxDxX7oi4', 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800', 'strength'),
('Deep Squats', 'squat,back squat,goblet squat', 'https://www.youtube.com/watch?v=aclHkVaku9U', 'https://images.pexels.com/photos/4162451/pexels-photo-4162451.jpeg?w=800', 'strength'),
('Plank', 'plank,forearm plank', 'https://www.youtube.com/watch?v=pSHjTRCQxIw', 'https://images.pexels.com/photos/6740056/pexels-photo-6740056.jpeg?w=800', 'core');
