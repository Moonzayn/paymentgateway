<?php
/**
 * Digiflazz Webhook Handler
 * Endpoint: POST /api/digiflazz_webhook.php
 *
 * Digiflazz akan kirim callback ke URL ini untuk update status transaksi
 */

require_once '../config.php';
require_once '../aggregator/index.php';

header('Content-Type: application/json');

// Log webhook request
error_log("Digiflazz Webhook Received: " . file_get_contents('php://input'));

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Extract data - Digiflazz wraps in 'data' key
$transaction = $data['data'] ?? $data;

$refId = $transaction['ref_id'] ?? '';
$status = $transaction['status'] ?? '';
$rc = $transaction['rc'] ?? '';
$sn = $transaction['sn'] ?? '';
$message = $transaction['message'] ?? '';

if (empty($refId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing ref_id']);
    exit;
}

$conn = koneksi();

// Find transaction by ref_id
$stmt = $conn->prepare("SELECT * FROM transaksi WHERE ref_id = ? AND status = 'pending' LIMIT 1");
$stmt->bind_param("s", $refId);
$stmt->execute();
$result = $stmt->get_result();
$transaksi = $result->fetch_assoc();

if (!$transaksi) {
    // Transaction not found or already processed
    echo json_encode(['success' => true, 'message' => 'Transaction not found or already processed']);
    exit;
}

// Update transaction based on status
if ($status === 'Sukses') {
    // Success - deduct saldo if not already done
    $stmt = $conn->prepare("UPDATE transaksi SET status = 'success', sn = ?, keterangan = ?, updated_at = NOW() WHERE id = ?");
    $keterangan = "Sukses. SN: {$sn}";
    $stmt->bind_param("ssi", $sn, $keterangan, $transaksi['id']);
    $stmt->execute();

    // Deduct saldo if not already
    if ($transaksi['saldo_sesudah'] == $transaksi['saldo_sebelum']) {
        updateSaldo($transaksi['user_id'], $transaksi['total_bayar'], 'kurang');
    }

    // Log success
    error_log("Digiflazz Webhook: Transaction {$refId} SUCCESS. SN: {$sn}");

} elseif ($status === 'Gagal') {
    // Failed - refund if saldo was deducted
    $stmt = $conn->prepare("UPDATE transaksi SET status = 'failed', keterangan = ?, updated_at = NOW() WHERE id = ?");
    $keterangan = "Gagal: {$message} (RC: {$rc})";
    $stmt->bind_param("si", $keterangan, $transaksi['id']);
    $stmt->execute();

    // Refund saldo if it was deducted
    if ($transaksi['saldo_sesudah'] != $transaksi['saldo_sebelum']) {
        updateSaldo($transaksi['user_id'], $transaksi['total_bayar'], 'tambah');
    }

    // Log failed
    error_log("Digiflazz Webhook: Transaction {$refId} FAILED. RC: {$rc}");

} else {
    // Still pending - just log
    error_log("Digiflazz Webhook: Transaction {$refId} still PENDING");
}

// Log webhook for audit
$stmt = $conn->prepare("INSERT INTO webhook_logs (provider, ref_id, status, request_data, created_at) VALUES ('digiflazz', ?, ?, ?, NOW())");
$stmt->bind_param("sss", $refId, $status, $input);
$stmt->execute();

echo json_encode(['success' => true, 'message' => 'Webhook processed']);
