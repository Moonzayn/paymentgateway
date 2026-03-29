<?php
/**
 * Test Aggregator Integration
 * Run: http://payment.test/api/test_aggregator.php
 */

require_once '../config.php';
require_once '../aggregator/index.php';

header('Content-Type: text/plain');

echo "=== Test Aggregator Integration ===\n\n";

// Test 1: Check aggregator registered
echo "1. Check Aggregator Manager:\n";
$manager = getAggregatorManager();
$available = $manager->getAvailable();
echo "   Available: " . implode(', ', $available) . "\n";
$active = $manager->getActive();
echo "   Active: " . ($active ? $active->getName() : 'NONE') . "\n\n";

// Test 2: Check product with SKU
echo "2. Check Product (TSEL5):\n";
$conn = koneksi();
$stmt = $conn->prepare("SELECT * FROM produk WHERE sku_code = 'TSEL5' LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
if ($product) {
    echo "   ID: {$product['id']}\n";
    echo "   SKU: {$product['sku_code']}\n";
    echo "   Provider: {$product['provider']}\n";
    echo "   Nominal: {$product['nominal']}\n";
    echo "   Harga Jual: Rp " . number_format($product['harga_jual']) . "\n";
    echo "   Harga Modal: Rp " . number_format($product['harga_modal']) . "\n";
    echo "   Aggregator: {$product['aggregator']}\n\n";

    // Test 3: Try purchase
    echo "3. Test Purchase TSEL5:\n";
    $ref_id = 'test_' . date('ymdHis');
    echo "   Ref ID: $ref_id\n";
    echo "   Customer: 085183059699\n";

    $result = purchasePulsa($product['id'], '085183059699', $ref_id);
    echo "   Status: {$result['status']}\n";
    echo "   RC: {$result['rc']}\n";
    echo "   Message: {$result['message']}\n";
    echo "   SN: " . ($result['sn'] ?? '-') . "\n";
    echo "   Price: Rp " . number_format($result['price'] ?? 0) . "\n";
    echo "   Buyer Saldo: Rp " . number_format($result['buyer_last_saldo'] ?? 0) . "\n";

    // If pending, try again with same ref_id
    if ($result['status'] === 'Pending') {
        echo "\n   Retrying with same ref_id...\n";
        $result2 = purchasePulsa($product['id'], '085183059699', $ref_id);
        echo "   Status: {$result2['status']}\n";
        echo "   RC: {$result2['rc']}\n";
        echo "   SN: " . ($result2['sn'] ?? '-') . "\n";
    }
} else {
    echo "   TSEL5 not found!\n";
}

echo "\n=== Test Complete ===\n";
