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
    // If users table, payment_transactions table, user_challenges table, or user_connections table is missing, initialize the database tables and seed defaults
    $usersTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    $paymentTable = $pdo->query("SHOW TABLES LIKE 'payment_transactions'")->fetch();
    $challengesTable = $pdo->query("SHOW TABLES LIKE 'user_challenges'")->fetch();
    $connectionsTable = $pdo->query("SHOW TABLES LIKE 'user_connections'")->fetch();
    if (!$usersTable || !$paymentTable || !$challengesTable || !$connectionsTable) {
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

// Fetch admin details and ensure profile record exists
$adminUser = null;
try {
    $adminEmail = $_SESSION['admin_email'];
    
    // Get user details
    $adminQuery = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, up.profile_picture 
        FROM users u 
        LEFT JOIN user_profiles up ON u.id = up.user_id 
        WHERE u.email = ? AND u.is_admin = 1
        LIMIT 1
    ");
    $adminQuery->execute([$adminEmail]);
    $adminUser = $adminQuery->fetch(PDO::FETCH_ASSOC);

    if ($adminUser) {
        // Check if a profile record exists, if not, create one
        $checkProfile = $pdo->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
        $checkProfile->execute([$adminUser['id']]);
        if (!$checkProfile->fetch()) {
            $pdo->prepare("INSERT INTO user_profiles (user_id) VALUES (?)")->execute([$adminUser['id']]);
        }
    }
} catch (PDOException $e) {
    error_log("Failed to fetch admin details: " . $e->getMessage());
}
?>
