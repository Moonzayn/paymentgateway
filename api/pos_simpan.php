<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_store_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$metode_bayar = $_POST['metode_bayar'] ?? '';
$reference = $_POST['reference'] ?? '';
$qris_string = $_POST['qris_string'] ?? '';
$uang_diberikan = (int)$_POST['uang_diberikan'] ?? 0;
$items = json_decode($_POST['items'] ?? '[]', true);

if (!$metode_bayar || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$conn = koneksi();
$store_id = $_SESSION['current_store_id'];
$user_id = $_SESSION['user_id'];

$subtotal = 0;
$total_item = 0;

foreach ($items as $item) {
    $subtotal += $item['harga'] * $item['qty'];
    $total_item += $item['qty'];
}

$kembalian = 0;
if ($metode_bayar === 'cash') {
    $kembalian = $uang_diberikan - $subtotal;
}

$no_invoice = 'POS-' . date('YmdHis') . rand(100, 999);

$conn->begin_transaction();

try {
    $sql = "INSERT INTO transaksi_pos 
        (store_id, user_id, no_invoice, total_item, subtotal, total_bayar, metode_bayar, qris_reference, qris_string, uang_diberikan, kembalian, status)
        VALUES ($store_id, $user_id, '$no_invoice', $total_item, $subtotal, $subtotal, '$metode_bayar', '$reference', '$qris_string', $uang_diberikan, $kembalian, 'pending')";
    
    if (!$conn->query($sql)) {
        throw new Exception('Gagal insert transaksi: ' . $conn->error);
    }
    
    $transaksi_id = $conn->insert_id;
    
    foreach ($items as $item) {
        $nama_item = $item['nama'];
        $qty = $item['qty'];
        $harga = $item['harga'];
        $total_harga = $harga * $qty;
        $is_manual = $item['isManual'] ? 'yes' : 'no';
        $produk_id = $item['isManual'] ? 'NULL' : $item['id'];
        
        $conn->query("
            INSERT INTO transaksi_pos_detail 
            (transaksi_id, produk_id, nama_item, qty, harga_saat_transaksi, total_harga, is_manual)
            VALUES ($transaksi_id, $produk_id, '$nama_item', $qty, $harga, $total_harga, '$is_manual')
        ");
        
        if (!$item['isManual'] && isset($item['stok'])) {
            $conn->query("UPDATE produk_pos SET stok = stok - $qty WHERE id = " . $item['id'] . " AND store_id = $store_id");
        }
    }
    
    if ($metode_bayar === 'cash') {
        $status = 'success';
        $conn->query("UPDATE transaksi_pos SET status = 'success', uang_diberikan = $uang_diberikan, kembalian = $kembalian WHERE id = $transaksi_id");
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'invoice' => $no_invoice,
        'transaksi_id' => $transaksi_id,
        'total' => $subtotal,
        'kembalian' => $kembalian,
        'status' => $status
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
