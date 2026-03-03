<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$invoice = $_GET['invoice'] ?? '';

if (!$invoice) {
    echo json_encode(['success' => false, 'message' => 'Invoice tidak ditemukan']);
    exit;
}

$conn = koneksi();
$stmt = $conn->prepare("
    SELECT tp.*, u.nama_lengkap as kasir, s.nama_toko
    FROM transaksi_pos tp
    JOIN users u ON tp.user_id = u.id
    JOIN stores s ON tp.store_id = s.id
    WHERE tp.no_invoice = ?
");
$stmt->bind_param("s", $invoice);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Invoice tidak ditemukan']);
    exit;
}

$transaksi = $result->fetch_assoc();

$stmtDetail = $conn->prepare("SELECT * FROM transaksi_pos_detail WHERE transaksi_id = ?");
$stmtDetail->bind_param("i", $transaksi['id']);
$stmtDetail->execute();
$items = $stmtDetail->get_result();

$itemsList = [];
while ($item = $items->fetch_assoc()) {
    $itemsList[] = $item;
}

echo json_encode([
    'success' => true,
    'transaksi' => [
        'no_invoice' => $transaksi['no_invoice'],
        'tanggal' => date('d/m/Y H:i', strtotime($transaksi['created_at'])),
        'kasir' => $transaksi['kasir'],
        'nama_toko' => $transaksi['nama_toko'],
        'metode_bayar' => $transaksi['metode_bayar'],
        'status' => $transaksi['status'],
        'total_bayar' => $transaksi['total_bayar'],
        'uang_diberikan' => $transaksi['uang_diberikan'],
        'kembalian' => $transaksi['kembalian'],
        'total_item' => $transaksi['total_item']
    ],
    'items' => $itemsList
]);
