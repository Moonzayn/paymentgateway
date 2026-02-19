<?php
// =============================================
// KONFIGURASI DATABASE PPOB - SECURED
// =============================================

// Security: Define secure session parameters BEFORE session_start
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600); // 1 hour

// Start Session dengan keamanan
if (session_status() == PHP_SESSION_NONE) {
    session_start();
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Regenerate session ID every 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Initialize CSRF token if not exists
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_ppob');

// Security Constants
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutes

// Koneksi Database
function koneksi() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Sistem sedang maintenance. Silakan coba lagi nanti.");
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// CSRF Token Functions
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Rate Limiting for Login
function checkLoginAttempts($identifier) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    $now = time();
    $key = md5($identifier);
    
    if (isset($_SESSION['login_attempts'][$key])) {
        $attempts = $_SESSION['login_attempts'][$key];
        
        // Clear old attempts
        $attempts = array_filter($attempts, function($time) use ($now) {
            return $now - $time < LOGIN_TIMEOUT;
        });
        
        if (count($attempts) >= MAX_LOGIN_ATTEMPTS) {
            return false;
        }
        
        $_SESSION['login_attempts'][$key] = $attempts;
    }
    
    return true;
}

function recordLoginAttempt($identifier) {
    $key = md5($identifier);
    if (!isset($_SESSION['login_attempts'][$key])) {
        $_SESSION['login_attempts'][$key] = [];
    }
    $_SESSION['login_attempts'][$key][] = time();
}

function clearLoginAttempts($identifier) {
    $key = md5($identifier);
    unset($_SESSION['login_attempts'][$key]);
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
