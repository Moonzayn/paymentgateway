<?php
session_start();

require_once 'config.php';

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

// Check if 2FA was just set up
if (isset($_GET['2fa_setup']) && $_GET['2fa_setup'] == 1) {
    $success = '2FA berhasil diaktifkan! Silakan masukkan kode untuk login.';
}

// Proses Login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token - simplified for now
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token)) {
        $error = 'Token tidak ada. Silakan refresh halaman.';
    } elseif (!isset($_SESSION['csrf_token'])) {
        $error = 'Sesi expired. Silakan refresh halaman.';
    } elseif ($csrf_token !== $_SESSION['csrf_token']) {
        $error = 'Token tidak cocok. Silakan refresh halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Rate limiting check
        $identifier = $username . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!checkLoginAttempts($identifier)) {
            $error = 'Terlalu banyak percobaan login. Silakan coba lagi dalam 15 menit.';
        } elseif (empty($username) || empty($password)) {
            $error = 'Username dan password harus diisi!';
        } elseif (strlen($username) > 50 || strlen($password) > 100) {
            $error = 'Input terlalu panjang!';
        } else {
            $conn = koneksi();
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                // Verifikasi password
                if (password_verify($password, $user['password'])) {
                    // Clear login attempts
                    clearLoginAttempts($identifier);

                    // Check if user already has 2FA enabled
                    $stmt2fa = $conn->prepare("SELECT enabled FROM user_2fa WHERE user_id = ? AND enabled = 'yes'");
                    $stmt2fa->bind_param("i", $user['id']);
                    $stmt2fa->execute();
                    $result2fa = $stmt2fa->get_result();
                    $has2FA = $result2fa->num_rows > 0;

                    // Check force 2FA
                    $force2FA = $user['force_2fa'] === 'yes';

                    // JIKA BELUM PUNYA 2FA DAN FORCE_2FA = yes, REDIRECT KE SETUP 2FA
                    if (!$has2FA && $force2FA) {
                        // Simpan user_id untuk setup 2FA
                        $_SESSION['2fa_required_user_id'] = $user['id'];
                        $_SESSION['2fa_required_username'] = $username;
                        header("Location: setup_2fa.php?required=1");
                        exit;
                    }

                    // JIKA PUNYA 2FA ATAU FORCE_2FA, TUNJUK FORM 2FA
                    if ($has2FA || $force2FA) {
                        // Need 2FA - store in session
                        $_SESSION['2fa_user_id'] = $user['id'];
                        $_SESSION['2fa_pending'] = true;
                        $_SESSION['2fa_username'] = $username;

                        // Show 2FA form instead of redirecting
                        $show2FA = true;
                        $user_name = $user['nama_lengkap'];
                    } else {
                        // No 2FA - proceed with login
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['saldo'] = $user['saldo'];
                        $_SESSION['created'] = time();
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        initStoreSession($user['id']);

                        // Log
                        error_log("[" . date('Y-m-d H:i:s') . "] LOGIN SUCCESS: User {$username} from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

                        header("Location: index.php");
                        exit;
                    }
                } else {
                    recordLoginAttempt($identifier);
                    $error = 'Password salah!';
                    error_log("[" . date('Y-m-d H:i:s') . "] LOGIN FAILED: Wrong password for {$username} from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                }
            } else {
                recordLoginAttempt($identifier);
                $error = 'Username tidak ditemukan!';
                error_log("[" . date('Y-m-d H:i:s') . "] LOGIN FAILED: User {$username} not found from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            }
            $conn->close();
        }
    }
}

// Check if 2FA verification
$show2FA = $show2FA ?? false;
$user_name = $user_name ?? '';
$twofa_error = $_SESSION['2fa_error'] ?? '';
unset($_SESSION['2fa_error']);

// Jika user baru setup 2FA dan kembali ke login
if (isset($_GET['2fa_setup']) && $_GET['2fa_setup'] == 1 && !$show2FA) {
    // User harus login dulu passwordnya, lalu masukkan 2FA
    // Biarkan user masukkan username/password dulu
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PPOB Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #3b82f6;
            --light-blue: #eff6ff;
        }

        body {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
        }

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            animation: fadeIn 0.5s ease;
            border: 1px solid #e5e7eb;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .btn-primary {
            background-color: var(--primary-blue);
            color: white;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .input-field {
            transition: all 0.2s ease;
        }

        .input-field:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .demo-credentials {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .logo-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-blue) 0%, #1d4ed8 100%);
        }

        .error-alert {
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .password-toggle {
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--primary-blue);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1f2937;
        }

        .login-subtitle {
            color: #6b7280;
            margin-top: 0.5rem;
        }

        .gradient-text {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #1d4ed8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .twofa-input {
            font-size: 2rem;
            letter-spacing: 0.75rem;
            text-align: center;
        }

        .timer-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue) 0%, #1d4ed8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.25rem;
        }
    </style>
