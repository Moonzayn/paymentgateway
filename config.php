<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {

    // === SESSION CONFIG (HARUS DI SINI) ===
    ini_set('session.cookie_httponly', '1');
    ini_set(
        'session.cookie_secure',
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0'
    );

    if (PHP_VERSION_ID >= 70300) {
        // Use Lax for development, Strict for production
        $is_dev = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false;
        ini_set('session.cookie_samesite', $is_dev ? 'Lax' : 'Strict');
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '3600');

    session_start();

    // Regenerate session ID every 30 minutes
    if (!isset($_SESSION['created']) || time() - $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }

    // CSRF token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Include security functions
require_once __DIR__ . '/security.php';
applySecurityHeaders();

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
    // Check if user is in 2FA required setup flow
    if (!isset($_SESSION['user_id']) && isset($_SESSION['2fa_required_user_id'])) {
        header("Location: setup_2fa.php?required=1");
        exit;
    }

    // Normal login check
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    // Check if user has force_2fa but hasn't set up 2FA yet
    $currentPage = basename($_SERVER['PHP_SELF']);
    if ($currentPage !== 'setup_2fa.php' && $currentPage !== '2fa_setup.php' && $currentPage !== 'login.php') {
        $conn = koneksi();
        $user_id = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("SELECT force_2fa FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && $user['force_2fa'] === 'yes') {
            // Check if user has enabled 2FA
            $stmt2 = $conn->prepare("SELECT enabled FROM user_2fa WHERE user_id = ? AND enabled = 'yes'");
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            if ($result2->num_rows === 0) {
                header("Location: setup_2fa.php?required=1");
                exit;
            }
        }
        
        $conn->close();
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

function initStoreSession($user_id) {
    $conn = koneksi();
    
    $isSuperAdmin = false;
    $stmt = $conn->prepare("SELECT is_super_admin FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $isSuperAdmin = $row['is_super_admin'] == 'yes';
    }
    $_SESSION['is_super_admin'] = $isSuperAdmin ? 'yes' : 'no';
    
    if ($isSuperAdmin) {
        return;
    }
    
    $stmt = $conn->prepare("
        SELECT su.store_id, su.role, s.nama_toko 
        FROM store_users su 
        JOIN stores s ON su.store_id = s.id 
        WHERE su.user_id = ? AND s.status = 'active'
        ORDER BY su.created_at ASC
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $_SESSION['current_store_id'] = $row['store_id'];
        $_SESSION['current_store_name'] = $row['nama_toko'];
        $_SESSION['current_store_role'] = $row['role'];
    }
}

// ===========================================
// RATE LIMITING - Prevent API Abuse
// ===========================================
function checkRateLimit($endpoint, $maxRequests = 10, $windowSeconds = 60) {
    $conn = koneksi();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now = date('Y-m-d H:i:s');
    $windowStart = date('Y-m-d H:i:s', strtotime("-{$windowSeconds} seconds"));
    
    // Clean old records
    $stmt = $conn->prepare("DELETE FROM rate_limits WHERE window_start < ?");
    $stmt->bind_param("s", $windowStart);
    $stmt->execute();
    
    // Check current count
    $stmt = $conn->prepare("SELECT requests_count FROM rate_limits WHERE ip_address = ? AND endpoint = ? AND window_start >= ?");
    $stmt->bind_param("sss", $ip, $endpoint, $windowStart);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['requests_count'] >= $maxRequests) {
            return false; // Rate limit exceeded
        }
        // Increment
        $stmt = $conn->prepare("UPDATE rate_limits SET requests_count = requests_count + 1 WHERE ip_address = ? AND endpoint = ? AND window_start >= ?");
        $stmt->bind_param("sss", $ip, $endpoint, $windowStart);
        $stmt->execute();
    } else {
        // Insert new
        $stmt = $conn->prepare("INSERT INTO rate_limits (ip_address, endpoint, requests_count, window_start) VALUES (?, ?, 1, NOW())");
        $stmt->bind_param("ss", $ip, $endpoint);
        $stmt->execute();
    }
    
    return true;
}

