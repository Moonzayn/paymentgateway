<?php
header('Content-Type: text/plain');
$conn = new mysqli('localhost', 'root', '', 'db_ppob');

// Update SKU berdasarkan kode_produk
$updates = [
    // Telkomsel
    ['kode_produk' => 'TSEL10', 'sku' => 'TSEL10'],
    ['kode_produk' => 'TSEL20', 'sku' => 'TSEL20'],
    ['kode_produk' => 'TSEL25', 'sku' => 'TSEL25'],
    ['kode_produk' => 'TSEL30', 'sku' => 'TSEL30'],
    ['kode_produk' => 'TSEL50', 'sku' => 'TSEL50'],
    ['kode_produk' => 'TSEL60', 'sku' => 'TSEL60'],
    ['kode_produk' => 'TSEL100', 'sku' => 'TSEL100'],

    // XL
    ['kode_produk' => 'XL5', 'sku' => 'XL5'],
    ['kode_produk' => 'XL10', 'sku' => 'XL10'],
    ['kode_produk' => 'XL25', 'sku' => 'XL25'],
    ['kode_produk' => 'XL30', 'sku' => 'XL30'],
    ['kode_produk' => 'XL50', 'sku' => 'XL50'],
    ['kode_produk' => 'XL100', 'sku' => 'XL100'],

    // Indosat
    ['kode_produk' => 'ISAT5', 'sku' => 'IS5'],
    ['kode_produk' => 'ISAT10', 'sku' => 'IS10'],
    ['kode_produk' => 'ISAT25', 'sku' => 'IS25'],
    ['kode_produk' => 'ISAT50', 'sku' => 'IS50'],
    ['kode_produk' => 'ISAT100', 'sku' => 'IS100'],

    // Tri
    ['kode_produk' => 'TRI5', 'sku' => 'TRI5'],
    ['kode_produk' => 'TRI10', 'sku' => 'TRI10'],
    ['kode_produk' => 'TRI25', 'sku' => 'TRI25'],
    ['kode_produk' => 'TRI50', 'sku' => 'TRI50'],
    ['kode_produk' => 'TRI100', 'sku' => 'TRI100'],

    // Smartfren
    ['kode_produk' => 'SF5', 'sku' => 'SF5'],
    ['kode_produk' => 'SF10', 'sku' => 'SF10'],
    ['kode_produk' => 'SF25', 'sku' => 'SF25'],
    ['kode_produk' => 'SF50', 'sku' => 'SF50'],
    ['kode_produk' => 'SF100', 'sku' => 'SF100'],
];

echo "=== Update SKU Produk ===\n\n";

foreach ($updates as $update) {
    $stmt = $conn->prepare("UPDATE produk SET sku_code = ? WHERE kode_produk = ? AND (sku_code IS NULL OR sku_code = '')");
    $stmt->bind_param("ss", $update['sku'], $update['kode_produk']);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "OK: {$update['kode_produk']} -> {$update['sku']}\n";
    }
}

echo "\n=== Produk Pulsa dengan SKU ===\n";
$result = $conn->query("SELECT id, kode_produk, provider, nominal, harga_jual, sku_code FROM produk WHERE kategori_id = 1 AND sku_code IS NOT NULL ORDER BY provider, nominal");
while ($row = $result->fetch_assoc()) {
    echo "[{$row['sku_code']}] {$row['kode_produk']} - {$row['provider']} " . number_format($row['nominal']) . " -> Rp " . number_format($row['harga_jual']) . "\n";
}

echo "\n=== Produk Pulsa TANPA SKU ===\n";
$result = $conn->query("SELECT id, kode_produk, provider, nominal, harga_jual FROM produk WHERE kategori_id = 1 AND (sku_code IS NULL OR sku_code = '') ORDER BY provider, nominal");
while ($row = $result->fetch_assoc()) {
    echo "- {$row['kode_produk']} - {$row['provider']} " . number_format($row['nominal']) . "\n";
}

$conn->close();
echo "\nDone!\n";
