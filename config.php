<?php
// =============================================
// KONFIGURASI DATABASE PPOB
// =============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_ppob');

// Koneksi Database
function koneksi() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Start Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek Login
function cekLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

// Cek Admin
function cekAdmin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
        header("Location: index.php");
        exit;
    }
}

// Format Rupiah
function rupiah($nominal) {
    return 'Rp ' . number_format($nominal, 0, ',', '.');
}

// Generate Invoice
function generateInvoice() {
    return 'INV' . date('Ymd') . rand(1000, 9999);
}

// Get Pengaturan
function getPengaturan($key) {
    $conn = koneksi();
    $stmt = $conn->prepare("SELECT nilai FROM pengaturan WHERE nama_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['nilai'];
    }
    return null;
}

// Update Saldo User
function updateSaldo($user_id, $nominal, $tipe = 'kurang') {
    $conn = koneksi();
    if ($tipe == 'tambah') {
        $sql = "UPDATE users SET saldo = saldo + ? WHERE id = ?";
    } else {
        $sql = "UPDATE users SET saldo = saldo - ? WHERE id = ?";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $nominal, $user_id);
    return $stmt->execute();
}

// Get Saldo User
function getSaldo($user_id) {
    $conn = koneksi();
    $stmt = $conn->prepare("SELECT saldo FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['saldo'];
    }
    return 0;
}

// Alert Message
function setAlert($type, $message) {
    $_SESSION['alert'] = ['type' => $type, 'message' => $message];
}

function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}
?>
