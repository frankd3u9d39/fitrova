<?php
// backend/app/worker.php
require_once __DIR__ . '/../../backend/config/db_config.php';
require_once __DIR__ . '/helpers/EmailHelper.php';

echo "[*] Fitrova Background Worker started...\n";
echo "[*] Press Ctrl+C to stop.\n";

$emailHelper = new EmailHelper();

while (true) {
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
    } catch (Exception $e) {
        echo "    [FATAL ERROR] " . $e->getMessage() . "\n";
    }

    // Wait for 2 seconds before checking again to save CPU
    sleep(2);
}
?>
