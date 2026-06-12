<?php
/**
 * Security Configuration for Fitrova Backend
 */

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

// CORS Configuration
$allowed_origins = [
    'http://localhost:8081',     // Expo development server
    'http://localhost:19006',    // Expo Web
    'https://staging.fitrova.com',  // Staging
    'https://fitrova.com',       // Production
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
}
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

// Rate limiting configuration
define('RATE_LIMIT_ENABLED', getenv('APP_ENV') !== 'development');
define('RATE_LIMIT_WINDOW', 60); // seconds
define('RATE_LIMIT_MAX_REQUESTS', 100); // per window
define('RATE_LIMIT_MAX_REQUESTS_AUTH', 500); // authenticated users

// Input validation functions
function sanitize_input($input) {
    if (is_array($input)) {
        return array_map('sanitize_input', $input);
    }
    
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_password($password) {
    // At least 8 characters, one uppercase, one lowercase, one number
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
}

function validate_numeric($value, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return false;
    }
    
    $num = floatval($value);
    
    if ($min !== null && $num < $min) {
        return false;
    }
    
    if ($max !== null && $num > $max) {
        return false;
    }
    
    return true;
}

// CSRF protection
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Security middleware
function require_https() {
    if (getenv('APP_ENV') === 'production' && empty($_SERVER['HTTPS'])) {
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit();
    }
}

function prevent_clickjacking() {
    header('X-Frame-Options: DENY');
}

function prevent_xss() {
    header('X-XSS-Protection: 1; mode=block');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\'; style-src \'self\' \'unsafe-inline\';');
}

function add_security_headers() {
    prevent_clickjacking();
    prevent_xss();
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('X-Content-Type-Options: nosniff');
}

// Initialize security
add_security_headers();

// Environment-specific security settings
$app_env = getenv('APP_ENV') ?: 'development';

switch ($app_env) {
    case 'production':
        error_reporting(0);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', __DIR__ . '/../storage/logs/php_errors.log');
        break;
        
    case 'staging':
        error_reporting(E_ALL & ~E_NOTICE);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        break;
        
    case 'development':
    default:
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('log_errors', 1);
        break;
}
?>