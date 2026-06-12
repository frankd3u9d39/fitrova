<?php
// Simple test script to check if backend is accessible
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Backend is working',
    'timestamp' => time(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'php_version' => phpversion()
]);
?>