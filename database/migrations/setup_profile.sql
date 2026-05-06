USE fitrova_db;

CREATE TABLE IF NOT EXISTS user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    age INT,
    gender VARCHAR(10),
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    activity_level VARCHAR(50),
    fitness_goal VARCHAR(100),
    target_weight DECIMAL(5,2),
    target_date VARCHAR(50),
    diet_preference VARCHAR(100),
    allergies JSON,
    medical_conditions JSON,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
