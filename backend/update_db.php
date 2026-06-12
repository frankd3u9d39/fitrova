<?php
require_once __DIR__ . '/config/db_config.php';

try {
    // Add verification columns if they don't exist
    $pdo->exec("ALTER TABLE users 
                ADD COLUMN IF NOT EXISTS verification_code VARCHAR(6) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0");
    
    // Create queue table
    $pdo->exec("CREATE TABLE IF NOT EXISTS queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        handler VARCHAR(255) NOT NULL,
        payload TEXT NOT NULL,
        status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        attempts INT DEFAULT 0,
        last_error TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Create pending_verifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS pending_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        code VARCHAR(6) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create ai_food_recommendations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_food_recommendations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        recommendation_date DATE NOT NULL,
        recommendations_json TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY idx_user_date (user_id, recommendation_date)
    )");
    
    echo "Database updated successfully with pending_verifications and ai_food_recommendations!\n";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>
