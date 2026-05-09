<?php
/**
 * Test Inquiry PLN
 * Endpoint: https://api.digiflazz.com/v1/inquiry-pln
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/digiflazz.php';

$customer_no = '45025389623'; // Nomor PLN untuk test

echo "=== INQUIRY PLN TEST ===\n";
echo "Customer No: $customer_no\n\n";

// Gunakan Digiflazz API
$df = new DigiflazzAPI();
$result = $df->inquiryPln($customer_no);

echo "Result:\n";
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";