<?php
/**
 * NEX - 47 CATALYS'S - Export Leads to CSV
 */

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT `id`, `client_name`, `client_phone`, `brand_name`, `service_type`, `budget`, `status`, `message`, `created_at` FROM `leads` ORDER BY `id` DESC");
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=nex47_leads_' . date('Y-m-d_H-i') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Client Name', 'Phone / WhatsApp', 'Brand / Website', 'Service Type', 'Budget Tier', 'Status', 'Message', 'Submission Date']);

    foreach ($leads as $lead) {
        fputcsv($output, $lead);
    }

    fclose($output);
    exit;
} catch (Exception $e) {
    echo "Error exporting leads: " . $e->getMessage();
}
