<?php
/**
 * API: Cek Status Token Listrik untuk Mobile App
 * Digiflazz: topup ulang dengan ref_id sama untuk cek status
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/digiflazz.php';

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $ref_id = trim($data['ref_id'] ?? '');
    $transaksi_id = intval($data['transaksi_id'] ?? 0);
    $user_id = intval($data['user_id'] ?? 0);
    
    if (empty($ref_id) && $transaksi_id == 0) {
        echo json_encode(['success' => false, 'message' => 'ref_id atau transaksi_id diperlukan!']);
        exit;
    }
    
    $conn = koneksi();
    
    // Get transaction from DB
    if ($transaksi_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM transaksi WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $transaksi_id, $user_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM transaksi WHERE ref_id = ? AND user_id = ? AND jenis_transaksi = 'listrik'");
        $stmt->bind_param("si", $ref_id, $user_id);
    }
    $stmt->execute();
    $transaksi = $stmt->get_result()->fetch_assoc();
    
    if (!$transaksi) {
        echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan!']);
        exit;
    }
    
    // If already success, return directly
    if ($transaksi['status'] === 'success') {
        echo json_encode([
            'success' => true,
            'message' => 'Transaksi sudah berhasil sebelumnya',
            'data' => [
                'transaksi_id' => $transaksi['id'],
                'no_invoice' => $transaksi['no_invoice'],
                'ref_id' => $transaksi['ref_id'],
                'status' => $transaksi['status'],
                'no_meter' => $transaksi['no_tujuan'],
                'nominal' => $transaksi['nominal'],
                'harga' => $transaksi['harga'],
                'keterangan' => $transaksi['keterangan']
            ]
        ]);
        exit;
    }
    
    // If not success, check with Digiflazz
    $ref_id = $transaksi['ref_id'];
    $no_meter = $transaksi['no_tujuan'];
    $kode_produk = $transaksi['kode_produk'];
    
    error_log("Cek status - ref_id: $ref_id, kode_produk: $kode_produk, no_meter: $no_meter");
    
    $df = new DigiflazzAPI();
    $apiResult = $df->buyTokenListrik($kode_produk, $no_meter, $ref_id);
    
    error_log("API Result: " . json_encode($apiResult));
    
    $rawStatus = $apiResult['status'] ?? 'failed';
    $keterangan = $apiResult['message'] ?? 'Cek status selesai';
    $sn = $apiResult['sn'] ?? null;
    
    // Map status
    $statusMap = [
        'success' => 'success',
        'Sukses' => 'success', 
        'sukses' => 'success',
        'pending' => 'pending',
        'Pending' => 'pending',
        'failed' => 'failed',
        'Gagal' => 'failed',
        'gagal' => 'failed'
    ];
    $finalStatus = $statusMap[strtolower($rawStatus)] ?? 'failed';
    
    // Update transaction
    $stmt = $conn->prepare("UPDATE transaksi SET status = ?, keterangan = ?, api_response = ? WHERE id = ?");
    $stmt->bind_param("sssi", $finalStatus, $keterangan, json_encode($apiResult), $transaksi['id']);
    $stmt->execute();
    
    // Refund if now success (was failed/pending before)
    if ($finalStatus === 'success' && $transaksi['status'] !== 'success') {
        // Saldo already deducted, now success - keep it as is
    } elseif ($finalStatus !== 'success' && $transaksi['status'] === 'pending') {
        // Was pending, now failed - refund
        updateSaldo($user_id, $transaksi['harga'], 'tambah');
    }
    
    echo json_encode([
        'success' => true,
        'message' => $keterangan,
        'data' => [
            'transaksi_id' => $transaksi['id'],
            'no_invoice' => $transaksi['no_invoice'],
            'ref_id' => $ref_id,
            'status' => $finalStatus,
            'no_meter' => $no_meter,
            'nominal' => $transaksi['nominal'],
            'harga' => $transaksi['harga'],
            'keterangan' => $keterangan,
            'token' => $sn
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}