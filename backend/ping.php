<?php
// backend/ping.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Cache-Control: no-cache, must-revalidate");

echo json_encode([
    "status" => "healthy",
    "service" => "Fitrova Backend API",
    "timestamp" => time()
]);
?>
