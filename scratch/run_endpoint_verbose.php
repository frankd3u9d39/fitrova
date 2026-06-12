<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock JSON input for the request
$inputData = ['user_id' => 1];
$_POST = [];
// We override php://input by using a custom function or rewriting the file read, 
// but since the endpoint uses file_get_contents('php://input'), we can't easily mock it in the same process unless we execute it as a separate CLI call and pass input via STDIN!
// This is perfect! Let's invoke the CLI command and pass the mock JSON via stdin!

$cmd = "php -d error_reporting=E_ALL -d display_errors=1 backend/app/controllers/workout/ai_workout_gemini.php";
$descriptors = [
    0 => ["pipe", "r"], // stdin
    1 => ["pipe", "w"], // stdout
    2 => ["pipe", "w"]  // stderr
];

$process = proc_open($cmd, $descriptors, $pipes);

if (is_resource($process)) {
    // Write JSON payload to stdin
    fwrite($pipes[0], json_encode($inputData));
    fclose($pipes[0]);

    // Read output and error streams
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    proc_close($process);

    echo "=== STDOUT ===\n";
    echo $stdout . "\n\n";

    echo "=== STDERR ===\n";
    echo $stderr . "\n\n";
} else {
    echo "Failed to open process!\n";
}
?>
