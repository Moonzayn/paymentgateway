<?php
require_once 'config.php';

$conn = koneksi();

echo "<h3>All Users</h3>";
$users = $conn->query("SELECT id, username, nama_lengkap, role, force_2fa FROM users");
while ($row = $users->fetch_assoc()) {
    echo "ID: {$row['id']} | Username: {$row['username']} | Role: {$row['role']} | force_2fa: {$row['force_2fa']}<br>";
}

echo "<hr><h3>2FA Status</h3>";
$result2fa = $conn->query("SELECT u.id, u.username, u.force_2fa, u2.enabled as 2fa_enabled FROM users u LEFT JOIN user_2fa u2 ON u.id = u2.user_id");
while ($row = $result2fa->fetch_assoc()) {
    echo "ID: {$row['id']} | Username: {$row['username']} | force_2fa: {$row['force_2fa']} | 2FA: " . ($row['2fa_enabled'] ?? 'N/A') . "<br>";
}

echo "<hr><h3>Check User admin</h3>";
$user = $conn->query("SELECT * FROM users WHERE username = 'admin'");
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
