<?php
/**
 * NEX - 47 CATALYS'S - Get Leads API
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();

    $status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';

    $query = "SELECT * FROM `leads` WHERE 1=1";
    $params = [];

    if ($status !== 'all' && !empty($status)) {
        $query .= " AND `status` = :status";
        $params[':status'] = $status;
    }

    if (!empty($search)) {
        $query .= " AND (`client_name` LIKE :search OR `client_phone` LIKE :search OR `brand_name` LIKE :search OR `service_type` LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $query .= " ORDER BY `created_at` DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'count' => count($leads),
        'leads' => $leads
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
