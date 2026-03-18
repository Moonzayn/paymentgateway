<?php
/**
 * API v1 - Login Endpoint
 * Method: POST
 * Body: { "username": "...", "password": "..." }
 */

require_once __DIR__ . '/../config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    apiError('Username dan password harus diisi', 'VALIDATION_ERROR');
}

$conn = koneksi();

// Check rate limiting
$identifier = $username . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!checkLoginAttempts($identifier)) {
    apiError('Terlalu banyak percobaan login. Silakan coba lagi dalam 15 menit.', 'RATE_LIMITED');
}

// Find user
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
            // Need 2FA - return partial success with 2FA required
            $_SESSION['2fa_user_id'] = $user['id'];
            $_SESSION['2fa_pending'] = true;
            $_SESSION['2fa_username'] = $username;

            // Generate temp token for 2FA verification
            $tempToken = 'temp_' . bin2hex(random_bytes(16));
            $_SESSION['2fa_temp_token'] = $tempToken;

            apiSuccess([
                'requires_2fa' => true,
                'temp_token' => $tempToken,
                'user_id' => $user['id'],
                'message' => 'Verifikasi 2FA diperlukan'
            ], 'Login berhasil, 2FA diperlukan');
        } else {
            // No 2FA - generate API key and return token
            $tokenData = generateApiToken($user['id'], 'Flutter App', 'flutter', $input['device_id'] ?? null);

            if (!$tokenData) {
                apiError('Gagal membuat token', 'TOKEN_ERROR', 500);
            }

            // Get user's store info
            $stmtStore = $conn->prepare("
                SELECT su.store_id, su.role, s.nama_toko
                FROM store_users su
                JOIN stores s ON su.store_id = s.id
                WHERE su.user_id = ? AND s.status = 'active'
                LIMIT 1
            ");
            $stmtStore->bind_param("i", $user['id']);
            $stmtStore->execute();
            $resultStore = $stmtStore->get_result();
            $store = $resultStore->fetch_assoc();

            $userData = [
                'id' => $user['id'],
                'username' => $user['username'],
                'nama_lengkap' => $user['nama_lengkap'],
                'email' => $user['email'],
                'no_hp' => $user['no_hp'],
                'role' => $user['role'],
                'saldo' => (float)$user['saldo'],
                'saldo_display' => 'Rp ' . number_format($user['saldo'], 0, ',', '.'),
                'store' => $store ? [
                    'id' => $store['store_id'],
                    'nama_toko' => $store['nama_toko'],
                    'role' => $store['role']
                ] : null
            ];

            apiSuccess([
                'token' => $tokenData['api_key'],
                'secret' => $tokenData['secret_key'],
                'expires_at' => $tokenData['expires_at'],
                'user' => $userData,
                '2fa_required' => false
            ], 'Login berhasil');
        }
    } else {
        recordLoginAttempt($identifier);
        apiError('Username atau password salah', 'INVALID_CREDENTIALS');
    }
} else {
    recordLoginAttempt($identifier);
    apiError('Username tidak ditemukan', 'USER_NOT_FOUND');
}

$conn->close();
