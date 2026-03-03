<?php
require_once 'config.php';
cekLogin();

$conn = koneksi();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['nama_lengkap'];

echo "<h3>Debug Session untuk: $username (ID: $user_id)</h3>";
echo "Store ID: " . ($_SESSION['current_store_id'] ?? 'N/A') . "<br>";
echo "Store Name: " . ($_SESSION['current_store_name'] ?? 'N/A') . "<br>";
echo "Store Role: " . ($_SESSION['current_store_role'] ?? 'N/A') . "<br>";
echo "Is Super Admin: " . ($_SESSION['is_super_admin'] ?? 'N/A') . "<br>";

echo "<h3>Role di Store</h3>";
$su = $conn->prepare("
    SELECT su.*, s.nama_toko 
    FROM store_users su 
    JOIN stores s ON su.store_id = s.id 
    WHERE su.user_id = ?
");
$su->bind_param("i", $user_id);
$su->execute();
$result = $su->get_result();

while ($row = $result->fetch_assoc()) {
    echo "Store: " . $row['nama_toko'] . " | Role: " . $row['role'] . "<br>";
}

echo "<h3>Transaksi POS Terbaru</h3>";
$trx = $conn->query("SELECT * FROM transaksi_pos ORDER BY created_at DESC LIMIT 5");
while ($row = $trx->fetch_assoc()) {
    echo "Invoice: " . $row['no_invoice'] . " | Total: " . $row['total_bayar'] . " | Metode: " . $row['metode_bayar'] . "<br>";
}
