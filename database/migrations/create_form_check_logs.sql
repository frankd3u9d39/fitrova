-- Migration to create form_check_logs table
CREATE TABLE IF NOT EXISTS form_check_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exercise_name VARCHAR(255),
    score INT,
    status VARCHAR(50),
    summary TEXT,
    tips TEXT, -- Stored as JSON string
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
