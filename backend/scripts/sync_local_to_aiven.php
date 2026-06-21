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
    $remotePdo = new PDO("mysql:host=$aivenHost;port=$aivenPort;dbname=$aivenDbName;charset=utf8mb4", $aivenUser, $aivenPass);
    $remotePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $remotePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "Connected successfully to remote Aiven database.\n";
} catch (PDOException $e) {
    die("Error connecting to remote Aiven database: " . $e->getMessage() . "\n");
}

echo "\nStarting database synchronization with dynamic user ID mapping...\n";

// Disable foreign key checks on remote database temporarily
$remotePdo->exec("SET FOREIGN_KEY_CHECKS = 0");

$userIdMap = []; // maps local_user_id => remote_user_id

try {
    // 1. Sync users table (matching by email)
    echo "Syncing table 'users'...\n";
    $localUsers = $localPdo->query("SELECT * FROM users")->fetchAll();
    
    // Get existing remote users to check for matches
    $remoteUsersStmt = $remotePdo->query("SELECT id, email FROM users");
    $remoteUsersByEmail = [];
    while ($ru = $remoteUsersStmt->fetch()) {
        $remoteUsersByEmail[strtolower($ru['email'])] = $ru['id'];
    }
    
    foreach ($localUsers as $lu) {
        $email = strtolower($lu['email']);
        if (isset($remoteUsersByEmail[$email])) {
            // User exists in remote: update remote record details but keep remote's ID
            $remoteId = $remoteUsersByEmail[$email];
            $userIdMap[$lu['id']] = $remoteId;
            
            $updateStmt = $remotePdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, password_hash = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$lu['first_name'], $lu['last_name'], $lu['password_hash'], $remoteId]);
        } else {
            // User does not exist in remote: insert and capture remote auto-increment ID
            // Check if local ID is taken on remote
            $idTaken = $remotePdo->query("SELECT COUNT(*) FROM users WHERE id = " . intval($lu['id']))->fetchColumn();
            if ($idTaken > 0) {
                // Local ID taken: let Aiven auto-generate ID
                $insertStmt = $remotePdo->prepare("
                    INSERT INTO users (first_name, last_name, email, password_hash, created_at)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([$lu['first_name'], $lu['last_name'], $lu['email'], $lu['password_hash'], $lu['created_at']]);
                $remoteId = $remotePdo->lastInsertId();
            } else {
                // Local ID is free: insert with local ID
                $insertStmt = $remotePdo->prepare("
                    INSERT INTO users (id, first_name, last_name, email, password_hash, created_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([$lu['id'], $lu['first_name'], $lu['last_name'], $lu['email'], $lu['password_hash'], $lu['created_at']]);
                $remoteId = $lu['id'];
            }
            $userIdMap[$lu['id']] = $remoteId;
        }
    }
    echo "✅ Synced 'users'. Total active user mappings: " . count($userIdMap) . "\n";

    // 2. Sync user_profiles table (matching by mapped user_id)
    echo "Syncing table 'user_profiles'...\n";
    $localProfiles = $localPdo->query("SELECT * FROM user_profiles")->fetchAll();
    foreach ($localProfiles as $lp) {
        if (!isset($userIdMap[$lp['user_id']])) continue;
        $remoteUserId = $userIdMap[$lp['user_id']];
        
        // Check if profile exists on remote
        $remoteProfileId = $remotePdo->query("SELECT id FROM user_profiles WHERE user_id = " . intval($remoteUserId))->fetchColumn();
        
        $fields = [
            'current_weight', 'target_weight', 'height', 'age', 'gender', 
            'activity_level', 'daily_calorie_goal', 'health_score',
            'subscription_tier', 'trial_used', 'subscription_expiry'
        ];
        
        $vals = [$remoteUserId];
        foreach ($fields as $f) {
            $vals[] = $lp[$f] ?? null;
        }
        
        if ($remoteProfileId) {
            // Update existing profile
            $setParts = [];
            foreach ($fields as $f) {
                $setParts[] = "$f = ?";
            }
            $setStr = implode(', ', $setParts);
            $updateStmt = $remotePdo->prepare("UPDATE user_profiles SET $setStr WHERE user_id = ?");
            
            $updateVals = array_slice($vals, 1);
            $updateVals[] = $remoteUserId;
            $updateStmt->execute($updateVals);
        } else {
            // Insert new profile
            $colsStr = 'user_id, ' . implode(', ', $fields);
            $placeholders = implode(', ', array_fill(0, count($fields) + 1, '?'));
            $insertStmt = $remotePdo->prepare("INSERT INTO user_profiles ($colsStr) VALUES ($placeholders)");
            $insertStmt->execute($vals);
        }
    }
    echo "✅ Synced 'user_profiles'.\n";

    // 3. Sync static/lookup tables (matching on unique natural keys)
    echo "Syncing table 'system_settings'...\n";
    $localSettings = $localPdo->query("SELECT * FROM system_settings")->fetchAll();
    foreach ($localSettings as $ls) {
        $exists = $remotePdo->query("SELECT COUNT(*) FROM system_settings WHERE setting_key = " . $remotePdo->quote($ls['setting_key']))->fetchColumn();
        if ($exists) {
            $stmt = $remotePdo->prepare("UPDATE system_settings SET setting_value = ?, description = ?, category = ? WHERE setting_key = ?");
            $stmt->execute([$ls['setting_value'], $ls['description'], $ls['category'], $ls['setting_key']]);
        } else {
            $stmt = $remotePdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description, category) VALUES (?, ?, ?, ?)");
            $stmt->execute([$ls['setting_key'], $ls['setting_value'], $ls['description'], $ls['category']]);
        }
    }
    echo "✅ Synced 'system_settings'.\n";

    echo "Syncing table 'achievements'...\n";
    $localAchievements = $localPdo->query("SELECT * FROM achievements")->fetchAll();
    foreach ($localAchievements as $la) {
        $remoteId = $remotePdo->query("SELECT id FROM achievements WHERE title = " . $remotePdo->quote($la['title']))->fetchColumn();
        if ($remoteId) {
            $stmt = $remotePdo->prepare("UPDATE achievements SET description = ?, icon = ?, category = ?, color = ? WHERE id = ?");
            $stmt->execute([$la['description'], $la['icon'], $la['category'], $la['color'], $remoteId]);
        } else {
            $stmt = $remotePdo->prepare("INSERT INTO achievements (title, description, icon, category, color) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$la['title'], $la['description'], $la['icon'], $la['category'], $la['color']]);
        }
    }
    echo "✅ Synced 'achievements'.\n";

    echo "Syncing table 'exercise_library'...\n";
    $localExercises = $localPdo->query("SELECT * FROM exercise_library")->fetchAll();
    foreach ($localExercises as $le) {
        $remoteId = $remotePdo->query("SELECT id FROM exercise_library WHERE name = " . $remotePdo->quote($le['name']))->fetchColumn();
        if ($remoteId) {
            $stmt = $remotePdo->prepare("UPDATE exercise_library SET keywords = ?, video_url = ?, image_url = ?, category = ?, difficulty = ? WHERE id = ?");
            $stmt->execute([$le['keywords'], $le['video_url'], $le['image_url'], $le['category'], $le['difficulty'], $remoteId]);
        } else {
            $stmt = $remotePdo->prepare("INSERT INTO exercise_library (name, keywords, video_url, image_url, category, difficulty) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$le['name'], $le['keywords'], $le['video_url'], $le['image_url'], $le['category'], $le['difficulty']]);
        }
    }
    echo "✅ Synced 'exercise_library'.\n";

    // 4. Truncate and sync all dependent log tables
    // Truncating clears logs to prevent primary key conflicts, then we insert rows with mapped user IDs
    $logTables = [
        'weight_history'          => ['user_id'],
        'nutrition_logs'          => ['user_id'],
        'workout_plans'           => ['user_id'],
        'workout_logs'            => ['user_id'],
        'ai_insights'             => ['user_id'],
        'personal_records'        => ['user_id'],
        'user_achievements'       => ['user_id'],
        'payment_transactions'    => ['user_id'],
        'form_check_logs'         => ['user_id'],
        'ai_food_recommendations' => ['user_id'],
        'challenge_messages'      => ['user_id'],
        'user_challenges'         => ['user_id'],
        'user_connections'        => ['requester_id', 'receiver_id']
    ];

    foreach ($logTables as $table => $userCols) {
        echo "Syncing table '$table' (clearing remote first)...\n";
        $remotePdo->exec("TRUNCATE TABLE `$table`");
        
        $localRows = $localPdo->query("SELECT * FROM `$table`")->fetchAll();
        if (empty($localRows)) {
            echo "   -> 0 rows locally.\n";
            continue;
        }

        // Get local columns list, excluding auto-increment primary key ID to let remote generate it
        $columnsStmt = $localPdo->query("DESCRIBE `$table`");
        $allCols = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
        $colsToSync = array_filter($allCols, function($c) { return $c !== 'id'; });
        
        $colsStr = implode(', ', array_map(function($c) { return "`$c`"; }, $colsToSync));
        $placeholders = implode(', ', array_fill(0, count($colsToSync), '?'));
        
        $insertStmt = $remotePdo->prepare("INSERT INTO `$table` ($colsStr) VALUES ($placeholders)");
        
        $remotePdo->beginTransaction();
        $transferredCount = 0;
        foreach ($localRows as $row) {
            // Translate local user ID referencing columns to remote user IDs
            $skip = false;
            foreach ($userCols as $userCol) {
                if (isset($row[$userCol])) {
                    if (isset($userIdMap[$row[$userCol]])) {
                        $row[$userCol] = $userIdMap[$row[$userCol]];
                    } else {
                        // Skip row if it references a user that does not exist in our mappings
                        $skip = true;
                        break;
                    }
                }
            }
            if ($skip) continue;
            
            // Collect query bound parameters
            $values = [];
            foreach ($colsToSync as $c) {
                $values[] = $row[$c];
            }
            
            $insertStmt->execute($values);
            $transferredCount++;
        }
        $remotePdo->commit();
        echo "✅ Synchronized table '$table': Transferred $transferredCount rows.\n";
    }

} catch (Exception $e) {
    if ($remotePdo->inTransaction()) {
        $remotePdo->rollBack();
    }
    echo "\n❌ Sync failed: " . $e->getMessage() . "\n";
} finally {
    // Re-enable foreign key checks on remote
    $remotePdo->exec("SET FOREIGN_KEY_CHECKS = 1");
}

echo "\n🎉 Database synchronization finished successfully!\n";
?>
