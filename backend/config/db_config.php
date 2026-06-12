<?php
// backend/db_config.php

if (php_sapi_name() !== 'cli') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Load environment variables if env_loader is present
require_once __DIR__ . '/env_loader.php';
if (file_exists(__DIR__ . '/../.env')) {
    loadEnv(__DIR__ . '/../.env');
} elseif (file_exists(__DIR__ . '/../.env.local')) {
    loadEnv(__DIR__ . '/../.env.local');
} elseif (file_exists(__DIR__ . '/../.env.production')) {
    loadEnv(__DIR__ . '/../.env.production');
}

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'fitrova_db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: '');

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (php_sapi_name() === 'cli') {
        die("Database connection failed: " . $e->getMessage() . "\n");
    }
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}
?>
