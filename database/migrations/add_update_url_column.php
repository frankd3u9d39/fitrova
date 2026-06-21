<?php
// database/migrations/add_update_url_column.php
require_once __DIR__ . '/../../backend/config/db_config.php';

try {
    echo "--- LOCAL MIGRATION ---\n";
    // Check if column exists locally
    $checkLocal = $pdo->query("SHOW COLUMNS FROM app_updates LIKE 'update_url'");
    if (!$checkLocal->fetch()) {
        $pdo->exec("ALTER TABLE app_updates ADD COLUMN update_url VARCHAR(255) DEFAULT NULL");
        echo "✅ Added 'update_url' column to local 'app_updates' table.\n";
        // Update default rows with placeholder link
        $pdo->exec("UPDATE app_updates SET update_url = 'https://fitrova-backend.onrender.com/download' WHERE update_url IS NULL");
        echo "✅ Updated local update_url defaults.\n";
    } else {
        echo "ℹ️ Column 'update_url' already exists in local 'app_updates' table.\n";
    }
} catch (PDOException $e) {
    echo "❌ Local migration failed: " . $e->getMessage() . "\n";
}

// Remote production migration
try {
    echo "\n--- PRODUCTION MIGRATION ---\n";
    $host = getenv('AIVEN_DB_HOST');
    $port = getenv('AIVEN_DB_PORT') ?: '11816';
    $dbname = getenv('AIVEN_DB_NAME') ?: 'defaultdb';
    $username = getenv('AIVEN_DB_USER');
    $password = getenv('AIVEN_DB_PASS');

    if (empty($host) || empty($password)) {
        echo "⚠️ Production credentials not found in env. Skipping remote migration.\n";
        exit();
    }

    echo "Connecting to production Aiven database ($host:$port)...\n";
    $remotePdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $remotePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if column exists remotely
    $checkRemote = $remotePdo->query("SHOW COLUMNS FROM app_updates LIKE 'update_url'");
    if (!$checkRemote->fetch()) {
        $remotePdo->exec("ALTER TABLE app_updates ADD COLUMN update_url VARCHAR(255) DEFAULT NULL");
        echo "✅ Added 'update_url' column to production 'app_updates' table.\n";
        $remotePdo->exec("UPDATE app_updates SET update_url = 'https://fitrova-backend.onrender.com/download' WHERE update_url IS NULL");
        echo "✅ Updated production update_url defaults.\n";
    } else {
        echo "ℹ️ Column 'update_url' already exists in production 'app_updates' table.\n";
    }
} catch (PDOException $e) {
    echo "❌ Production migration failed: " . $e->getMessage() . "\n";
}
?>
