<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/spn_helper.php';

header('Content-Type: application/json');

$reference = $_GET['reference'] ?? '';

if (!$reference) {
    echo json_encode(['success' => false, 'message' => 'No reference']);
    exit;
}

$conn = koneksi();

$stmt = $conn->prepare("SELECT * FROM transaksi_pos WHERE no_invoice = ? AND status = 'pending'");
$stmt->bind_param("s", $reference);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if ($row['status'] == 'success') {
        echo json_encode(['paid' => true, 'status' => 'success']);
        exit;
    }
    
    if ($row['status'] == 'cancelled') {
        echo json_encode(['paid' => false, 'status' => 'cancelled', 'message' => 'Transaksi dibatalkan']);
        exit;
    }
    
    $checkResult = checkQRISStatus($reference);
    
    if ($checkResult['success'] && isset($checkResult['data']['data'])) {
        $data = $checkResult['data']['data'];
        $status = strtolower($data['status'] ?? 'pending');
        
        if (in_array($status, ['paid', 'success', 'settlement'])) {
            $stmt = $conn->prepare("UPDATE transaksi_pos SET status = 'success' WHERE no_invoice = ?");
            $stmt->bind_param("s", $reference);
            $stmt->execute();
            
            echo json_encode(['paid' => true, 'status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['paid' => false, 'status' => $status]);
        }
    } else {
        echo json_encode(['paid' => false, 'status' => 'pending', 'message' => 'Waiting for payment']);
    }
} else {
    echo json_encode(['paid' => false, 'message' => 'Transaction not found']);
}
