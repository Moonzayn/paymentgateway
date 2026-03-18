<?php
/**
 * API v1 - Get Current User Info
 * Method: GET
 * Header: Authorization: Bearer <api_key>
 */

require_once __DIR__ . '/../config.php';

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

// Require authentication
$currentUser = requireAuth();

$conn = koneksi();

// Get full user data
$stmt = $conn->prepare("
    SELECT id, username, nama_lengkap, email, no_hp, role, saldo, status, force_2fa, is_super_admin, created_at
    FROM users WHERE id = ?
");
$stmt->bind_param("i", $currentUser['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    apiError('User tidak ditemukan', 'USER_NOT_FOUND', 404);
}

// Check 2FA status
$stmt2fa = $conn->prepare("SELECT enabled FROM user_2fa WHERE user_id = ? AND enabled = 'yes'");
$stmt2fa->bind_param("i", $currentUser['user_id']);
$stmt2fa->execute();
$result2fa = $stmt2fa->get_result();
$has2FA = $result2fa->num_rows > 0;

// Get store info
$stmtStore = $conn->prepare("
    SELECT su.store_id, su.role as store_role, s.nama_toko, s.status as store_status
    FROM store_users su
    JOIN stores s ON su.store_id = s.id
    WHERE su.user_id = ?
    ORDER BY su.created_at ASC
    LIMIT 5
");
$stmtStore->bind_param("i", $currentUser['user_id']);
$stmtStore->execute();
$resultStore = $stmtStore->get_result();

$stores = [];
while ($store = $resultStore->fetch_assoc()) {
    $stores[] = [
        'id' => $store['store_id'],
        'nama_toko' => $store['nama_toko'],
        'role' => $store['store_role'],
        'status' => $store['store_status']
    ];
}

$conn->close();

apiSuccess([
    'id' => $user['id'],
    'username' => $user['username'],
    'nama_lengkap' => $user['nama_lengkap'],
    'email' => $user['email'],
    'no_hp' => $user['no_hp'],
    'role' => $user['role'],
    'saldo' => (float)$user['saldo'],
    'saldo_display' => 'Rp ' . number_format($user['saldo'], 0, ',', '.'),
    'status' => $user['status'],
    'is_super_admin' => $user['is_super_admin'] === 'yes',
    '2fa_enabled' => $has2FA,
    '2fa_force' => $user['force_2fa'] === 'yes',
    'stores' => $stores,
    'created_at' => $user['created_at']
], 'Data user');
