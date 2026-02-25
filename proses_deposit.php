<?php
/**
 * Proses Deposit - Handle deposit approval/rejection
 * Called from deposit.php (admin panel)
 */

require_once 'config.php';
cekLogin();

// Check if admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

// Rate limiting
if (!checkRateLimit('admin_deposit_action', 20, 60)) {
    setAlert('error', 'Terlalu banyak permintaan. Silakan tunggu sebentar.');
    header("Location: deposit.php"); 
    exit;
}

// Validate CSRF
$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrf_token)) {
    setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
    header("Location: deposit.php");
    exit;
}

$action = $_POST['action'] ?? '';
$deposit_id = intval($_POST['deposit_id'] ?? 0);

if ($deposit_id == 0) {
    setAlert('error', 'ID deposit tidak valid!');
    header("Location: deposit.php");
    exit;
}

$conn = koneksi();

// Get deposit info
$stmt = $conn->prepare("SELECT * FROM deposit WHERE id = ?");
$stmt->bind_param("i", $deposit_id);
$stmt->execute();
$deposit = $stmt->get_result()->fetch_assoc();

if (!$deposit) {
    setAlert('error', 'Deposit tidak ditemukan!');
    header("Location: deposit.php");
    exit;
}

if ($deposit['status'] != 'pending') {
    setAlert('error', 'Deposit sudah diproses sebelumnya!');
    header("Location: deposit.php");
    exit;
}

$admin_id = $_SESSION['user_id'];

if ($action == 'approve') {
    // Approve deposit
    
    // Get user current balance
    $stmt = $conn->prepare("SELECT saldo, nama_lengkap FROM users WHERE id = ?");
    $stmt->bind_param("i", $deposit['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    $saldo_sebelum = $user['saldo'];
    $saldo_sesudah = $saldo_sebelum + $deposit['nominal'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update deposit status
        $stmt = $conn->prepare("UPDATE deposit SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $admin_id, $deposit_id);
        $stmt->execute();
        
        // Update user saldo
        $stmt = $conn->prepare("UPDATE users SET saldo = ? WHERE id = ?");
        $stmt->bind_param("di", $saldo_sesudah, $deposit['user_id']);
        $stmt->execute();
        
        // Create transaction record
        $invoice = 'DEP' . date('YmdHis') . rand(100, 999);
        $stmt = $conn->prepare("INSERT INTO transaksi (user_id, no_invoice, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan) VALUES (?, ?, 'deposit', ?, ?, ?, 0, ?, ?, ?, 'success', 'Deposit via " . $deposit['metode_bayar'] . "')");
        $stmt->bind_param("issddddd", $deposit['user_id'], $invoice, $deposit['metode_bayar'], $deposit['nominal'], $deposit['nominal'], $deposit['nominal'], $saldo_sebelum, $saldo_sesudah);
        $stmt->execute();
        
        // Log security event
        logSecurityEvent('DEPOSIT_APPROVED', [
            'admin_id' => $admin_id,
            'deposit_id' => $deposit_id,
            'user_id' => $deposit['user_id'],
            'amount' => $deposit['nominal'],
            'method' => $deposit['metode_bayar']
        ]);
        
        $conn->commit();
        
        setAlert('success', 'Deposit berhasil disetujui! Saldo user ditambahkan.');
        
    } catch (Exception $e) {
        $conn->rollback();
        setAlert('error', 'Gagal memproses deposit: ' . $e->getMessage());
    }
    
} elseif ($action == 'reject') {
    // Reject deposit
    
    $conn->begin_transaction();
    
    try {
        // Update deposit status
        $stmt = $conn->prepare("UPDATE deposit SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $admin_id, $deposit_id);
        $stmt->execute();
        
        // Log security event
        logSecurityEvent('DEPOSIT_REJECTED', [
            'admin_id' => $admin_id,
            'deposit_id' => $deposit_id,
            'user_id' => $deposit['user_id'],
            'amount' => $deposit['nominal'],
            'method' => $deposit['metode_bayar']
        ]);
        
        $conn->commit();
        
        setAlert('success', 'Deposit berhasil ditolak.');
        
    } catch (Exception $e) {
        $conn->rollback();
        setAlert('error', 'Gagal memproses penolakan: ' . $e->getMessage());
    }
    
} else {
    setAlert('error', 'Aksi tidak valid!');
}

$conn->close();
header("Location: deposit.php");
exit;
