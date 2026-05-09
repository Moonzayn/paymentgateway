<?php
/**
 * API Endpoint: Inquiry PLN
 * Method: GET/POST
 * URL: http://localhost/payment/api/inquiry_pln.php?customer_no=45025389623
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../digiflazz.php';

header('Content-Type: application/json');

// Get customer number from request
$customer_no = $_GET['customer_no'] ?? $_POST['customer_no'] ?? '';

if (empty($customer_no)) {
    echo json_encode([
        'success' => false,
        'message' => 'Parameter customer_no wajib diisi'
    ], JSON_PRETTY_PRINT);
    exit;
}

// Validate customer_no (PLN ID should be numeric, 8-12 digits typically)
if (!preg_match('/^[0-9]{8,12}$/', $customer_no)) {
    echo json_encode([
        'success' => false,
        'message' => 'Format customer_no tidak valid'
    ], JSON_PRETTY_PRINT);
    exit;
}

// Use Digiflazz API
$df = new DigiflazzAPI();
error_log("inquiryPln - testing mode: " . ($df->isTesting() ? 'true' : 'false'));

$result = $df->inquiryPln($customer_no);

error_log("inquiryPln result: " . json_encode($result));

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);