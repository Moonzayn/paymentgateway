<?php
/**
 * Force 2FA untuk semua user - Buka di browser: force_2fa_all.php
 */

require_once 'config.php';

$conn = koneksi();

// Update semua user untuk force_2fa = yes
$conn->query("UPDATE users SET force_2fa = 'yes' WHERE status = 'active'");

$affected = $conn->affected_rows;

echo "Berhasil! $affected user di-set wajib 2FA.";

$conn->close();
