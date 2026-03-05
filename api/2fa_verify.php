<?php
/**
 * API: Verify 2FA saat login
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/totp_helper.php';

$action = $_GET['action'] ?? 'verify';

$conn = koneksi();

switch ($action) {
    case 'verify':
        $user_id = $_SESSION['2fa_user_id'] ?? null;
        $code = trim($_POST['code'] ?? '');

        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'Session expired. Silakan login ulang.']);
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
            echo json_encode(['success' => false, 'message' => '2FA tidak aktif untuk user ini']);
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
                // Update remaining backup codes
                $newBackupCodes = json_encode($verifyBackup['remaining_codes']);
                $stmt = $conn->prepare("UPDATE user_2fa SET backup_codes = ? WHERE user_id = ?");
                $stmt->bind_param("si", $newBackupCodes, $user_id);
                $stmt->execute();
            }
        }

        // Log attempt
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $conn->prepare("INSERT INTO user_2fa_login_attempts (user_id, ip_address, success, code_used) VALUES (?, ?, ?, ?)");
        $success = $valid ? 'yes' : 'no';
        $stmt->bind_param("isss", $user_id, $ip, $success, $code);
        $stmt->execute();

        if ($valid) {
            // Check max attempts from session
            $attempts = $_SESSION['2fa_attempts'] ?? 0;

            // Get user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            // Regenerate session ID for security
            session_regenerate_id(true);

            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['saldo'] = $user['saldo'];
            $_SESSION['created'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['login_time'] = date('Y-m-d H:i:s');

            // Initialize store session (like in login.php)
            initStoreSession($user['id']);

            // Update last login
            $stmt = $conn->prepare("UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            // Update last 2fa login
            $stmt = $conn->prepare("UPDATE users SET last_2fa_login = NOW() WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            // Clear 2FA session
            unset($_SESSION['2fa_user_id']);
            unset($_SESSION['2fa_pending']);
            unset($_SESSION['2fa_attempts']);
            unset($_SESSION['2fa_required_user_id']);
            unset($_SESSION['2fa_required_username']);

            // Log
            error_log("[" . date('Y-m-d H:i:s') . "] 2FA LOGIN SUCCESS: User ID $user_id from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil',
                'redirect' => 'index.php'
            ]);
        } else {
            // Increment attempts
            $_SESSION['2fa_attempts'] = ($_SESSION['2fa_attempts'] ?? 0) + 1;
            $attempts = $_SESSION['2fa_attempts'];

            // Max 5 attempts
            if ($attempts >= 5) {
                // Block for 15 minutes
                $_SESSION['2fa_blocked_until'] = time() + 900;

                error_log("[" . date('Y-m-d H:i:s') . "] 2FA BLOCKED: User ID $user_id from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

                echo json_encode([
                    'success' => false,
                    'message' => 'Terlalu banyak percobaan. Silakan coba lagi dalam 15 menit.',
                    'blocked' => true
                ]);
            } else {
                $remaining = 5 - $attempts;
                echo json_encode([
                    'success' => false,
                    'message' => "Kode salah! Sisa percobaan: $remaining",
                    'attempts' => $attempts
                ]);
            }
        }
        break;

    case 'check':
        // Check apakah user butuh 2FA
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            echo json_encode(['needs_2fa' => false]);
            exit;
        }

        // Verify password first
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$user = $result->fetch_assoc()) {
            echo json_encode(['needs_2fa' => false, 'message' => 'User tidak ditemukan']);
            exit;
        }

        if (!password_verify($password, $user['password'])) {
            echo json_encode(['needs_2fa' => false, 'message' => 'Password salah']);
            exit;
        }

        // Check if user has 2FA enabled
        $stmt = $conn->prepare("SELECT enabled FROM user_2fa WHERE user_id = ? AND enabled = 'yes'");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        $has2FA = $result->num_rows > 0;

        // Check force 2FA
        $force2FA = $user['force_2fa'] === 'yes';

        if ($has2FA || $force2FA) {
            // Store user_id in session for 2FA verification
            $_SESSION['2fa_user_id'] = $user['id'];
            $_SESSION['2fa_pending'] = true;
            $_SESSION['2fa_attempts'] = 0;

            echo json_encode([
                'needs_2fa' => true,
                'user_id' => $user['id'],
                'message' => '2FA diperlukan'
            ]);
        } else {
            echo json_encode([
                'needs_2fa' => false
            ]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
