<?php
/**
 * Secure Setup Wizard
 * Run once during installation to create admin account
 * Delete this file after setup!
 */

// Prevent access if already configured
if (file_exists(__DIR__ . '/.setup_complete')) {
    die('Setup already completed. Please delete this file.');
}

require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    
    // Validasi
    if (empty($username) || empty($password) || empty($nama_lengkap)) {
        $error = 'Semua field wajib diisi!';
    } elseif (strlen($username) < 4) {
        $error = 'Username minimal 4 karakter!';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter!';
    } elseif ($password !== $confirm_password) {
        $error = 'Password tidak cocok!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email tidak valid!';
    } else {
        $conn = koneksi();
        
        // Check if any admin exists
        $check = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        if ($check->num_rows > 0) {
            $error = 'Admin sudah ada. Setup tidak diperlukan.';
        } else {
            // Create admin
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, no_hp, saldo, role, status) VALUES (?, ?, ?, ?, ?, 0, 'admin', 'active')");
            $stmt->bind_param("sssss", $username, $hashedPassword, $nama_lengkap, $email, $no_hp);
            
            if ($stmt->execute()) {
                // Mark setup as complete
                file_put_contents(__DIR__ . '/.setup_complete', date('Y-m-d H:i:s'));
                $success = 'Admin berhasil dibuat! Silakan hapus file setup.php dan login.';
            } else {
                $error = 'Gagal membuat admin: ' . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - PPOB Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .setup-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="setup-card w-full max-w-md p-8">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800">PPOB Express Setup</h1>
            <p class="text-gray-600">Buat akun admin pertama</p>
        </div>
        
        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
        <div class="text-center">
            <a href="login.php" class="text-blue-600 hover:text-blue-800">Login ke Dashboard</a>
        </div>
        <?php else: ?>
        <form method="POST" action="" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                <input type="text" name="username" required minlength="4"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                       placeholder="admin">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                <input type="password" name="password" required minlength="8"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                       placeholder="Minimal 8 karakter">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password *</label>
                <input type="password" name="confirm_password" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                <input type="text" name="nama_lengkap" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                <input type="email" name="email" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">No HP</label>
                <input type="tel" name="no_hp"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition">
                Buat Admin
            </button>
        </form>
        
        <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
            <p class="font-semibold">⚠️ PENTING:</p>
            <ul class="list-disc list-inside mt-2 space-y-1">
                <li>Hapus file setup.php setelah selesai</li>
                <li>Gunakan password yang kuat</li>
                <li>Simpan credentials dengan aman</li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
