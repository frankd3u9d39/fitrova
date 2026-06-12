<?php
// backend/app/helpers/PaymentGuard.php
// Include this at the TOP of every payment controller to enforce the global kill-switch.

if (!function_exists('enforcePaymentGate')) {
    function enforcePaymentGate(PDO $pdo): void {
        try {
            $stmt = $pdo->query(
                "SELECT setting_value FROM system_settings WHERE setting_key = 'payments_enabled' LIMIT 1"
            );
            $value = $stmt ? $stmt->fetchColumn() : 'true';

            // Treat any non-false value as enabled (default is always ON)
            if ($value === 'false') {
                http_response_code(503);
                echo json_encode([
                    'status'  => 'error',
                    'code'    => 'PAYMENTS_DISABLED',
                    'message' => 'Payments are temporarily disabled by the administrator. Please try again later.',
                ]);
                exit();
            }
        } catch (Throwable $e) {
            // On DB error, fail open (allow payments to proceed) to avoid
            // blocking users during unexpected infrastructure issues.
        }
    }
}
?>
