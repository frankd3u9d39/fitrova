<?php
require_once __DIR__ . '/config/db_config.php';

try {
    // Disable foreign key checks temporarily
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $pdo->exec("DELETE FROM workout_plans");
    $pdo->exec("DELETE FROM workout_logs");
    
    // Reset auto-increment
    $pdo->exec("ALTER TABLE workout_plans AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE workout_logs AUTO_INCREMENT = 1");
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "Workout data cleared successfully!\n";
} catch (PDOException $e) {
    echo "Error clearing workout data: " . $e->getMessage() . "\n";
}
?>
