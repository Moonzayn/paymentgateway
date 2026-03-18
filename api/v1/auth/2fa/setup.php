<?php
/**
 * API v1 - 2FA Setup Endpoint
 * Method: POST
 * Header: Authorization: Bearer <api_key>
 *
 * Setup 2FA for the user
 * Request: { "enable": true/false }
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
$enable = $input['enable'] ?? true;

// Check if user already has 2FA enabled
$stmt = $conn->prepare("SELECT * FROM user_2fa WHERE user_id = ?");
$stmt->bind_param("i", $currentUser['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$existing2FA = $result->fetch_assoc();

if ($enable) {
    // Setup 2FA - generate new secret
    if ($existing2FA && $existing2FA['enabled'] === 'yes') {
        apiError('2FA sudah diaktifkan', '2FA_ALREADY_ENABLED');
    }

    // Generate new secret
    $totp = new TOTPHelper();
    $secret = $totp->generateSecret();

    // Get user email
    $stmtUser = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $currentUser['user_id']);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();
    $user = $resultUser->fetch_assoc();

    // Save pending secret (not enabled yet)
    if ($existing2FA) {
        $stmtUpdate = $conn->prepare("UPDATE user_2fa SET secret = ?, enabled = 'pending', created_at = NOW() WHERE user_id = ?");
        $stmtUpdate->bind_param("si", $secret, $currentUser['user_id']);
        $stmtUpdate->execute();
    } else {
        $stmtInsert = $conn->prepare("INSERT INTO user_2fa (user_id, secret, enabled) VALUES (?, ?, 'pending')");
        $stmtInsert->bind_param("is", $currentUser['user_id'], $secret);
        $stmtInsert->execute();
    }

    // Generate QR code URL
    $qrCodeUrl = $totp->getQRCodeImageUrlDirect($secret, $user['email'], 'PPOB Express');

    apiSuccess([
        'secret' => $secret,
        'qr_code_url' => $qrCodeUrl,
        'otpauth_url' => $totp->getQRCodeUrl($secret, $user['email'], 'PPOB Express'),
        'message' => 'Silakan scan QR code dengan aplikasi authenticator Anda'
    ], 'Setup 2FA');
} else {
    // Disable 2FA - requires password confirmation
    $password = $input['password'] ?? '';

    if (empty($password)) {
        apiError('Password diperlukan untuk menonaktifkan 2FA', 'PASSWORD_REQUIRED');
    }

    // Verify password
    $stmtUser = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $currentUser['user_id']);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();
    $user = $resultUser->fetch_assoc();

    if (!password_verify($password, $user['password'])) {
        apiError('Password salah', 'INVALID_PASSWORD');
    }

    // Disable 2FA
    $stmtDisable = $conn->prepare("UPDATE user_2fa SET enabled = 'no', secret = NULL WHERE user_id = ?");
    $stmtDisable->bind_param("i", $currentUser['user_id']);
    $stmtDisable->execute();

    apiSuccess(null, '2FA berhasil dinonaktifkan');
}

$conn->close();
