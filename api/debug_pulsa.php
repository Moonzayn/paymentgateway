<?php
/**
 * Debug Pulsa Purchase
 * Simulate what pulsa.php does
 */

require_once '../config.php';
require_once '../aggregator/index.php';

header('Content-Type: text/plain');

echo "=== Debug Pulsa Purchase ===\n\n";

// Simulate form submission
$productId = intval($_GET['product_id'] ?? 1); // TSEL5 = ID 1
$noHp = $_GET['no_hp'] ?? '085183059699';
$userId = 1; // Assuming user_id 1 for testing

echo "Input:\n";
echo "  Product ID: $productId\n";
echo "  No HP: $noHp\n";
echo "  User ID: $userId\n\n";

// Get product
$conn = koneksi();
$stmt = $conn->prepare("SELECT * FROM produk WHERE id = ?");
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    echo "ERROR: Produk tidak ditemukan!\n";
    exit;
}

echo "Product:\n";
echo "  SKU: {$product['sku_code']}\n";
echo "  Provider: {$product['provider']}\n";
echo "  Nominal: {$product['nominal']}\n";
echo "  Harga Jual: Rp " . number_format($product['harga_jual']) . "\n\n";

// Check saldo
$saldo = getSaldo($userId);
echo "User Saldo: Rp " . number_format($saldo) . "\n";

if ($saldo < $product['harga_jual']) {
    echo "ERROR: Saldo tidak mencukupi!\n";
    exit;
}

// Generate ref_id
$refId = 'ppob_' . date('ymdHis') . rand(100, 999);
$invoice = 'INV' . date('Ymd') . rand(1000, 9999);
echo "\nRef ID: $refId\n";
echo "Invoice: $invoice\n\n";

// Execute purchase
echo "Executing purchase...\n";
$result = purchasePulsa($productId, $noHp, $refId);
echo "Result:\n";
print_r($result);

// Retry if pending
if ($result['status'] === 'Pending') {
    echo "\nRetrying with same ref_id...\n";
    $result = purchasePulsa($productId, $noHp, $refId);
    echo "Result:\n";
    print_r($result);
}

// Record transaction
if ($result['status'] === 'Sukses') {
    $harga = $product['harga_jual'];
    $saldoSebelum = $saldo;
    $saldoSesudah = $saldo - $harga;
    $sn = $result['sn'] ?? '';
    $keterangan = 'Sukses. SN: ' . $sn;

    $stmt = $conn->prepare("INSERT INTO transaksi (user_id, produk_id, no_invoice, ref_id, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan, created_at) VALUES (?, ?, ?, ?, 'pulsa', ?, ?, ?, 0, ?, ?, ?, 'success', ?, NOW())");
    $stmt->bind_param("iisssdddddss", $userId, $productId, $invoice, $refId, $noHp, $product['nominal'], $harga, $harga, $saldoSebelum, $saldoSesudah, $keterangan);

    if ($stmt->execute()) {
        echo "\nTransaction recorded successfully!\n";
        updateSaldo($userId, $harga, 'kurang');
        echo "Saldo updated: -Rp " . number_format($harga) . "\n";
    } else {
        echo "\nERROR recording transaction: " . $stmt->error . "\n";
    }
} elseif ($result['status'] === 'Pending') {
    $harga = $product['harga_jual'];
    $keterangan = 'Pending. Menunggu konfirmasi...';

    $stmt = $conn->prepare("INSERT INTO transaksi (user_id, produk_id, no_invoice, ref_id, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan, created_at) VALUES (?, ?, ?, ?, 'pulsa', ?, ?, ?, 0, ?, ?, ?, 'pending', ?, NOW())");
    $stmt->bind_param("iissssdddddds", $userId, $productId, $invoice, $refId, $noHp, $product['nominal'], $harga, $harga, $saldo, $saldo, $keterangan);

    if ($stmt->execute()) {
        echo "\nPending transaction recorded!\n";
    } else {
        echo "\nERROR: " . $stmt->error . "\n";
    }
} else {
    echo "\nPurchase failed: {$result['message']}\n";
}

// Check recent transactions
echo "\n=== Recent Transactions ===\n";
$result = $conn->query("SELECT * FROM transaksi WHERE user_id = $userId ORDER BY created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    echo "- [{$row['status']}] {$row['no_invoice']} | {$row['no_tujuan']} | Rp " . number_format($row['total_bayar']) . " | {$row['keterangan']}\n";
}

echo "\nDone!\n";
