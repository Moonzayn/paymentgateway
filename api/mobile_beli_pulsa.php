<?php
/**
 * API: Beli Pulsa untuk Mobile App - MINIMAL VERSION
 */

// Prevent any output before our JSON
error_reporting(0);
ini_set('display_errors', 0);

// Clean ALL output buffers
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Get input
$rawInput = file_get_contents('php://input');

// Direct JSON parse
$data = json_decode($rawInput, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request data',
        'raw' => substr($rawInput, 0, 100)
    ]);
    exit;
}

// Extract parameters
$user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
$no_hp = isset($data['no_hp']) ? preg_replace('/[^0-9]/', '', $data['no_hp']) : '';
$produk_id = isset($data['produk_id']) ? intval($data['produk_id']) : 0;

// Validation
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}
if (!$no_hp || strlen($no_hp) < 10) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number']);
    exit;
}
if (!$produk_id) {
    echo json_encode(['success' => false, 'message' => 'Product ID required']);
    exit;
}

// Database connection - direct
$conn = new mysqli('localhost', 'root', '', 'db_ppob');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $conn->connect_error]);
    exit;
}
$conn->set_charset('utf8mb4');

// Get product
$stmt = $conn->prepare('SELECT * FROM produk WHERE id = ?');
$stmt->bind_param('i', $produk_id);
$stmt->execute();
$result = $stmt->get_result();
$produk = $result->fetch_assoc();

if (!$produk) {
    echo json_encode(['success' => false, 'message' => 'Product not found, ID: ' . $produk_id]);
    exit;
}

// Get user
$stmt2 = $conn->prepare('SELECT saldo FROM users WHERE id = ?');
$stmt2->bind_param('i', $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
$user = $result2->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$saldo = floatval($user['saldo']);
$harga = floatval($produk['harga_jual']);

if ($saldo < $harga) {
    echo json_encode(['success' => false, 'message' => 'Insufficient balance. Balance: ' . $saldo . ', Price: ' . $harga]);
    exit;
}

// Generate invoice
$invoice = 'INV' . date('YmdHis') . rand(1000, 9999);
$ref_id = 'PLS' . date('YmdHis') . rand(100, 999);
$nominal = intval($produk['nominal']);
$saldo_sebelum = $saldo;
$saldo_sesudah = $saldo - $harga;

// Insert transaction
$stmt3 = $conn->prepare("INSERT INTO transaksi (user_id, produk_id, no_invoice, ref_id, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan) VALUES (?, ?, ?, ?, 'pulsa', ?, ?, ?, 0, ?, ?, ?, 'success', 'Purchase successful')");
$stmt3->bind_param('iisssidddds', $user_id, $produk_id, $invoice, $ref_id, $no_hp, $nominal, $harga, $harga, $saldo_sebelum, $saldo_sesudah);
$stmt3->execute();
$transaksi_id = $conn->insert_id;

// Update balance
$stmt4 = $conn->prepare('UPDATE users SET saldo = saldo - ? WHERE id = ?');
$stmt4->bind_param('di', $harga, $user_id);
$stmt4->execute();

$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Purchase successful!',
    'data' => [
        'transaksi_id' => $transaksi_id,
        'no_invoice' => $invoice,
        'no_tujuan' => $no_hp,
        'nominal' => $nominal,
        'harga' => $harga,
        'saldo_sesudah' => $saldo_sesudah
    ]
]);
