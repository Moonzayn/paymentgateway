<?php
/**
 * API Get Saldo - Auto refresh saldo tanpa reload page
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config.php';

$user_id = $_SESSION['user_id'];

// Get current saldo from database
$conn = koneksi();
$stmt = $conn->prepare("SELECT saldo FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $saldo = floatval($row['saldo']);
    $saldo_display = 'Rp ' . number_format($saldo, 0, ',', '.');

    echo json_encode([
        'success' => true,
        'saldo' => $saldo,
        'saldo_display' => $saldo_display,
        'last_update' => date('H:i:s')
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}

$conn->close();
