<?php
/**
 * API v1 - 2FA Verify Endpoint
 * Method: POST
 * Header: Authorization: Bearer <api_key>
 *
 * Verify 2FA code and return token if valid
 * Request: { "code": "123456" }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../totp_helper.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

// Require authentication
$currentUser = requireAuth();

$conn = koneksi();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$code = trim($input['code'] ?? '');

if (empty($code)) {
    apiError('Kode 2FA diperlukan', 'CODE_REQUIRED');
}

// Get user's 2FA secret
$stmt = $conn->prepare("SELECT * FROM user_2fa WHERE user_id = ? AND enabled = 'yes'");
$stmt->bind_param("i", $currentUser['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$twoFA = $result->fetch_assoc();

if (!$twoFA) {
    apiError('2FA tidak diaktifkan untuk user ini', '2FA_NOT_ENABLED');
}

$secret = $twoFA['secret'];

// Verify the code
$totp = new TOTPHelper();
$isValid = $totp->verifyCode($secret, $code);

if (!$isValid) {
    // Log failed attempt
    $stmtLog = $conn->prepare("INSERT INTO user_2fa_login_attempts (user_id, ip_address, status) VALUES (?, ?, 'failed')");
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmtLog->bind_param("is", $currentUser['user_id'], $ip);
    $stmtLog->execute();

    apiError('Kode 2FA tidak valid', 'INVALID_CODE');
}

// Log successful attempt
$stmtLog = $conn->prepare("INSERT INTO user_2fa_login_attempts (user_id, ip_address, status) VALUES (?, ?, 'success')");
$stmtLog->bind_param("is", $currentUser['user_id'], $ip);
$stmtLog->execute();

// Generate API token if not already exists
$stmtToken = $conn->prepare("SELECT * FROM api_keys WHERE user_id = ? AND is_active = 'yes' ORDER BY created_at DESC LIMIT 1");
$stmtToken->bind_param("i", $currentUser['user_id']);
$stmtToken->execute();
$resultToken = $stmtToken->get_result();
$existingToken = $resultToken->fetch_assoc();

if ($existingToken) {
    // Update last used
    $stmtUpdate = $conn->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?");
    $stmtUpdate->bind_param("i", $existingToken['id']);
    $stmtUpdate->execute();

    $token = $existingToken['api_key'];
    $secretKey = $existingToken['secret_key'];
} else {
    // Generate new token
    $tokenData = generateApiToken($currentUser['user_id'], 'Flutter App', 'flutter');

    if (!$tokenData) {
        apiError('Gagal membuat token', 'TOKEN_ERROR', 500);
    }

    $token = $tokenData['api_key'];
    $secretKey = $tokenData['secret_key'];
}

// Get user data
$stmtUser = $conn->prepare("SELECT id, username, nama_lengkap, email, no_hp, role, saldo FROM users WHERE id = ?");
$stmtUser->bind_param("i", $currentUser['user_id']);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
$user = $resultUser->fetch_assoc();

$conn->close();

apiSuccess([
    'token' => $token,
    'secret' => $secretKey,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'nama_lengkap' => $user['nama_lengkap'],
        'email' => $user['email'],
        'no_hp' => $user['no_hp'],
        'role' => $user['role'],
        'saldo' => (float)$user['saldo'],
        'saldo_display' => 'Rp ' . number_format($user['saldo'], 0, ',', '.')
    ]
], 'Verifikasi 2FA berhasil');
