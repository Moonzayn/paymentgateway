<?php
/**
 * Update Produk SKU untuk Digiflazz
 * Jalankan script ini sekali untuk set SKU produk
 */

require_once 'config.php';

$conn = koneksi();

// Cek kolom sku_code ada belum
$result = $conn->query("SHOW COLUMNS FROM produk LIKE 'sku_code'");
if ($result->num_rows == 0) {
    echo "Menambah kolom sku_code ke tabel produk...\n";
    $conn->query("ALTER TABLE produk ADD COLUMN sku_code VARCHAR(50) DEFAULT NULL AFTER provider");
    $conn->query("ALTER TABLE produk ADD COLUMN aggregator VARCHAR(50) DEFAULT 'digiflazz' AFTER sku_code");
    echo "Kolom berhasil ditambahkan!\n";
}

// Mapping SKU Digiflazz untuk produk yang ada
$sku_mapping = [
    // Telkomsel
    'TSEL5' => ['provider' => 'Telkomsel', 'nominal' => 5000],
    'TSEL10' => ['provider' => 'Telkomsel', 'nominal' => 10000],
    'TSEL20' => ['provider' => 'Telkomsel', 'nominal' => 20000],
    'TSEL25' => ['provider' => 'Telkomsel', 'nominal' => 25000],
    'TSEL50' => ['provider' => 'Telkomsel', 'nominal' => 50000],
    'TSEL100' => ['provider' => 'Telkomsel', 'nominal' => 100000],

    // XL
    'XL5' => ['provider' => 'XL', 'nominal' => 5000],
    'XL10' => ['provider' => 'XL', 'nominal' => 10000],
    'XL25' => ['provider' => 'XL', 'nominal' => 25000],
    'XL50' => ['provider' => 'XL', 'nominal' => 50000],
    'XL100' => ['provider' => 'XL', 'nominal' => 100000],

    // Indosat
    'IS5' => ['provider' => 'Indosat', 'nominal' => 5000],
    'IS10' => ['provider' => 'Indosat', 'nominal' => 10000],
    'IS25' => ['provider' => 'Indosat', 'nominal' => 25000],
    'IS50' => ['provider' => 'Indosat', 'nominal' => 50000],
    'IS100' => ['provider' => 'Indosat', 'nominal' => 100000],

    // Tri
    'TRI5' => ['provider' => 'Tri', 'nominal' => 5000],
    'TRI10' => ['provider' => 'Tri', 'nominal' => 10000],
    'TRI25' => ['provider' => 'Tri', 'nominal' => 25000],
    'TRI50' => ['provider' => 'Tri', 'nominal' => 50000],
    'TRI100' => ['provider' => 'Tri', 'nominal' => 100000],

    // Smartfren
    'SF5' => ['provider' => 'Smartfren', 'nominal' => 5000],
    'SF10' => ['provider' => 'Smartfren', 'nominal' => 10000],
    'SF25' => ['provider' => 'Smartfren', 'nominal' => 25000],
    'SF50' => ['provider' => 'Smartfren', 'nominal' => 50000],
    'SF100' => ['provider' => 'Smartfren', 'nominal' => 100000],
];

echo "=== Update SKU Produk untuk Digiflazz ===\n\n";

// Update berdasarkan mapping
$updated = 0;
foreach ($sku_mapping as $sku => $criteria) {
    $stmt = $conn->prepare("UPDATE produk SET sku_code = ? WHERE provider = ? AND nominal = ? AND sku_code IS NULL");
    $stmt->bind_param("sii", $sku, $criteria['provider'], $criteria['nominal']);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "Updated: {$sku} -> {$criteria['provider']} {$criteria['nominal']}\n";
        $updated += $stmt->affected_rows;
    }
}

// Update TSEL5 secara spesifik
echo "\nUpdating TSEL5...\n";
$stmt = $conn->prepare("UPDATE produk SET sku_code = 'TSEL5' WHERE provider = 'Telkomsel' AND nominal = 5000");
$stmt->execute();
echo "Affected: {$stmt->affected_rows}\n";

echo "\n=== Produk dengan SKU ===\n";
$result = $conn->query("SELECT id, provider, nominal, harga_jual, sku_code FROM produk WHERE sku_code IS NOT NULL ORDER BY provider, nominal");
while ($row = $result->fetch_assoc()) {
    echo "- [{$row['sku_code']}] {$row['provider']} {$row['nominal']} -> Rp " . number_format($row['harga_jual']) . "\n";
}

echo "\nTotal updated: {$updated}\n";
echo "Done!\n";
