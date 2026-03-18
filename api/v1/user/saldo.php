<?php
/**
 * API v1 - Get Saldo Endpoint
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

// Get user saldo
$stmt = $conn->prepare("SELECT saldo, updated_at FROM users WHERE id = ?");
$stmt->bind_param("i", $currentUser['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    apiError('User tidak ditemukan', 'USER_NOT_FOUND', 404);
}

$conn->close();

apiSuccess([
    'saldo' => (float)$user['saldo'],
    'saldo_display' => 'Rp ' . number_format($user['saldo'], 0, ',', '.'),
    'last_update' => $user['updated_at']
], 'Saldo user');
