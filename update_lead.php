<?php
/**
 * NEX - 47 CATALYS'S - Update Lead Status API
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$lead_id = intval($input['id'] ?? 0);
$status = trim($input['status'] ?? '');
$notes = trim($input['notes'] ?? '');

if ($lead_id <= 0 || empty($status)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid lead ID and status are required.']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    if (!empty($notes)) {
        $stmt = $pdo->prepare("UPDATE `leads` SET `status` = :status, `notes` = :notes, `updated_at` = NOW() WHERE `id` = :id");
        $stmt->execute([':status' => $status, ':notes' => $notes, ':id' => $lead_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE `leads` SET `status` = :status, `updated_at` = NOW() WHERE `id` = :id");
        $stmt->execute([':status' => $status, ':id' => $lead_id]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Lead updated successfully.',
        'updated_id' => $lead_id,
        'new_status' => $status
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
