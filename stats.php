<?php
/**
 * NEX - 47 CATALYS'S - Admin Stats API
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();

    $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM `leads`");
    $totalLeads = $totalStmt->fetch()['total'] ?? 0;

    $newStmt = $pdo->query("SELECT COUNT(*) as total_new FROM `leads` WHERE `status` = 'new'");
    $newLeads = $newStmt->fetch()['total_new'] ?? 0;

    $contactedStmt = $pdo->query("SELECT COUNT(*) as total_contacted FROM `leads` WHERE `status` = 'contacted' OR `status` = 'in_progress'");
    $contactedLeads = $contactedStmt->fetch()['total_contacted'] ?? 0;

    $convertedStmt = $pdo->query("SELECT COUNT(*) as total_converted FROM `leads` WHERE `status` = 'converted'");
    $convertedLeads = $convertedStmt->fetch()['total_converted'] ?? 0;

    // Services breakdown
    $serviceStmt = $pdo->query("SELECT `service_type`, COUNT(*) as count FROM `leads` GROUP BY `service_type` ORDER BY count DESC LIMIT 5");
    $topServices = $serviceStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_leads' => $totalLeads,
            'new_inquiries' => $newLeads,
            'in_progress' => $contactedLeads,
            'converted' => $convertedLeads,
            'top_services' => $topServices
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
