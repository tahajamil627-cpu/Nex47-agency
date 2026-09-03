<?php
/**
 * NEX - 47 CATALYS'S - Lead Submission Endpoint
 * Receives lead data via POST or JSON, validates, and stores in MySQL
 */

require_once __DIR__ . '/config.php';

if (!headers_sent()) {
    header('Content-Type: application/json');
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed.']);
    exit;
}

// Read raw JSON or form-data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$client_name = trim($input['client_name'] ?? '');
$client_phone = trim($input['client_phone'] ?? '');
$brand_name = trim($input['brand_name'] ?? '');
$service_type = trim($input['service_type'] ?? 'All-in-One Growth Plan');
$budget = trim($input['budget'] ?? '$1,000 - $3,000');
$message = trim($input['message'] ?? '');
$source = trim($input['source'] ?? ($input['lead_source'] ?? 'Website Interactive Form'));
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Validation
if (empty($client_name) || empty($client_phone)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please provide both your full name and phone/WhatsApp number.'
    ]);
    exit;
}

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        INSERT INTO `leads` (`client_name`, `client_phone`, `brand_name`, `service_type`, `budget`, `message`, `lead_source`, `ip_address`, `status`)
        VALUES (:client_name, :client_phone, :brand_name, :service_type, :budget, :message, :lead_source, :ip_address, 'new')
    ");

    $stmt->execute([
        ':client_name'  => $client_name,
        ':client_phone' => $client_phone,
        ':brand_name'   => $brand_name,
        ':service_type' => $service_type,
        ':budget'       => $budget,
        ':message'      => $message,
        ':lead_source'  => $source,
        ':ip_address'   => $ip_address,
    ]);

    $leadId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Growth brief saved successfully to NEX-47 Database!',
        'lead_id' => $leadId,
        'data' => [
            'client_name' => $client_name,
            'client_phone' => $client_phone,
            'service_type' => $service_type,
            'budget' => $budget
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving lead: ' . $e->getMessage()
    ]);
}
