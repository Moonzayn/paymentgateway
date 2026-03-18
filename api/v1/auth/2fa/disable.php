<?php
/**
 * API v1 - 2FA Disable Endpoint
 * Method: POST
 * Header: Authorization: Bearer <api_key>
 *
 * Disable 2FA for the user
 * Request: { "password": "...", "code": "123456" }
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
$password = $input['password'] ?? '';
$code = $input['code'] ?? '';

if (empty($password)) {
    apiError('Password diperlukan', 'PASSWORD_REQUIRED');
}

// Verify password first
$stmtUser = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmtUser->bind_param("i", $currentUser['user_id']);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
$user = $resultUser->fetch_assoc();

if (!password_verify($password, $user['password'])) {
    apiError('Password salah', 'INVALID_PASSWORD');
}

// Get 2FA status
$stmt = $conn->prepare("SELECT * FROM user_2fa WHERE user_id = ?");
$stmt->bind_param("i", $currentUser['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$twoFA = $result->fetch_assoc();

if (!$twoFA || $twoFA['enabled'] !== 'yes') {
    apiError('2FA tidak diaktifkan', '2FA_NOT_ENABLED');
}

// If 2FA is enabled, require current code to disable
if (!empty($code)) {
    $totp = new TOTPHelper();
    $isValid = $totp->verifyCode($twoFA['secret'], $code);

    if (!$isValid) {
        apiError('Kode 2FA tidak valid', 'INVALID_CODE');
    }
}

// Disable 2FA
$stmtDisable = $conn->prepare("UPDATE user_2fa SET enabled = 'no', secret = NULL WHERE user_id = ?");
$stmtDisable->bind_param("i", $currentUser['user_id']);
$stmtDisable->execute();

$conn->close();

apiSuccess(null, '2FA berhasil dinonaktifkan');
