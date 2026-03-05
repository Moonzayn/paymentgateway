<?php
/**
 * API: Setup 2FA
 * Generate secret, QR code, dan verify kode pertama
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/totp_helper.php';

header('Content-Type: application/json');

// Check if user is logged in OR in 2FA required setup flow
$isRequiredSetup = isset($_SESSION['2fa_required_user_id']);
$isLoggedIn = isset($_SESSION['user_id']);

if (!$isRequiredSetup && !$isLoggedIn) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get user_id from either source
$user_id = $_SESSION['user_id'] ?? $_SESSION['2fa_required_user_id'] ?? null;
$action = $_GET['action'] ?? 'init';

$conn = koneksi();

switch ($action) {
    case 'init':
        // Check jika 2FA sudah enabled
        $stmt = $conn->prepare("SELECT enabled FROM user_2fa WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ($row['enabled'] === 'yes') {
                echo json_encode(['success' => false, 'message' => '2FA sudah diaktifkan', 'already_enabled' => true]);
                exit;
            }
        }

        // Generate secret baru
        $secret = generateTOTPSecret();
        $username = $_SESSION['username'] ?? $_SESSION['2fa_required_username'] ?? 'user';
        $email = $username . '@ppobexpress';
        $qrUrl = getTOTPQRUrl($secret, $email);

        // Get alternate QR URL
        $totp = new TOTPHelper();
        $qrUrlAlt = $totp->getQRCodeImageUrlDirect($secret, $email);
        $otpauthUrl = getTOTPAuthUrl($secret, $email);

        // Simpan secret sementara di session (belum aktif)
        $_SESSION['2fa_pending_secret'] = $secret;
        $_SESSION['2fa_pending_expire'] = time() + 300; // 5 menit

        echo json_encode([
            'success' => true,
            'secret' => $secret,
            'qr_url' => $qrUrl,
            'qr_url_alt' => $qrUrlAlt,
            'otpauth' => $otpauthUrl,
            'email' => $email,
            'expire_in' => 300
        ]);
        break;

    case 'verify':
        $code = trim($_POST['code'] ?? '');

        if (empty($code) || !ctype_digit($code) || strlen($code) !== 6) {
            echo json_encode(['success' => false, 'message' => 'Kode harus 6 digit']);
            exit;
        }

        // Check session
        $secret = $_SESSION['2fa_pending_secret'] ?? '';
        $expire = $_SESSION['2fa_pending_expire'] ?? 0;

        if (empty($secret) || time() > $expire) {
            echo json_encode(['success' => false, 'message' => 'Session expired. Silakan ulangi dari awal.']);
            exit;
        }

        // Verify kode
        if (!verifyTOTPCode($secret, $code)) {
            // Log failed attempt
            error_log("2FA Setup Failed - User: $user_id, Code: $code");

            echo json_encode(['success' => false, 'message' => 'Kode tidak valid. Pastikan waktu di hp Anda benar.']);
            exit;
        }

        // Kode valid - simpan ke database
        $backupCodes = generateBackupCodes(8);
        $backupCodesJson = json_encode($backupCodes);

        // Check jika sudah ada record
        $stmt = $conn->prepare("SELECT id FROM user_2fa WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Update
            $stmt = $conn->prepare("UPDATE user_2fa SET secret_key = ?, backup_codes = ?, enabled = 'yes', enabled_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("ssi", $secret, $backupCodesJson, $user_id);
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO user_2fa (user_id, secret_key, backup_codes, enabled, enabled_at) VALUES (?, ?, ?, 'yes', NOW())");
            $stmt->bind_param("iss", $user_id, $secret, $backupCodesJson);
        }

        if ($stmt->execute()) {
            // Check if user was in required flow (force_2fa should be yes)
            // Only set force_2fa = yes if user came from required flow
            $isRequired = isset($_SESSION['2fa_required_user_id']);
            if ($isRequired) {
                $stmt = $conn->prepare("UPDATE users SET force_2fa = 'yes' WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
            }

            // Clear session
            unset($_SESSION['2fa_pending_secret']);
            unset($_SESSION['2fa_pending_expire']);

            // Check if this is required (from login)
            $isRequired = isset($_SESSION['2fa_required_user_id']);

            if ($isRequired) {
                // Langsung login tanpa perlu ke login.php lagi
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();

                // Regenerate session ID for security
                session_regenerate_id(true);

                // Set session user
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
                $stmt = $conn->prepare("UPDATE users SET last_login = NOW(), login_count = login_count + 1, last_2fa_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();

                // Clear required session
                unset($_SESSION['2fa_required_user_id']);
                unset($_SESSION['2fa_required_username']);

                echo json_encode([
                    'success' => true,
                    'message' => '2FA berhasil diaktifkan!',
                    'backup_codes' => $backupCodes,
                    'redirect' => 'index.php'
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => '2FA berhasil diaktifkan!',
                    'backup_codes' => $backupCodes
                ]);
            }
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan. Silakan coba lagi.']);
            exit;
        }
        break;

    case 'disable':
        $code = trim($_POST['code'] ?? '');

        if (empty($code)) {
            echo json_encode(['success' => false, 'message' => 'Kode diperlukan untuk menonaktifkan 2FA']);
            exit;
        }

        // Get secret
        $stmt = $conn->prepare("SELECT secret_key FROM user_2fa WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$row = $result->fetch_assoc()) {
            echo json_encode(['success' => false, 'message' => '2FA tidak aktif']);
            exit;
        }

        // Verify kode
        if (!verifyTOTPCode($row['secret_key'], $code)) {
            // Check backup code
            $stmt = $conn->prepare("SELECT backup_codes FROM user_2fa WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            $verifyBackup = verifyBackupCode($row['backup_codes'], $code);
            if (!$verifyBackup['valid']) {
                echo json_encode(['success' => false, 'message' => 'Kode tidak valid']);
                exit;
            }

            // Update remaining backup codes
            $newBackupCodes = json_encode($verifyBackup['remaining_codes']);
            $stmt = $conn->prepare("UPDATE user_2fa SET backup_codes = ? WHERE user_id = ?");
            $stmt->bind_param("si", $newBackupCodes, $user_id);
            $stmt->execute();
        }

        // Disable 2FA - delete the record instead of setting NULL
        $stmt = $conn->prepare("DELETE FROM user_2fa WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        // Update user
        $stmt = $conn->prepare("UPDATE users SET force_2fa = 'no' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => '2FA berhasil dinonaktifkan']);
        break;

    case 'status':
        // Get 2FA status
        $stmt = $conn->prepare("SELECT enabled, enabled_at FROM user_2fa WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                'success' => true,
                'enabled' => $row['enabled'] === 'yes',
                'enabled_at' => $row['enabled_at']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'enabled' => false
            ]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
