<?php
// C:\xampp\htdocs\Fitrova\database\migrations\update_profile_and_pr_schema.php
require_once __DIR__ . '/../../backend/config/db_config.php';

try {
    echo "Running database schema upgrade...\n";

    // 1. Safely add 'motto' column to user_profiles table if it doesn't exist
    $checkMotto = $pdo->query("SHOW COLUMNS FROM user_profiles LIKE 'motto'");
    $mottoExists = $checkMotto->fetch();
    
    if (!$mottoExists) {
        $pdo->exec("ALTER TABLE user_profiles ADD COLUMN motto VARCHAR(255) DEFAULT 'Striving for 1% better every day'");
        echo "✅ Column 'motto' successfully added to 'user_profiles'.\n";
    } else {
        echo "ℹ️ Column 'motto' already exists in 'user_profiles'.\n";
    }

    // 2. Create the 'personal_records' table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS personal_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            exercise_name VARCHAR(100) NOT NULL,
            weight_kg DECIMAL(5,2) NOT NULL,
            recorded_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "✅ Table 'personal_records' successfully verified/created.\n";

    // 3. Seed sample data for user_id = 1 if personal_records table is empty
    $checkPRCount = $pdo->prepare("SELECT COUNT(*) FROM personal_records WHERE user_id = ?");
    $checkPRCount->execute([1]);
    $prCount = $checkPRCount->fetchColumn();

    if ($prCount == 0) {
        $seedPR = $pdo->prepare("
            INSERT INTO personal_records (user_id, exercise_name, weight_kg, recorded_date) VALUES
            (1, 'Squat', 120.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
            (1, 'Bench Press', 95.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY))
        ");
        $seedPR->execute();
        echo "✅ Seeded initial personal records for User 1.\n";
    } else {
        echo "ℹ️ Personal records already exist for User 1.\n";
    }

    echo "🎉 Database schema upgrade completed successfully!\n";

} catch (PDOException $e) {
    die("❌ Migration failed: " . $e->getMessage() . "\n");
}
?>
