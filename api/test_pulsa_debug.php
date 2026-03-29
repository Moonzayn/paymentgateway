<?php
/**
 * Test pulsa purchase step by step
 */

require_once '../config.php';
require_once '../aggregator/index.php';

header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== STEP BY STEP DEBUG ===\n\n";

// Step 1: Get product
echo "STEP 1: Get product (ID=1)\n";
$productId = 1;
$conn = koneksi();
$stmt = $conn->prepare("SELECT * FROM produk WHERE id = ?");
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    echo "ERROR: Product not found!\n";
    exit;
}
echo "OK: SKU={$product['sku_code']}, Harga={$product['harga_jual']}\n\n";

// Step 2: Check saldo
echo "STEP 2: Check saldo (user_id=1)\n";
$userId = 1;
$saldo = getSaldo($userId);
echo "OK: Saldo=Rp " . number_format($saldo) . "\n\n";

// Step 3: Check rate limit
echo "STEP 3: Check rate limit\n";
$rateLimit = checkRateLimit('purchase_pulsa', 10, 60);
echo $rateLimit ? "OK: Rate limit passed\n\n" : "ERROR: Rate limit exceeded\n\n";
if (!$rateLimit) exit;

// Step 4: Acquire lock
echo "STEP 4: Acquire lock\n";
$noHp = '085183999999';
$refId = 'test_' . date('ymdHis');
$lockKey = 'pulsa_' . md5($noHp . $productId);
echo "Lock key: $lockKey\n";

if (!acquireLock($lockKey, $userId, 30)) {
    echo "ERROR: Could not acquire lock!\n";
    exit;
}
echo "OK: Lock acquired\n\n";

// Step 5: Execute purchase
echo "STEP 5: Execute purchase (testing mode)\n";
$result = purchasePulsa($productId, $noHp, $refId, true); // TRUE for testing
echo "Result:\n";
echo "  status: {$result['status']}\n";
echo "  rc: {$result['rc']}\n";
echo "  message: {$result['message']}\n";
echo "  sn: " . ($result['sn'] ?? '-') . "\n\n";

// Step 6: Record transaction
echo "STEP 6: Record transaction\n";
$invoice = 'INV' . date('Ymd') . rand(1000, 9999);
$harga = $product['harga_jual'];
$sn = $result['sn'] ?? '';
$keterangan = $result['status'] === 'Sukses' ? "Sukses. SN: $sn" : "Pending. Menunggu...";

$stmt = $conn->prepare("INSERT INTO transaksi (user_id, produk_id, no_invoice, ref_id, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan) VALUES (?, ?, ?, ?, 'pulsa', ?, ?, ?, 0, ?, ?, ?, ?, ?)");
$stmt->bind_param("iissssddddds", $userId, $productId, $invoice, $refId, $noHp, $product['nominal'], $harga, $harga, $saldo, $saldo, $result['status'], $keterangan);

if ($stmt->execute()) {
    echo "OK: Transaction recorded!\n";
    echo "  Invoice: $invoice\n";
    echo "  Status: {$result['status']}\n";
} else {
    echo "ERROR: " . $stmt->error . "\n";
}

// Step 7: Release lock
echo "\nSTEP 7: Release lock\n";
releaseLock($lockKey, $userId);
echo "OK: Lock released\n";

// Show recent transactions
echo "\n=== RECENT TRANSACTIONS ===\n";
$result = $conn->query("SELECT * FROM transaksi WHERE user_id = $userId ORDER BY created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    echo "[{$row['status']}] {$row['no_invoice']} | {$row['no_tujuan']} | Rp " . number_format($row['total_bayar']) . "\n";
}

echo "\n=== DONE ===\n";
