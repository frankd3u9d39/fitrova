<?php
// backend/scripts/sync_local_to_aiven.php
require_once __DIR__ . '/../config/db_config.php';

// 1. Establish connection to local database (already set up in db_config.php via $pdo)
$localPdo = $pdo;
echo "Connected to local database: " . getenv('DB_NAME') . "\n";

// 2. Establish connection to remote Aiven database
$aivenHost = getenv('AIVEN_DB_HOST') ?: '';
$aivenPort = getenv('AIVEN_DB_PORT') ?: '11816';
$aivenUser = getenv('AIVEN_DB_USER') ?: '';
$aivenPass = getenv('AIVEN_DB_PASS') ?: '';
$aivenDbName = getenv('AIVEN_DB_NAME') ?: 'defaultdb';

if (empty($aivenHost) || $aivenHost === 'your-aiven-host.aivencloud.com' || empty($aivenPass) || $aivenPass === 'your-aiven-password') {
    die("Error: Please configure AIVEN_DB_HOST and AIVEN_DB_PASS in backend/.env before running this script.\n");
}

try {
    $remotePdo = new PDO("mysql:host=$aivenHost;port=$aivenPort;dbname=$aivenDbName;charset=utf8", $aivenUser, $aivenPass);
    $remotePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $remotePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "Connected successfully to remote Aiven database.\n";
} catch (PDOException $e) {
    die("Error connecting to remote Aiven database: " . $e->getMessage() . "\n");
}

// 3. Define the tables to sync in order of dependencies (parent tables first)
$tables = [
    'users',
    'user_profiles',
    'weight_history',
    'nutrition_logs',
    'workout_plans',
    'workout_logs',
    'ai_insights',
    'personal_records',
    'exercise_library',
    'system_settings',
    'achievements',
    'user_achievements',
    'payment_transactions',
    'form_check_logs',
    'ai_food_recommendations'
];

echo "\nStarting database synchronization...\n";

// Disable foreign key checks on remote database temporarily to prevent order insertion conflicts
$remotePdo->exec("SET FOREIGN_KEY_CHECKS = 0");

foreach ($tables as $table) {
    try {
        // Fetch columns of the table
        $columnsStmt = $localPdo->query("DESCRIBE $table");
        $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($columns)) {
            echo "⚠️ Table '$table' does not exist in local database. Skipping.\n";
            continue;
        }

        // Fetch all rows from local table
        $localRowsStmt = $localPdo->query("SELECT * FROM $table");
        $localRows = $localRowsStmt->fetchAll();
        $rowCount = count($localRows);

        if ($rowCount === 0) {
            echo "ℹ️ Table '$table' has 0 local rows. Skipping.\n";
            continue;
        }

        // Build dynamic INSERT ... ON DUPLICATE KEY UPDATE query
        $colNames = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        
        $updateAssignments = [];
        foreach ($columns as $col) {
            if ($col !== 'id') { // Keep primary key IDs intact
                $updateAssignments[] = "$col = VALUES($col)";
            }
        }
        $updateQueryPart = implode(', ', $updateAssignments);
        
        $sql = "INSERT INTO $table ($colNames) VALUES ($placeholders)";
        if (!empty($updateQueryPart)) {
            $sql .= " ON DUPLICATE KEY UPDATE $updateQueryPart";
        }

        $remoteStmt = $remotePdo->prepare($sql);

        // Execute batch insertion
        $remotePdo->beginTransaction();
        foreach ($localRows as $row) {
            $values = [];
            foreach ($columns as $col) {
                $values[] = $row[$col];
            }
            $remoteStmt->execute($values);
        }
        $remotePdo->commit();

        echo "✅ Synchronized table '$table': Transferred $rowCount rows.\n";

    } catch (Exception $e) {
        if ($remotePdo->inTransaction()) {
            $remotePdo->rollBack();
        }
        echo "❌ Failed to synchronize table '$table': " . $e->getMessage() . "\n";
    }
}

// Re-enable foreign key checks on remote
$remotePdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\n🎉 Synchronization finished successfully!\n";
?>
