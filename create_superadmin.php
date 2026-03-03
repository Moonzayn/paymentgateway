<?php
require_once 'config.php';

$conn = koneksi();

$username = 'admin';
$password = 'admin123';
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$nama_lengkap = 'Super Admin';
$email = 'admin@ppob.com';
$no_hp = '081234567890';

$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    
    $stmt = $conn->prepare("UPDATE users SET password = ?, is_super_admin = 'yes' WHERE id = ?");
    $stmt->bind_param("si", $password_hash, $user_id);
    $stmt->execute();
    
    echo "✅ Akun super admin sudah ada, password di-reset!<br>";
    echo "Username: <strong>$username</strong><br>";
    echo "Password: <strong>$password</strong>";
} else {
    $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, no_hp, role, status, is_super_admin) VALUES (?, ?, ?, ?, ?, 'admin', 'active', 'yes')");
    $stmt->bind_param("ssssss", $username, $password_hash, $nama_lengkap, $email, $no_hp);
    
    if ($stmt->execute()) {
        echo "✅ Akun super admin berhasil dibuat!<br>";
        echo "Username: <strong>$username</strong><br>";
        echo "Password: <strong>$password</strong>";
    } else {
        echo "❌ Gagal membuat akun: " . $conn->error;
    }
}

$conn->close();
