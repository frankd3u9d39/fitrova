-- Migration to create achievement tables and seed data
USE fitrova_db;

-- Achievements definition table
CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    icon VARCHAR(50) NOT NULL,
    category ENUM('Training', 'Nutrition', 'Milestones') NOT NULL,
    color VARCHAR(7) DEFAULT '#D1FAE5',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User achievements progress table
CREATE TABLE IF NOT EXISTS user_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
    UNIQUE KEY idx_user_achievement (user_id, achievement_id)
);

-- Seed initial achievements (matching frontend mock data)
INSERT IGNORE INTO achievements (id, title, description, icon, category, color) VALUES
(1, '7-Day Streak', 'Consistency King', 'flash', 'Training', '#D1FAE5'),
(2, 'Iron Will', '50 Heavy Sessions', 'barbell', 'Training', '#D1FAE5'),
(3, 'Iron Will Rank II', '100 Heavy Sessions', 'barbell', 'Training', '#E5E7EB'), -- Example of a locked one
(4, 'Sprint Master', 'Max Velocity Hit', 'speedometer', 'Training', '#D1FAE5'),
(5, 'Protein Pro', 'Macro Precision', 'restaurant', 'Nutrition', '#D1FAE5'),
(6, 'Water God', 'Stay Hydrated', 'water', 'Nutrition', '#E5E7EB'),
(7, 'Leafy Legend', 'Eat Your Greens', 'leaf', 'Nutrition', '#E5E7EB'),
(8, 'First Step', 'Journey Begun', 'footsteps', 'Milestones', '#D1FAE5'),
(9, 'Month Strong', '30 Days Active', 'calendar', 'Milestones', '#D1FAE5'),
(10, 'Year Warrior', '365 Days of Fitness', 'trophy', 'Milestones', '#E5E7EB');

-- Unlock some achievements for User 1 (sample data)
INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES
(1, 1),
(1, 2),
(1, 4),
(1, 5),
(1, 8),
(1, 9);
