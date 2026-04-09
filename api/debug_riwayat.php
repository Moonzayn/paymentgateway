<?php
/**
 * Debug: Check API returns same as website
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
$conn = koneksi();

$user_id = 1; // adjust as needed

$result = $conn->query("SELECT t.id, t.no_invoice, t.ref_id, t.jenis_transaksi, t.no_tujuan, t.nominal, t.total_bayar, t.status, t.keterangan, t.created_at, p.nama_produk, p.provider
FROM transaksi t 
LEFT JOIN produk p ON t.produk_id = p.id 
WHERE t.user_id = $user_id 
ORDER BY t.created_at DESC LIMIT 15");

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}

echo json_encode([
    'success' => true,
    'count' => count($transactions),
    'transactions' => $transactions
]);
