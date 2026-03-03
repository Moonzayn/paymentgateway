<?php
require_once 'config.php';
cekLogin();

$conn = koneksi();
$store_id = $_SESSION['current_store_id'] ?? 2;
$user_id = $_SESSION['user_id'] ?? 5;

echo "Store ID: $store_id<br>";
echo "User ID: $user_id<br>";

$tanggal_dari = date('Y-m-01');
$tanggal_sampai = date('Y-m-d');

echo "Dari: $tanggal_dari<br>";
echo "Sampai: $tanggal_sampai<br><br>";

$where = "WHERE tp.store_id = $store_id AND DATE(tp.created_at) BETWEEN '$tanggal_dari' AND '$tanggal_sampai' AND tp.status = 'success'";

$sql = "
    SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(tp.total_bayar), 0) as total_pendapatan,
        COALESCE(SUM(tp.total_item), 0) as total_item,
        COALESCE(SUM(CASE WHEN tp.metode_bayar = 'qris' THEN tp.total_bayar ELSE 0 END), 0) as total_qris,
        COALESCE(SUM(CASE WHEN tp.metode_bayar = 'cash' THEN tp.total_bayar ELSE 0 END), 0) as total_cash
    FROM transaksi_pos tp
    $where
";

echo "SQL: $sql<br><br>";

$summary = $conn->query($sql);
$summaryResult = $summary->fetch_assoc();

print_r($summaryResult);

echo "<br><br>";

$transaksiList = $conn->query("
    SELECT tp.*, u.nama_lengkap as kasir
    FROM transaksi_pos tp
    JOIN users u ON tp.user_id = u.id
    $where
    ORDER BY tp.created_at DESC
    LIMIT 10
");

echo "Jumlah transaksi: " . $transaksiList->num_rows . "<br><br>";

while ($row = $transaksiList->fetch_assoc()) {
    echo "Invoice: " . $row['no_invoice'] . " | Total: " . $row['total_bayar'] . " | Metode: " . $row['metode_bayar'] . "<br>";
}
