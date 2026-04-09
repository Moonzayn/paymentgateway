<?php
/**
 * Simple test - click this in browser
 */

header('Content-Type: application/json');

$testData = json_encode([
    'user_id' => 1,
    'no_hp' => '083175360504',
    'produk_id' => 1
]);

// Simulate the mobile API call
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

echo "Testing mobile_beli_pulsa.php...\n\n";

// Include the API
include __DIR__ . '/mobile_beli_pulsa.php';
