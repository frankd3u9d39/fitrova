<?php
require_once __DIR__ . '/../backend/config/db_config.php';

// Verify profile_picture column
$stmt = $pdo->query("SHOW COLUMNS FROM user_profiles LIKE 'profile_picture'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "✅ Column: {$col['Field']}, Type: {$col['Type']}, Null: {$col['Null']}" . PHP_EOL;

// Verify get_profile_stats SQL works
$stmt = $pdo->prepare("
    SELECT u.first_name, u.last_name, u.email, up.motto, up.profile_picture
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE u.id = 1
");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "✅ User: {$row['first_name']} {$row['last_name']}" . PHP_EOL;
echo "✅ profile_picture present in result: " . (array_key_exists('profile_picture', $row) ? 'YES' : 'NO') . PHP_EOL;
echo "✅ profile_picture value: " . ($row['profile_picture'] ? substr($row['profile_picture'], 0, 50) . '...' : 'NULL (no picture set yet)') . PHP_EOL;
?>