// ===========================================
// TRANSACTION LOCK - Prevent Race Condition
// ===========================================
function acquireLock($lockKey, $userId, $expiresInSeconds = 30) {
    $conn = koneksi();
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInSeconds} seconds"));
    
    // Try to acquire lock
    $stmt = $conn->prepare("INSERT INTO transaction_locks (lock_key, user_id, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id = id");
    $stmt->bind_param("sis", $lockKey, $userId, $expiresAt);
    $stmt->execute();
    
    // Check if we got the lock
    $stmt = $conn->prepare("SELECT id FROM transaction_locks WHERE lock_key = ? AND user_id = ? AND expires_at > NOW()");
    $stmt->bind_param("si", $lockKey, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

function releaseLock($lockKey, $userId) {
    $conn = koneksi();
    $stmt = $conn->prepare("DELETE FROM transaction_locks WHERE lock_key = ? AND user_id = ?");
    $stmt->bind_param("si", $lockKey, $userId);
    return $stmt->execute();
}

function isLocked($lockKey) {
    $conn = koneksi();
    $stmt = $conn->prepare("SELECT id FROM transaction_locks WHERE lock_key = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $lockKey);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// ===========================================
// EMAIL QUEUE - Async Email Sending
// ===========================================
function queueEmail($toEmail, $subject, $body) {
    $conn = koneksi();
    $stmt = $conn->prepare("INSERT INTO email_queue (to_email, subject, body) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $toEmail, $subject, $body);
    return $stmt->execute();
}

function processEmailQueue($limit = 10) {
    $conn = koneksi();

    // Get pending emails
    $stmt = $conn->prepare("SELECT * FROM email_queue WHERE status = 'pending' AND attempts < max_attempts ORDER BY created_at ASC LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($email = $result->fetch_assoc()) {
        // Try to send email
        $sent = sendEmail($email['to_email'], $email['subject'], $email['body']);

        if ($sent) {
            $stmt = $conn->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE email_queue SET attempts = attempts + 1 WHERE id = ?");
        }
        $stmt->bind_param("i", $email['id']);
        $stmt->execute();
    }
}

function sendEmail($to, $subject, $body) {
    // Simple mail() wrapper - replace with SMTP in production
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type:text/html;charset=UTF-8\r\n";
    $headers .= "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n";

    return mail($to, $subject, $body, $headers);
}

// ===========================================
// CORS HEADERS - For Flutter Mobile App
// ===========================================
function setCorsHeaders() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");

    // Handle preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// ===========================================
// API RESPONSE HELPERS
// ===========================================
function apiResponse($success, $data = null, $message = '', $error = null) {
    header('Content-Type: application/json');

    $response = ['success' => $success];

    if ($data !== null) {
        $response['data'] = $data;
    }

    if ($message) {
        $response['message'] = $message;
    }

    if ($error) {
        $response['error'] = $error;
    }

    echo json_encode($response);
    exit;
}

function apiSuccess($data = null, $message = 'Success') {
    apiResponse(true, $data, $message);
}

function apiError($message, $code = 'ERROR', $httpCode = 400) {
    http_response_code($httpCode);
    apiResponse(false, null, $message, [
        'code' => $code,
        'message' => $message
    ]);
}

// ===========================================
// API KEY AUTHENTICATION
// ===========================================
function generateApiToken($userId, $name = 'Flutter App', $platform = 'flutter', $deviceId = null, $expiresInDays = 365) {
    $conn = koneksi();

    // Generate unique API key and secret
    $apiKey = 'sk_' . bin2hex(random_bytes(24));
    $secretKey = 'secret_' . bin2hex(random_bytes(32));

    // Set expiration date
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInDays} days"));

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO api_keys (user_id, api_key, secret_key, name, platform, device_id, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $userId, $apiKey, $secretKey, $name, $platform, $deviceId, $expiresAt);

    if ($stmt->execute()) {
        return [
            'api_key' => $apiKey,
            'secret_key' => $secretKey,
            'expires_at' => $expiresAt
        ];
    }

    return null;
}

function validateApiKey($apiKey) {
    if (empty($apiKey)) {
        return null;
    }

    // Remove "Bearer " prefix if present
    if (strpos($apiKey, 'Bearer ') === 0) {
        $apiKey = substr($apiKey, 7);
    }

    $conn = koneksi();
    $stmt = $conn->prepare("
        SELECT ak.*, u.username, u.nama_lengkap, u.role, u.saldo, u.status as user_status
        FROM api_keys ak
        JOIN users u ON ak.user_id = u.id
        WHERE ak.api_key = ? AND ak.is_active = 'yes'
    ");
    $stmt->bind_param("s", $apiKey);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Check expiration
        if ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
            return null;
        }

        // Check user status
        if ($row['user_status'] !== 'active') {
            return null;
        }

        // Update last used
        $stmt = $conn->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $row['id']);
        $stmt->execute();

        return $row;
    }

    return null;
}

function invalidateApiKey($apiKey) {
    if (empty($apiKey)) {
        return false;
    }

    if (strpos($apiKey, 'Bearer ') === 0) {
        $apiKey = substr($apiKey, 7);
    }

    $conn = koneksi();
    $stmt = $conn->prepare("UPDATE api_keys SET is_active = 'no' WHERE api_key = ?");
    $stmt->bind_param("s", $apiKey);
    return $stmt->execute();
}

function getApiKeyUser($apiKey) {
    return validateApiKey($apiKey);
}

// ===========================================
// API RESPONSE HELPER (Legacy)
// ===========================================
function saveApiResponse($transaksiId, $response) {
    $conn = koneksi();
    $stmt = $conn->prepare("UPDATE transaksi SET api_response = ? WHERE id = ?");
    $jsonResponse = json_encode($response);
    $stmt->bind_param("si", $jsonResponse, $transaksiId);
    return $stmt->execute();
}
?>
