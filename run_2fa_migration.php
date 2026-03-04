<?php
/**
 * Run SQL Migration - Buka di browser: run_2fa_migration.php
 */

require_once 'config.php';

$conn = koneksi();
$message = '';
$error = '';

// Jalankan migration
if (isset($_GET['run'])) {
    try {
        // Buat tabel user_2fa
        $conn->query("CREATE TABLE IF NOT EXISTS user_2fa (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            secret_key VARCHAR(64) NOT NULL,
            backup_codes TEXT,
            enabled ENUM('yes', 'no') DEFAULT 'no',
            enabled_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_enabled (enabled)
        ) ENGINE=InnoDB");

        // Buat tabel user_2fa_login_attempts
        $conn->query("CREATE TABLE IF NOT EXISTS user_2fa_login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            ip_address VARCHAR(45),
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success ENUM('yes', 'no') DEFAULT 'no',
            code_used VARCHAR(10),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_time (user_id, attempt_time)
        ) ENGINE=InnoDB");

        // Tambah kolom force_2fa
        $conn->query("ALTER TABLE users ADD COLUMN force_2fa ENUM('yes', 'no') DEFAULT 'no'");

        // Tambah kolom last_2fa_login
        $conn->query("ALTER TABLE users ADD COLUMN last_2fa_login TIMESTAMP NULL");

        $message = "Migration berhasil! Tabel user_2fa dan kolom telah dibuat.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check status
$tablesExist = false;
$columnsExist = false;

$result = $conn->query("SHOW TABLES LIKE 'user_2fa'");
$tablesExist = $result->num_rows > 0;

$result = $conn->query("SHOW COLUMNS FROM users LIKE 'force_2fa'");
$columnsExist = $result->num_rows > 0;

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>2FA Migration</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full">
        <h1 class="text-2xl font-bold mb-6 text-center">2FA Database Migration</h1>

        <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= $message ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= $error ?>
        </div>
        <?php endif; ?>

        <div class="space-y-4">
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <span>Tabel user_2fa</span>
                <?php if ($tablesExist): ?>
                    <span class="text-green-600">✓ Ada</span>
                <?php else: ?>
                    <span class="text-red-600">✗ Belum</span>
                <?php endif; ?>
            </div>

            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <span>Kolom force_2fa</span>
                <?php if ($columnsExist): ?>
                    <span class="text-green-600">✓ Ada</span>
                <?php else: ?>
                    <span class="text-red-600">✗ Belum</span>
                <?php endif; ?>
            </div>

            <?php if (!$tablesExist || !$columnsExist): ?>
            <a href="?run=1" class="block w-full bg-blue-600 text-white text-center py-3 rounded-lg font-semibold hover:bg-blue-700">
                Jalankan Migration
            </a>
            <?php else: ?>
            <div class="text-center text-green-600 font-semibold">
                ✓ Semua sudah ready!
            </div>
            <?php endif; ?>

            <a href="index.php" class="block text-center text-gray-500 mt-4">
                ← Kembali ke Dashboard
            </a>
        </div>
    </div>
</body>
</html>
