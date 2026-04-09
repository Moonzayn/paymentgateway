<?php
/**
 * API: Beli Kuota untuk Mobile App
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/digiflazz.php';

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $no_hp = preg_replace('/[^0-9]/', '', $data['no_hp'] ?? '');
    $produk_id = intval($data['produk_id'] ?? 0);
    
    if (empty($no_hp) || strlen($no_hp) < 10 || strlen($no_hp) > 15) {
        echo json_encode(['success' => false, 'message' => 'Nomor HP tidak valid!']);
        exit;
    }
    
    if ($produk_id == 0) {
        echo json_encode(['success' => false, 'message' => 'Pilih paket kuota!']);
        exit;
    }
    
    $conn = koneksi();
    
    $stmt = $conn->prepare("SELECT * FROM produk WHERE id = ?");
    $stmt->bind_param("i", $produk_id);
    $stmt->execute();
    $produk = $stmt->get_result()->fetch_assoc();
    
    if (!$produk) {
        echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan!']);
        exit;
    }
    
    $user_id = intval($data['user_id'] ?? 0);
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID diperlukan!']);
        exit;
    }
    
    $saldo = getSaldo($user_id);
    $harga = $produk['harga_jual'];
    
    if ($saldo < $harga) {
        echo json_encode(['success' => false, 'message' => 'Saldo tidak mencukupi!']);
        exit;
    }
    
    $ref_id = generateDigiflazzRefId('KTA');
    $invoice = generateInvoice();
    $saldo_sebelum = $saldo;
    $saldo_sesudah = $saldo - $harga;
    
    $customerNo = formatPhoneForDigiflazz($no_hp);
    $no_tujuan = formatPhoneForDigiflazz($no_hp);
    
    $stmt = $conn->prepare("INSERT INTO transaksi (user_id, produk_id, no_invoice, ref_id, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan, api_response) VALUES (?, ?, ?, ?, 'kuota', ?, ?, ?, 0, ?, ?, ?, 'pending', 'Menunggu response Digiflazz', NULL)");
    $stmt->bind_param("iisssddddd", $user_id, $produk_id, $invoice, $ref_id, $no_tujuan, $produk['nominal'], $harga, $harga, $saldo_sebelum, $saldo_sesudah);
    $stmt->execute();
    $transaksi_id = $conn->insert_id;
    
    updateSaldo($user_id, $harga, 'kurang');
    
    $df = new DigiflazzAPI();
    $apiResult = $df->buyPulsa($produk['kode_produk'], $customerNo, $ref_id);
    
    $finalStatus = $apiResult['status'];
    $keterangan = $apiResult['message'];
    $sn = $apiResult['sn'] ?? null;
    
    $stmt = $conn->prepare("UPDATE transaksi SET status = ?, keterangan = ?, api_response = ?, sn = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $finalStatus, $keterangan, json_encode($apiResult), $sn, $transaksi_id);
    $stmt->execute();
    
    if ($finalStatus !== 'success') {
        updateSaldo($user_id, $harga, 'tambah');
    }
    
    echo json_encode([
        'success' => ($finalStatus === 'success'),
        'message' => $keterangan,
        'data' => [
            'transaksi_id' => $transaksi_id,
            'no_invoice' => $invoice,
            'ref_id' => $ref_id,
            'status' => $finalStatus,
            'no_tujuan' => $no_tujuan,
            'nominal' => $produk['nominal'],
            'harga' => $harga,
            'sn' => $sn
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
