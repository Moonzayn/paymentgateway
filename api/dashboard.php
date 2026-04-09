<?php
/**
 * API Get Dashboard Data
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config.php';

try {
    $user_id = $_GET['user_id'] ?? 0;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'user_id required']);
        exit;
    }
    
    $conn = koneksi();
    
    // Get recent transactions (last 10)
    $stmt = $conn->prepare("
        SELECT t.id, t.no_invoice, t.ref_id, t.jenis_transaksi, t.no_tujuan, 
               t.nominal, t.total_bayar, t.status, t.keterangan, t.created_at,
               p.nama_produk, p.provider
        FROM transaksi t 
        LEFT JOIN produk p ON t.produk_id = p.id 
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC 
        LIMIT 10
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = [
            'id' => intval($row['id']),
            'no_tujuan' => $row['no_tujuan'] ?? '',
            'total_bayar' => floatval($row['total_bayar'] ?? $row['harga'] ?? 0),
            'status' => $row['status'] ?? 'pending',
            'jenis_transaksi' => $row['jenis_transaksi'] ?? 'other',
            'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
            'nama_produk' => $row['nama_produk'] ?? $row['produk'] ?? 'Produk',
            'provider' => $row['provider'] ?? '',
            'keterangan' => $row['keterangan'] ?? '',
            'ref_id' => $row['ref_id'] ?? '',
            'no_invoice' => $row['no_invoice'] ?? '',
        ];
    }
    
    // Get user saldo
    $stmtSaldo = $conn->prepare("SELECT saldo FROM users WHERE id = ?");
    $stmtSaldo->bind_param("i", $user_id);
    $stmtSaldo->execute();
    $resultSaldo = $stmtSaldo->get_result();
    $userData = $resultSaldo->fetch_assoc();
    $saldo = floatval($userData['saldo'] ?? 0);
    
    // Get stats
    $today = date('Y-m-d');
    $bulanIni = date('Y-m');
    
    // Today's transactions count
    $stmtCount = $conn->prepare("SELECT COUNT(*) as count, SUM(total_bayar) as total FROM transaksi WHERE user_id = ? AND DATE(created_at) = ? AND status = 'success'");
    $stmtCount->bind_param("is", $user_id, $today);
    $stmtCount->execute();
    $resultCount = $stmtCount->get_result();
    $countData = $resultCount->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'saldo' => $saldo,
        'transactions' => $transactions,
        'stats' => [
            'transaksi_hari_ini' => intval($countData['count'] ?? 0),
            'total_hari_ini' => floatval($countData['total'] ?? 0),
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
