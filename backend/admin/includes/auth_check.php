<?php
// backend/admin/includes/auth_check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure database config is loaded
if (!isset($pdo)) {
    require_once __DIR__ . '/../../config/db_config.php';
}

// Self-Heal Database Schema (Ensure tables and columns exist)
try {
    // If users table, payment_transactions table, or user_challenges table is missing, initialize the database tables and seed defaults
    $usersTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    $paymentTable = $pdo->query("SHOW TABLES LIKE 'payment_transactions'")->fetch();
    $challengesTable = $pdo->query("SHOW TABLES LIKE 'user_challenges'")->fetch();
    if (!$usersTable || !$paymentTable || !$challengesTable) {
        ob_start();
        require_once __DIR__ . '/../../scripts/setup_production_db.php';
        ob_end_clean();
    }

    // Ensure the is_admin column exists
    $checkCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_admin'")->fetch();
    if (!$checkCol) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
        // Promote the seeded admin user
        $pdo->exec("UPDATE users SET is_admin = 1 WHERE email = 'admin@fitrova.com'");
    }
} catch (PDOException $e) {
    // Fallback in case of permissions or query failures
    error_log("Self-healing failed in auth_check.php: " . $e->getMessage());
}

// Determine if the current request is an API request
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$isApi = (strpos($requestUri, '/api/') !== false || strpos($scriptName, '/api/') !== false);

// Bypass authentication checks in CLI mode
if (php_sapi_name() === 'cli') {
    return;
}

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if ($isApi) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "Unauthorized. Admin session required."]);
        exit();
    } else {
        header('Location: login.php');
        exit();
    }
}
?>
