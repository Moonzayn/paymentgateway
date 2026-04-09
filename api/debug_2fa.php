<?php
/**
 * Debug: Check user 2FA status
 */

error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

$conn = koneksi();

// Get admin user
$stmt = $conn->prepare("SELECT id, username, force_2fa FROM users WHERE username = 'admin'");
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode(['error' => 'User admin tidak ditemukan']);
    exit;
}

echo json_encode([
    'user' => $user,
]);

// Check 2FA
$stmt2fa = $conn->prepare("SELECT * FROM user_2fa WHERE user_id = ?");
$stmt2fa->bind_param("i", $user['id']);
$stmt2fa->execute();
$result2fa = $stmt2fa->get_result();
$user2fa = $result2fa->fetch_assoc();

echo json_encode([
    'user' => $user,
    '2fa_record' => $user2fa,
    'has_2fa' => $user2fa ? true : false,
]);

$conn->close();
