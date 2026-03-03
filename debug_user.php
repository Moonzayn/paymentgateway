<?php
require_once 'config.php';

$conn = koneksi();

echo "<h3>Check User moonshine</h3>";
$user = $conn->query("SELECT * FROM users WHERE username = 'moonshine'");
if ($user->num_rows > 0) {
    $u = $user->fetch_assoc();
    echo "User ID: " . $u['id'] . "<br>";
    echo "Nama: " . $u['nama_lengkap'] . "<br>";
    echo "Role: " . $u['role'] . "<br>";
    echo "is_super_admin: " . $u['is_super_admin'] . "<br>";
    
    echo "<h3>Store Users untuk user ini</h3>";
    $su = $conn->query("SELECT * FROM store_users WHERE user_id = " . $u['id']);
    if ($su->num_rows > 0) {
        while ($s = $su->fetch_assoc()) {
            echo "Store ID: " . $s['store_id'] . " | Role: " . $s['role'] . "<br>";
        }
    } else {
        echo "Tidak ada store!<br>";
    }
    
    echo "<h3>Stores</h3>";
    $stores = $conn->query("SELECT * FROM stores");
    while ($s = $stores->fetch_assoc()) {
        echo "Store ID: " . $s['id'] . " | Nama: " . $s['nama_toko'] . " | Status: " . $s['status'] . "<br>";
    }
} else {
    echo "User tidak ditemukan";
}
