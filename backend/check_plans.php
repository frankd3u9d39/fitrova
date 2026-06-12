<?php
require_once __DIR__ . '/config/db_config.php';
try {
    $stmt = $pdo->query("SELECT * FROM workout_plans");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($plans, JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
