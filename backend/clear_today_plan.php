<?php
require_once __DIR__ . '/config/db_config.php';
try {
    echo "--- Current Plans in DB ---\n";
    $stmt = $pdo->query("SELECT id, user_id, plan_date, name FROM workout_plans");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, User: {$row['user_id']}, Date: {$row['plan_date']}, Name: {$row['name']}\n";
    }
    
    echo "--- Deleting ALL Plans ---\n";
    $count = $pdo->exec("DELETE FROM workout_plans");
    echo "Successfully deleted $count plans.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
