<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../config/db_config.php';

try {
    // Set headers to trigger file download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=fitrova_financials_' . date('Y-m-d') . '.csv');

    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    // Output the column headings
    fputcsv($output, ['Transaction ID / Reference', 'Athlete Name', 'Plan Tier', 'Amount (NGN)', 'Date', 'Status']);

    // Query database for real payment transaction records
    $stmt = $pdo->query("
        SELECT 
            pt.reference,
            CONCAT(u.first_name, ' ', u.last_name) as athlete_name,
            pt.subscription_tier,
            pt.amount,
            pt.created_at,
            pt.status
        FROM payment_transactions pt
        JOIN users u ON pt.user_id = u.id
        ORDER BY pt.created_at DESC
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['reference'],
            $row['athlete_name'],
            ucfirst($row['subscription_tier']),
            $row['amount'],
            $row['created_at'],
            ucfirst($row['status'])
        ]);
    }
    fclose($output);
} catch (Exception $e) {
    http_response_code(500);
    echo "Error generating financials export: " . $e->getMessage();
}
?>
