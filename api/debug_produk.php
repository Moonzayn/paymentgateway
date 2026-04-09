<?php
/**
 * Debug: Check produk table
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
$conn = koneksi();

$result = $conn->query("SELECT id, kode_produk, nama_produk, provider, nominal, harga_jual, status FROM produk WHERE kategori_id = 1 ORDER BY provider, nominal LIMIT 30");

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode([
    'success' => true,
    'count' => count($products),
    'products' => $products
]);
