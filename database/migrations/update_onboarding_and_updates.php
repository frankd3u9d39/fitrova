<?php
// database/migrations/update_onboarding_and_updates.php
require_once __DIR__ . '/../../backend/config/db_config.php';

try {
    echo "Starting database migration for updates and onboarding...\n";

    // 1. Add fields to 'users' table if they don't exist
    $checkOnboarding = $pdo->query("SHOW COLUMNS FROM users LIKE 'has_completed_onboarding'");
    if (!$checkOnboarding->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN has_completed_onboarding TINYINT(1) DEFAULT 0");
        echo "✅ Added 'has_completed_onboarding' column to 'users' table.\n";
    } else {
        echo "ℹ️ Column 'has_completed_onboarding' already exists in 'users' table.\n";
    }

    $checkVersionSeen = $pdo->query("SHOW COLUMNS FROM users LIKE 'app_version_seen'");
    if (!$checkVersionSeen->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN app_version_seen VARCHAR(50) DEFAULT NULL");
        echo "✅ Added 'app_version_seen' column to 'users' table.\n";
    } else {
        echo "ℹ️ Column 'app_version_seen' already exists in 'users' table.\n";
    }

    // 2. Create 'app_updates' table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_updates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            version VARCHAR(50) NOT NULL,
            message TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            force_update TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✅ Table 'app_updates' successfully verified/created.\n";

    // 3. Create 'onboarding_slides' table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS onboarding_slides (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✅ Table 'onboarding_slides' successfully verified/created.\n";

    // 4. Seed system_settings for onboarding_enabled if not exists
    $checkOnboardingSetting = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'onboarding_enabled'");
    $checkOnboardingSetting->execute();
    if ($checkOnboardingSetting->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO system_settings (setting_key, setting_value, description, category)
            VALUES ('onboarding_enabled', 'true', 'Globally enable or disable the onboarding slide system.', 'system')
        ");
        echo "✅ Seeded 'onboarding_enabled' system setting.\n";
    } else {
        echo "ℹ️ 'onboarding_enabled' setting already exists in 'system_settings'.\n";
    }

    // 5. Seed initial version in app_updates if table is empty
    $checkUpdatesCount = $pdo->query("SELECT COUNT(*) FROM app_updates")->fetchColumn();
    if ($checkUpdatesCount == 0) {
        $pdo->exec("
            INSERT INTO app_updates (version, message, is_active, force_update)
            VALUES ('1.0.0', 'Initial release of the Fitrova app. Experience personalized fitness today!', 1, 0)
        ");
        echo "✅ Seeded initial app version (1.0.0) in 'app_updates'.\n";
    }

    // 6. Seed initial onboarding slides if table is empty
    $checkSlidesCount = $pdo->query("SELECT COUNT(*) FROM onboarding_slides")->fetchColumn();
    if ($checkSlidesCount == 0) {
        $pdo->exec("
            INSERT INTO onboarding_slides (title, description, sort_order, is_active) VALUES
            ('Welcome to Fitrova', 'Your AI fitness companion to help you reach your goals.', 1, 1),
            ('AI Workout Assistant', 'Get smarter, customized workout guidance based on your personal metrics.', 2, 1),
            ('Track Your Progress', 'Monitor your improvements, log your nutrition, and stay consistent.', 3, 1)
        ");
        echo "✅ Seeded initial 3 onboarding slides in 'onboarding_slides'.\n";
    } else {
        echo "ℹ️ Onboarding slides already seeded.\n";
    }

    echo "🎉 Database schema upgrade completed successfully!\n";

} catch (PDOException $e) {
    die("❌ Migration failed: " . $e->getMessage() . "\n");
}
?>
