<?php
// backend/scripts/run_production_migration.php
require_once __DIR__ . '/../config/env_loader.php';

// Load env variables
if (file_exists(__DIR__ . '/../.env')) {
    loadEnv(__DIR__ . '/../.env');
}

$host = getenv('AIVEN_DB_HOST');
$port = getenv('AIVEN_DB_PORT') ?: '11816';
$dbname = getenv('AIVEN_DB_NAME') ?: 'defaultdb';
$username = getenv('AIVEN_DB_USER');
$password = getenv('AIVEN_DB_PASS');

if (empty($host) || empty($password)) {
    die("❌ Error: AIVEN_DB_HOST or AIVEN_DB_PASS is not configured in backend/.env\n");
}

try {
    echo "Connecting to remote production Aiven database ($host:$port)...\n";
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "✅ Connected to Aiven.\n\n";

    echo "Running production database schema upgrade...\n";

    // 1. Add fields to 'users' table
    $checkOnboarding = $pdo->query("SHOW COLUMNS FROM users LIKE 'has_completed_onboarding'");
    if (!$checkOnboarding->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN has_completed_onboarding TINYINT(1) DEFAULT 0");
        echo "✅ Added 'has_completed_onboarding' column to production 'users' table.\n";
    } else {
        echo "ℹ️ Column 'has_completed_onboarding' already exists in production 'users'.\n";
    }

    $checkVersionSeen = $pdo->query("SHOW COLUMNS FROM users LIKE 'app_version_seen'");
    if (!$checkVersionSeen->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN app_version_seen VARCHAR(50) DEFAULT NULL");
        echo "✅ Added 'app_version_seen' column to production 'users' table.\n";
    } else {
        echo "ℹ️ Column 'app_version_seen' already exists in production 'users'.\n";
    }

    // 2. Create 'app_updates' table
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
    echo "✅ Table 'app_updates' successfully verified/created in production.\n";

    // 3. Create 'onboarding_slides' table
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
    echo "✅ Table 'onboarding_slides' successfully verified/created in production.\n";

    // 4. Seed system_settings for onboarding_enabled if not exists
    $checkOnboardingSetting = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'onboarding_enabled'");
    $checkOnboardingSetting->execute();
    if ($checkOnboardingSetting->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO system_settings (setting_key, setting_value, description, category)
            VALUES ('onboarding_enabled', 'true', 'Globally enable or disable the onboarding slide system.', 'system')
        ");
        echo "✅ Seeded 'onboarding_enabled' system setting in production.\n";
    } else {
        echo "ℹ️ 'onboarding_enabled' setting already exists in production system_settings.\n";
    }

    // 5. Seed initial version in app_updates if empty
    $checkUpdatesCount = $pdo->query("SELECT COUNT(*) FROM app_updates")->fetchColumn();
    if ($checkUpdatesCount == 0) {
        $pdo->exec("
            INSERT INTO app_updates (version, message, is_active, force_update)
            VALUES ('1.0.0', 'Initial release of the Fitrova app. Experience personalized fitness today!', 1, 0)
        ");
        echo "✅ Seeded initial app version (1.0.0) in production 'app_updates'.\n";
    }

    // 6. Seed initial onboarding slides if empty
    $checkSlidesCount = $pdo->query("SELECT COUNT(*) FROM onboarding_slides")->fetchColumn();
    if ($checkSlidesCount == 0) {
        $pdo->exec("
            INSERT INTO onboarding_slides (title, description, sort_order, is_active) VALUES
            ('Welcome to Fitrova', 'Your AI fitness companion to help you reach your goals.', 1, 1),
            ('AI Workout Assistant', 'Get smarter, customized workout guidance based on your personal metrics.', 2, 1),
            ('Track Your Progress', 'Monitor your improvements, log your nutrition, and stay consistent.', 3, 1)
        ");
        echo "✅ Seeded initial onboarding slides in production 'onboarding_slides'.\n";
    }

    echo "\n🎉 Production database upgrade completed successfully!\n";

} catch (PDOException $e) {
    die("❌ Production migration failed: " . $e->getMessage() . "\n");
}
?>
