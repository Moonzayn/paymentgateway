<?php
require_once 'config.php';

$conn = koneksi();

$user = $conn->query("SELECT password FROM users WHERE username = 'admin'");
$row = $user->fetch_assoc();

echo "Password hash di DB: " . $row['password'] . "<br>";

// Test password
$test_passwords = ['admin123', 'password', 'admin', '123456', 'admin123!'];

foreach ($test_passwords as $pwd) {
    $hash = password_hash($pwd, PASSWORD_DEFAULT);
    echo "Testing '$pwd': " . ($hash === $row['password'] ? 'MATCH!' : 'tidak cocok') . "<br>";
}

// Cek apakah password_verify bekerja
echo "<br>Password verify test:<br>";
echo "admin123: " . (password_verify('admin123', $row['password']) ? 'BENAR' : 'SALAH') . "<br>";

$conn->close();
