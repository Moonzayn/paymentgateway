<?php
// API Login untuk Mobile App - Returns JSON
session_start();

header('Content-Type: application/json');
require_once 'config.php';

// Rate limiting check
$identifier = ($_POST['username'] ?? '') . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!checkLoginAttempts($identifier)) {
    echo json_encode([
        'success' => false,
        'message' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam 15 menit.'
    ]);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$csrf_token = $_POST['csrf_token'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Username dan password harus diisi!'
    ]);
    exit;
}

$conn = koneksi();
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    if (password_verify($password, $user['password'])) {
        // Clear login attempts
        clearLoginAttempts($identifier);

        // Check if user has 2FA enabled
        $stmt2fa = $conn->prepare("SELECT enabled FROM user_2fa WHERE user_id = ? AND enabled = 'yes'");
        $stmt2fa->bind_param("i", $user['id']);
        $stmt2fa->execute();
        $result2fa = $stmt2fa->get_result();
        $has2FA = $result2fa->num_rows > 0;
        $force2FA = $user['force_2fa'] === 'yes';

        if ($has2FA || $force2FA) {
            // Need 2FA - return special response
            $_SESSION['2fa_user_id'] = $user['id'];
            $_SESSION['2fa_required_user_id'] = $user['id']; // For cekLogin() bypass protection
            $_SESSION['2fa_pending'] = true;
            $_SESSION['2fa_username'] = $username;

            // Generate new CSRF token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            echo json_encode([
                'success' => true,
                'needs_2fa' => true,
                'user_id' => -1, // Special indicator for 2FA
                'csrf_token' => $_SESSION['csrf_token'],
                'message' => 'Verifikasi 2FA diperlukan'
            ]);
        } else {
            // No 2FA - proceed with login
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['saldo'] = $user['saldo'];
            $_SESSION['created'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            initStoreSession($user['id']);

            echo json_encode([
                'success' => true,
                'needs_2fa' => false,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'role' => $user['role'],
                    'saldo' => (float)$user['saldo']
                ],
                'csrf_token' => $_SESSION['csrf_token'],
                'message' => 'Login berhasil'
            ]);
        }
    } else {
        recordLoginAttempt($identifier);
        echo json_encode([
            'success' => false,
            'message' => 'Password salah!'
        ]);
    }
} else {
    recordLoginAttempt($identifier);
    echo json_encode([
        'success' => false,
        'message' => 'Username tidak ditemukan!'
    ]);
}

$conn->close();
