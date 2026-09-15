-- Per-set workout logging: the missing write-side counterpart to the
-- existing `personal_records` table (which Profile already reads from,
-- but nothing has ever written to outside its seed data).
--
-- exercise_set_logs stores every logged set (weight + reps), giving:
--   - "what did I lift last time" prefill for ActiveWorkoutScreen
--   - a real history to detect PRs against, which get promoted into
--     the existing personal_records table on a new best.
CREATE TABLE IF NOT EXISTS exercise_set_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exercise_name VARCHAR(255) NOT NULL,
    set_number INT NOT NULL DEFAULT 1,
    weight_kg DECIMAL(6,2) DEFAULT NULL,
    reps INT DEFAULT NULL,
    is_pr BOOLEAN DEFAULT FALSE,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_exercise (user_id, exercise_name),
    INDEX idx_user_exercise_logged (user_id, exercise_name, logged_at)
);
