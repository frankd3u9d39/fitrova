<?php
// backend/app/worker.php
require_once __DIR__ . '/helpers/EmailHelper.php';

echo "[*] Fitrova Background Worker started...\n";
echo "[*] Press Ctrl+C to stop.\n";

$emailHelper = new EmailHelper();
$pdo = null;

function connectDb() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'fitrova_db';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: '');

    try {
        $conn = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        echo "[*] Database connected successfully.\n";
        return $conn;
    } catch (PDOException $e) {
        echo "[-] Primary DB connection failed: " . $e->getMessage() . ". Trying Aiven Cloud DB fallback...\n";
        try {
            $host = getenv('AIVEN_DB_HOST') ?: 'fitroval-db123-ibehpromise30-af36.g.aivencloud.com';
            $port = getenv('AIVEN_DB_PORT') ?: '11816';
            $dbname = getenv('AIVEN_DB_NAME') ?: 'defaultdb';
            $username = getenv('AIVEN_DB_USER') ?: 'avnadmin';
            $password = getenv('AIVEN_DB_PASS') ?: 'AVNS_Sz6-RnTLGjBHbi49wvp';
            $conn = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            echo "[*] Aiven Cloud Database connected successfully.\n";
            return $conn;
        } catch (PDOException $e2) {
            echo "[-] Aiven Database connection failed: " . $e2->getMessage() . ". Retrying in 5 seconds...\n";
            return null;
        }
    }
}

while (true) {
    if ($pdo === null) {
        $pdo = connectDb();
        if ($pdo === null) {
            sleep(5);
            continue;
        }
    }

    try {
        // Find a pending job
        $stmt = $pdo->prepare("SELECT * FROM queue WHERE status = 'pending' LIMIT 1 FOR UPDATE");
        $stmt->execute();
        $job = $stmt->fetch();

        if ($job) {
            echo "[+] Processing job #{$job['id']} ({$job['handler']})...\n";
            
            // Mark as processing
            $pdo->prepare("UPDATE queue SET status = 'processing', attempts = attempts + 1 WHERE id = ?")->execute([$job['id']]);
            
            $payload = json_decode($job['payload'], true);
            $success = false;
            
            if ($job['handler'] === 'send_verification_email') {
                $success = $emailHelper->sendVerificationCode($payload['email'], $payload['code']);
            }
            
            if ($success) {
                $pdo->prepare("UPDATE queue SET status = 'completed' WHERE id = ?")->execute([$job['id']]);
                echo "    [OK] Job #{$job['id']} completed.\n";
            } else {
                $pdo->prepare("UPDATE queue SET status = 'failed', last_error = 'Email sending failed' WHERE id = ?")->execute([$job['id']]);
                echo "    [ERROR] Job #{$job['id']} failed.\n";
            }
        }
    } catch (PDOException $e) {
        echo "    [DB ERROR] Connection lost or query failed: " . $e->getMessage() . "\n";
        $pdo = null; // trigger reconnect
    } catch (Exception $e) {
        echo "    [FATAL ERROR] " . $e->getMessage() . "\n";
    }

    // Wait for 2 seconds before checking again to save CPU
    sleep(2);
}
?>

