<?php
/**
 * API v1 - Profile Endpoint
 * Method: GET (get profile) / PUT (update profile)
 * Header: Authorization: Bearer <api_key>
 */

require_once __DIR__ . '/../config.php';

$currentUser = requireAuth();
$conn = koneksi();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get profile
    $stmt = $conn->prepare("
        SELECT id, username, nama_lengkap, email, no_hp, role, saldo, status, created_at, updated_at
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
        '2fa_enabled' => $has2FA,
        'created_at' => $user['created_at'],
        'updated_at' => $user['updated_at']
    ], 'Data profile');

} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update profile
    $input = json_decode(file_get_contents('php://input'), true);

    $nama_lengkap = trim($input['nama_lengkap'] ?? '');
    $email = trim($input['email'] ?? '');
    $no_hp = trim($input['no_hp'] ?? '');

    // Validation
    $errors = [];

    if (empty($nama_lengkap)) {
        $errors[] = 'Nama lengkap diperlukan';
    }

    if (empty($email)) {
        $errors[] = 'Email diperlukan';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid';
    }

    if (!empty($errors)) {
        apiError(implode(', ', $errors), 'VALIDATION_ERROR');
    }

    // Check if email is already used by another user
    $stmtCheck = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmtCheck->bind_param("si", $email, $currentUser['user_id']);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck->num_rows > 0) {
        apiError('Email sudah digunakan oleh user lain', 'EMAIL_EXISTS');
    }

    // Update profile
    $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, no_hp = ? WHERE id = ?");
    $stmt->bind_param("sssi", $nama_lengkap, $email, $no_hp, $currentUser['user_id']);

    if ($stmt->execute()) {
        // Get updated user data
        $stmtUser = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmtUser->bind_param("i", $currentUser['user_id']);
        $stmtUser->execute();
        $resultUser = $stmtUser->get_result();
        $user = $resultUser->fetch_assoc();

        $conn->close();

        apiSuccess([
            'id' => $user['id'],
            'username' => $user['username'],
            'nama_lengkap' => $user['nama_lengkap'],
            'email' => $user['email'],
            'no_hp' => $user['no_hp'],
            'role' => $user['role'],
            'saldo' => (float)$user['saldo']
        ], 'Profile berhasil diperbarui');
    } else {
        apiError('Gagal memperbarui profile', 'UPDATE_ERROR', 500);
    }

} else {
    apiError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}
