<?php
// backend/app/migration_paystack.php
require_once __DIR__ . '/../config/db_config.php';

try {
    echo "Starting Paystack & subscription migrations...\n";

    // 1. Check and add columns to user_profiles table if they do not exist
    $columns = [
        'subscription_tier' => "VARCHAR(20) DEFAULT 'free'",
        'trial_used' => "TINYINT(1) DEFAULT 0",
        'subscription_expiry' => "DATETIME DEFAULT NULL"
    ];

    foreach ($columns as $col => $definition) {
        $check = $pdo->query("SHOW COLUMNS FROM user_profiles LIKE '$col'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE user_profiles ADD COLUMN $col $definition");
            echo "Added column '$col' to user_profiles table.\n";
        } else {
            echo "Column '$col' already exists in user_profiles.\n";
        }
    }

    // 2. Create payment_transactions table
    $createTableSql = "
        CREATE TABLE IF NOT EXISTS payment_transactions (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
    $pdo->exec($createTableSql);
    echo "Table 'payment_transactions' ensured in database.\n";

    // 3. Insert mock Paystack test credentials into system_settings if they don't exist
    $settings = [
        'paystack_public_key' => 'pk_test_mock_public_key_123456789',
        'paystack_secret_key' => 'sk_test_mock_secret_key_123456789'
    ];

    foreach ($settings as $key => $val) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        if ($stmt->fetchColumn() == 0) {
            $insert = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
            $insert->execute([$key, $val]);
            echo "Inserted dynamic setting '$key'.\n";
        } else {
            echo "Setting '$key' already exists in system_settings.\n";
        }
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
