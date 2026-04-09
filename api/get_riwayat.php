<?php
/**
 * API Get Riwayat - Get transaction history for a user
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config.php';

$user_id = $_GET['user_id'] ?? null;
$status = $_GET['status'] ?? null;
$jenis = $_GET['jenis'] ?? null;
$id = $_GET['id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'user_id required']);
    exit;
}

$conn = koneksi();

$sql = "SELECT t.*, p.nama_produk, p.provider
        FROM transaksi t 
        LEFT JOIN produk p ON t.produk_id = p.id 
        WHERE t.user_id = ?";
$params = [$user_id];
$types = "i";

if ($status) {
    $sql .= " AND t.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($jenis) {
    $sql .= " AND t.jenis_transaksi = ?";
    $params[] = $jenis;
    $types .= "s";
}

if ($id) {
    $sql .= " AND t.id = ?";
    $params[] = $id;
    $types .= "i";
}

$sql .= " ORDER BY t.created_at DESC LIMIT 50";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
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

echo json_encode([
    'success' => true,
    'data' => $transactions
]);

$conn->close();