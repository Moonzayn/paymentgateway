<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/spn_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['current_store_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$reference = $_POST['reference'] ?? '';
$amount = (int)$_POST['amount'] ?? 0;

if (!$reference || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$callbackUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/payment/api/qris_callback.php';

$result = generateQRIS($reference, $amount, 30, 'PPOB Express', $callbackUrl);

if ($result['success'] && isset($result['data'])) {
    $data = $result['data'];
    
    $conn = koneksi();
    $qrisString = $data['qris_string'] ?? '';
    $expiredAt = $data['expired_at'] ?? date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    $stmt = $conn->prepare("
        UPDATE transaksi_pos 
        SET qris_reference = ?, qris_string = ?, qris_expired_at = ?
        WHERE no_invoice = ?
    ");
    $stmt->bind_param("ssss", $data['reference'], $qrisString, $expiredAt, $reference);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'qris_image' => $data['qris_image'] ?? '',
        'qris_string' => $qrisString,
        'reference' => $data['reference'],
        'expired_at' => $expiredAt
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Failed to generate QRIS',
        'debug' => $result
    ]);
}
