<?php
session_start();

require_once 'config.php';

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
// Proses Login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Sesi tidak valid. Silakan refresh halaman.';
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
                // Verifikasi password yang benar
                if (password_verify($password, $user['password'])) {
                    // Clear login attempts on success
                    clearLoginAttempts($identifier);
                    
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['saldo'] = $user['saldo'];
                    $_SESSION['created'] = time();
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    // Log successful login
                    error_log("[" . date('Y-m-d H:i:s') . "] LOGIN SUCCESS: User {$username} from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                    
                    header("Location: index.php");
                    exit;
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
        /* Custom CSS untuk tema login */
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
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        
        // Auto focus on username field
        document.addEventListener('DOMContentLoaded', function() {
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