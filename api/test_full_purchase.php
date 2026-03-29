<?php
/**
 * Full purchase test
 */
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config.php';

echo "1. Testing aggregator manager...\n";
require_once '../aggregator/index.php';

$manager = getAggregatorManager();
echo "   Manager loaded: " . ($manager ? "OK" : "FAIL") . "\n";
echo "   Available: " . implode(', ', $manager->getAvailable()) . "\n";

$agg = $manager->getActive();
echo "   Active: " . ($agg ? $agg->getName() : "NONE") . "\n\n";

echo "2. Testing purchasePulsa function...\n";
$productId = 1;
$noHp = '085183999999';
$refId = 'test_full_' . time();

echo "   productId=$productId, noHp=$noHp, refId=$refId\n";

$result = purchasePulsa($productId, $noHp, $refId, true);

echo "   Result:\n";
echo "   - status: {$result['status']}\n";
echo "   - rc: {$result['rc']}\n";
echo "   - message: {$result['message']}\n";
echo "   - sn: " . ($result['sn'] ?? '-') . "\n\n";

echo "3. Testing complete flow...\n";

// Check if product has SKU
$conn = koneksi();
$stmt = $conn->prepare("SELECT * FROM produk WHERE id = ?");
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

echo "   Product: SKU={$product['sku_code']}, harga={$product['harga_jual']}\n";

if (empty($product['sku_code'])) {
    echo "   ERROR: SKU is empty!\n";
} else {
    echo "   SKU OK\n";
}

echo "\nDONE\n";
