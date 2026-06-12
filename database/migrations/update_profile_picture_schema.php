<?php
// C:\xampp\htdocs\Fitrova\database\migrations\update_profile_picture_schema.php
require_once __DIR__ . '/../../backend/config/db_config.php';

try {
    echo "Running profile picture database schema upgrade...\n";

    // 1. Safely add 'profile_picture' column to user_profiles table if it doesn't exist
    $checkPic = $pdo->query("SHOW COLUMNS FROM user_profiles LIKE 'profile_picture'");
    $picExists = $checkPic->fetch();
    
    if (!$picExists) {
        $pdo->exec("ALTER TABLE user_profiles ADD COLUMN profile_picture TEXT DEFAULT NULL");
        echo "✅ Column 'profile_picture' successfully added to 'user_profiles'.\n";
    } else {
        echo "ℹ️ Column 'profile_picture' already exists in 'user_profiles'.\n";
    }

    echo "🎉 Profile picture database schema upgrade completed successfully!\n";

} catch (PDOException $e) {
    die("❌ Migration failed: " . $e->getMessage() . "\n");
}
?>
