<?php
/**
 * Debug: Check recent transactions
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
$conn = koneksi();

$result = $conn->query("SELECT id, no_invoice, ref_id, jenis_transaksi, no_tujuan, nominal, harga, total_bayar, status, keterangan FROM transaksi ORDER BY id DESC LIMIT 10");

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}

echo json_encode([
    'success' => true,
    'count' => count($transactions),
    'transactions' => $transactions
]);
