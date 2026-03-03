<?php
require_once 'config.php';

$conn = koneksi();

echo "<h3>Check Stores Status</h3>";
$stores = $conn->query("SELECT * FROM stores");
while ($s = $stores->fetch_assoc()) {
    echo "Store ID: " . $s['id'] . " | Nama: " . $s['nama_toko'] . " | Status: " . $s['status'] . "<br>";
}

echo "<h3>Check Store Users</h3>";
$su = $conn->query("SELECT * FROM store_users");
while ($s = $su->fetch_assoc()) {
    echo "User ID: " . $s['user_id'] . " | Store ID: " . $s['store_id'] . " | Role: " . $s['role'] . "<br>";
}

echo "<h3>Check Transactions for Store ID 2</h3>";
$trx = $conn->query("SELECT * FROM transaksi_pos WHERE store_id = 2 ORDER BY created_at DESC LIMIT 10");
echo "Jumlah: " . $trx->num_rows . "<br>";
while ($t = $trx->fetch_assoc()) {
    echo "Invoice: " . $t['no_invoice'] . " | Total: " . $t['total_bayar'] . " | Status: " . $t['status'] . "<br>";
}
