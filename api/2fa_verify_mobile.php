<?php
/**
 * API: Verify 2FA untuk Mobile App - tidak bergantung session
 */

error_reporting(0);
ini_set('display_errors', 0);

ob_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/totp_helper.php';

try {

$conn = koneksi();

// Get input
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$user_id = intval($data['user_id'] ?? 0);
$code = trim($data['code'] ?? '');

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID diperlukan']);
    exit;
}

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Kode diperlukan']);
    exit;
}

// Get user 2FA data
$stmt = $conn->prepare("SELECT * FROM user_2fa WHERE user_id = ? AND enabled = 'yes'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$row = $result->fetch_assoc()) {
    // Debug: check if there's any record
    $stmtDebug = $conn->prepare("SELECT * FROM user_2fa WHERE user_id = ?");
    $stmtDebug->bind_param("i", $user_id);
    $stmtDebug->execute();
    $resultDebug = $stmtDebug->get_result();
    $rowDebug = $resultDebug->fetch_assoc();
    
    echo json_encode([
        'success' => false, 
        'message' => '2FA tidak aktif untuk user ini',
        'debug' => [
            'user_id' => $user_id,
            'any_record' => $rowDebug,
        ]
    ]);
    exit;
}

$secret = $row['secret_key'];
$backupCodes = $row['backup_codes'];

// Verify TOTP code
$valid = verifyTOTPCode($secret, $code);

// Check backup code if TOTP failed
if (!$valid) {
    $verifyBackup = verifyBackupCode($backupCodes, $code);
    if ($verifyBackup['valid']) {
        $valid = true;
    }
}

if ($valid) {
    // Get user data
    $stmtUser = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $user_id);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();
    $user = $resultUser->fetch_assoc();

    echo json_encode([
        'success' => true,
        'message' => 'Verifikasi berhasil',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'nama_lengkap' => $user['nama_lengkap'],
            'role' => $user['role'],
            'saldo' => (float)$user['saldo']
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Kode salah atau expired'
    ]);
}

} catch (Exception $e) {
    error_log("2fa_verify_mobile error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan']);
}

$conn->close();
