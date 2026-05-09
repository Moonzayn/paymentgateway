<?php
/**
 * Quick fix for transaction status
 * Usage: api_fix_status.php?id=37&status=success&token=xxx
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config.php';

$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
$status = $_GET['status'] ?? $_POST['status'] ?? '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$keterangan = $_GET['keterangan'] ?? $_POST['keterangan'] ?? '';

if (!$id || !$status) {
    echo json_encode(['success' => false, 'message' => 'ID dan status diperlukan']);
    exit;
}

$conn = koneksi();

// Get current transaction
$stmt = $conn->prepare("SELECT * FROM transaksi WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();

if (!$trx) {
    echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan']);
    exit;
}

// Update status
$stmt = $conn->prepare("UPDATE transaksi SET status = ?, keterangan = ?, server_id = ? WHERE id = ?");
$stmt->bind_param("sssi", $status, $keterangan, $token, $id);
$stmt->execute();

echo json_encode([
    'success' => true,
    'message' => 'Transaksi updated',
    'data' => [
        'id' => $id,
        'status' => $status,
        'keterangan' => $keterangan,
        'token' => $token
    ]
]);