</head>
<body class="font-sans">
    <div class="login-container p-4">
        <div class="w-full max-w-md">
            <!-- Header dengan Logo -->
            <div class="login-header">
                <div class="logo-circle rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <i class="fas fa-wallet text-white text-2xl"></i>
                </div>
                <h1 class="login-title">PPOB<span class="gradient-text">Express</span></h1>
                <p class="login-subtitle">Payment Point Online Banking</p>
            </div>

            <!-- Login Card -->
            <div class="login-card bg-white rounded-xl shadow-lg p-8">
                <?php if ($show2FA): ?>
                <!-- 2FA Form -->
                <div id="twofaSection">
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #dcfce7 0%, #86efac 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <i class="fas fa-shield-alt text-green-600 text-2xl"></i>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-800">Verifikasi Two-Factor</h2>
                        <p class="text-sm text-gray-500 mt-1">Masukkan kode dari Google Authenticator</p>
                    </div>

                    <?php if ($twofa_error): ?>
                    <div class="error-alert bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-start gap-3">
                        <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
                        <span><?= htmlspecialchars($twofa_error) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-start gap-3">
                        <i class="fas fa-check-circle text-green-500 mt-0.5"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                    <?php endif; ?>

                    <form id="twofaForm" class="space-y-4">
                        <div>
                            <input type="text"
                                   id="twofaCode"
                                   name="twofa_code"
                                   class="twofa-input w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                   placeholder="000000"
                                   maxlength="6"
                                   required
                                   autofocus>
                        </div>

                        <div style="display: flex; justify-content: center; gap: 1rem; margin: 1.5rem 0;">
                            <div class="timer-circle" id="timerCircle">
                                <span id="timerCount">30</span>
                            </div>
                        </div>

                        <button type="submit"
                                id="twofaSubmit"
                                class="btn-primary w-full py-3 px-4 rounded-lg font-semibold flex items-center justify-center gap-2">
                            <i class="fas fa-check"></i>
                            Verifikasi
                        </button>

                        <div style="text-align: center;">
                            <a href="login.php" class="text-sm text-gray-500 hover:text-blue-600">
                                <i class="fas fa-arrow-left"></i> Kembali ke login
                            </a>
                        </div>
                    </form>
                </div>

                <?php else: ?>
                <!-- Normal Login Form -->
                <h2 class="text-xl font-semibold text-gray-800 text-center mb-6">Masuk ke Akun Anda</h2>

                <?php if ($error): ?>
                <div class="error-alert bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-start gap-3">
                    <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
                    <div>
                        <span class="font-medium">Error:</span>
                        <span class="block"><?= $error ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user mr-2 text-gray-400"></i>
                            Username
                        </label>
                        <input type="text"
                               name="username"
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none"
                               placeholder="Masukkan username"
                               required
                               maxlength="50"
                               autofocus>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-2 text-gray-400"></i>
                            Password
                        </label>
                        <div class="relative">
                            <input type="password"
                                   name="password"
                                   id="password"
                                   class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none pr-12"
                                   placeholder="Masukkan password"
                                   required
                                   maxlength="100"
                                   autocomplete="current-password">
                            <button type="button"
                                    onclick="togglePassword()"
                                    class="password-toggle absolute right-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                                class="btn-primary w-full py-3 px-4 rounded-lg font-semibold flex items-center justify-center gap-2">
                            <i class="fas fa-sign-in-alt"></i>
                            Masuk ke Dashboard
                        </button>
                    </div>
                </form>

                <!-- Demo Credentials -->
                <div class="demo-credentials mt-6 p-4 rounded-lg border border-gray-200">
                    <p class="text-sm font-medium text-gray-700 mb-2 text-center">Akun Demo (Gunakan password: <code class="bg-gray-100 px-2 py-1 rounded">password</code>)</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                        <div class="bg-white p-3 rounded-lg border border-gray-100">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user-shield text-blue-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">Admin</p>
                                    <p class="text-xs text-gray-500">Full Access</p>
                                </div>
                            </div>
                            <p class="text-xs text-gray-600 mt-2">Username: <span class="font-mono font-semibold">admin</span></p>
                        </div>

                        <div class="bg-white p-3 rounded-lg border border-gray-100">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-green-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">Member</p>
                                    <p class="text-xs text-gray-500">Standard Access</p>
                                </div>
                            </div>
                            <p class="text-xs text-gray-600 mt-2">Username: <span class="font-mono font-semibold">member1</span></p>
                        </div>
                    </div>
                </div>

                <!-- Register Link -->
                <div class="mt-6 pt-6 border-t border-gray-200 text-center">
                    <p class="text-sm text-gray-600">
                        Belum punya akun?
                        <a href="register.php" class="font-semibold text-blue-600 hover:text-blue-800 transition">
                            Daftar akun baru
                        </a>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Footer Info -->
                <div class="mt-4 text-center">
                    <p class="text-xs text-gray-500">
                        © <?= date('Y') ?> PPOB Express. Sistem pembayaran digital terintegrasi.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');

            if (password.type === 'password') {
                password.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
                eyeIcon.classList.add('text-blue-600');
            } else {
                password.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash', 'text-blue-600');
                eyeIcon.classList.add('fa-eye');
            }
        }

        // 2FA Timer
        let timerInterval = null;
        let timeLeft = 30;

        function startTimer() {
            timeLeft = 30;
            if (timerInterval) clearInterval(timerInterval);

            timerInterval = setInterval(() => {
                timeLeft--;
                const timerEl = document.getElementById('timerCount');
                const circleEl = document.getElementById('timerCircle');
                if (timerEl) timerEl.textContent = timeLeft;

                if (timeLeft <= 0) {
                    timeLeft = 30;
                }
            }, 1000);
        }

        // 2FA Form Submit
        document.addEventListener('DOMContentLoaded', function() {
            const twofaForm = document.getElementById('twofaForm');
            if (twofaForm) {
                startTimer();

                twofaForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const code = document.getElementById('twofaCode').value.trim();
                    const submitBtn = document.getElementById('twofaSubmit');

                    if (!code || code.length !== 6) {
                        alert('Masukkan kode 6 digit');
                        return;
                    }

                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memverifikasi...';

                    fetch('/payment/api/2fa_verify.php?action=verify', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'code=' + encodeURIComponent(code)
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = data.redirect || 'index.php';
                        } else {
                            if (data.blocked) {
                                alert(data.message);
                                window.location.href = 'login.php';
                            } else {
                                alert(data.message);
                                document.getElementById('twofaCode').value = '';
                                document.getElementById('twofaCode').focus();
                            }
                        }
                    })
                    .catch(err => {
                        alert('Terjadi kesalahan. Silakan coba lagi.');
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-check"></i> Verifikasi';
                    });
                });

                // Auto format input
                document.getElementById('twofaCode').addEventListener('input', function(e) {
                    this.value = this.value.replace(/\D/g, '').substring(0, 6);
                });
            }

            // Auto focus on username field
            const usernameField = document.querySelector('input[name="username"]');
            if (usernameField) {
                usernameField.focus();
            }
        });

        // Enter key to submit form
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.type !== 'textarea') {
                const form = document.querySelector('form');
                if (form) {
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.click();
                    }
                }
            }
        });
    </script>
</body>
</html>
