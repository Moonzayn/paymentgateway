<?php
/**
 * Transaction Audit Logging System
 * Track all financial transactions for compliance and security
 */

/**
 * Log transaction activity
 */
function logTransaction($user_id, $type, $amount, $details, $status = 'success') {
    $log_file = __DIR__ . '/logs/transactions.log';
    
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $session_id = session_id();
    
    $log_entry = json_encode([
        'timestamp' => $timestamp,
        'user_id' => $user_id,
        'type' => $type,
        'amount' => $amount,
        'details' => $details,
        'status' => $status,
        'ip' => $ip,
        'session_id' => $session_id,
        'user_agent' => substr($user_agent, 0, 200)
    ]) . "\n";
    
    error_log($log_entry, 3, $log_file);
}

/**
 * Log admin activity
 */
function logAdminActivity($admin_id, $action, $target, $details = []) {
    $log_file = __DIR__ . '/logs/admin_activity.log';
    
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $log_entry = json_encode([
        'timestamp' => $timestamp,
        'admin_id' => $admin_id,
        'action' => $action,
        'target' => $target,
        'details' => $details,
        'ip' => $ip
    ]) . "\n";
    
    error_log($log_entry, 3, $log_file);
}

/**
 * Get transaction statistics
 */
function getTransactionStats($days = 7) {
    $conn = koneksi();
    $since = date('Y-m-d H:i:s', strtotime("-$days days"));
    
    $stats = [
        'total_transactions' => 0,
        'total_amount' => 0,
        'success_count' => 0,
        'failed_count' => 0,
        'by_type' => []
    ];
    
    // Total transactions
    $result = $conn->query("SELECT COUNT(*) as count, SUM(total_bayar) as total, 
                            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                            FROM transaksi WHERE created_at >= '$since'");
    $row = $result->fetch_assoc();
    $stats['total_transactions'] = $row['count'];
    $stats['total_amount'] = $row['total'] ?? 0;
    $stats['success_count'] = $row['success'];
    $stats['failed_count'] = $row['failed'];
    
    // By type
    $result = $conn->query("SELECT jenis_transaksi, COUNT(*) as count, SUM(total_bayar) as total
                            FROM transaksi WHERE created_at >= '$since' AND status = 'success'
                            GROUP BY jenis_transaksi");
    while ($row = $result->fetch_assoc()) {
        $stats['by_type'][$row['jenis_transaksi']] = [
            'count' => $row['count'],
            'total' => $row['total']
        ];
    }
    
    return $stats;
}

/**
 * Check for suspicious transaction patterns
 */
function detectSuspiciousActivity($user_id) {
    $conn = koneksi();
    $alerts = [];
    
    // Check for rapid transactions (more than 10 in 1 hour)
    $one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transaksi WHERE user_id = ? AND created_at >= ?");
    $stmt->bind_param("is", $user_id, $one_hour_ago);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 10) {
        $alerts[] = "Rapid transactions detected: {$result['count']} transactions in 1 hour";
    }
    
    // Check for large amounts (more than 10 million in 24 hours)
    $one_day_ago = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $stmt = $conn->prepare("SELECT SUM(total_bayar) as total FROM transaksi WHERE user_id = ? AND created_at >= ? AND status = 'success'");
    $stmt->bind_param("is", $user_id, $one_day_ago);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['total'] > 10000000) {
        $alerts[] = "Large transaction volume: Rp " . number_format($result['total'], 0, ',', '.');
    }
    
    // Check for failed transactions (more than 5 failures in 1 hour)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transaksi WHERE user_id = ? AND created_at >= ? AND status = 'failed'");
    $stmt->bind_param("is", $user_id, $one_hour_ago);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 5) {
        $alerts[] = "Multiple failed transactions: {$result['count']} failures in 1 hour";
    }
    
    // Log alerts if any
    if (!empty($alerts)) {
        logSecurityEvent('SUSPICIOUS_TRANSACTION', [
            'user_id' => $user_id,
            'alerts' => $alerts
        ], $user_id);
    }
    
    return $alerts;
}

/**
 * Get recent transactions for a user
 */
function getRecentTransactions($user_id, $limit = 10) {
    $conn = koneksi();
    $stmt = $conn->prepare("SELECT * FROM transaksi WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Export transaction report to CSV
 */
function exportTransactionCSV($start_date, $end_date, $user_id = null) {
    $conn = koneksi();
    
    if ($user_id) {
        $stmt = $conn->prepare("SELECT t.*, u.username, u.nama_lengkap 
                               FROM transaksi t 
                               JOIN users u ON t.user_id = u.id 
                               WHERE t.user_id = ? AND DATE(t.created_at) BETWEEN ? AND ?
                               ORDER BY t.created_at DESC");
        $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    } else {
        $stmt = $conn->prepare("SELECT t.*, u.username, u.nama_lengkap 
                               FROM transaksi t 
                               JOIN users u ON t.user_id = u.id 
                               WHERE DATE(t.created_at) BETWEEN ? AND ?
                               ORDER BY t.created_at DESC");
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Generate CSV
    $filename = 'transactions_' . $start_date . '_to_' . $end_date . '.csv';
    $filepath = __DIR__ . '/exports/' . $filename;
    
    if (!is_dir(__DIR__ . '/exports')) {
        mkdir(__DIR__ . '/exports', 0755, true);
    }
    
    $fp = fopen($filepath, 'w');
    
    // Headers
    fputcsv($fp, ['ID', 'Invoice', 'User', 'Nama', 'Jenis', 'Tujuan', 'Nominal', 'Total', 'Status', 'Tanggal']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($fp, [
            $row['id'],
            $row['no_invoice'],
            $row['username'],
            $row['nama_lengkap'],
            $row['jenis_transaksi'],
            $row['no_tujuan'],
            $row['nominal'],
            $row['total_bayar'],
            $row['status'],
            $row['created_at']
        ]);
    }
    
    fclose($fp);
    
    return $filepath;
}
