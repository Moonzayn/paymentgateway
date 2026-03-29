<?php
/**
 * Migration: Add Aggregator Columns
 * Run via browser: http://localhost/api/migrate_aggregator.php
 */

header('Content-Type: text/plain');

$conn = new mysqli('localhost', 'root', '', 'db_ppob');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Migration: Add Aggregator Columns ===\n\n";

// Add columns
$sqls = [
    "ALTER TABLE produk ADD COLUMN sku_code VARCHAR(50) DEFAULT NULL AFTER provider",
    "ALTER TABLE produk ADD COLUMN aggregator VARCHAR(50) DEFAULT 'digiflazz' AFTER sku_code",
    "ALTER TABLE produk ADD COLUMN last_sync_at DATETIME DEFAULT NULL AFTER updated_at"
];

foreach ($sqls as $sql) {
    try {
        $conn->query($sql);
        echo "OK: " . substr($sql, 0, 50) . "...\n";
    } catch (Exception $e) {
        echo "SKIP: " . $e->getMessage() . "\n";
    }
}

// Update TSEL5
$conn->query("UPDATE produk SET sku_code = 'TSEL5' WHERE provider = 'Telkomsel' AND nominal = 5000");
echo "\nTSEL5 updated: " . $conn->affected_rows . " rows affected\n";

// Update other common SKUs
$sku_updates = [
    'TSEL10' => ['Telkomsel', 10000],
    'TSEL20' => ['Telkomsel', 20000],
    'TSEL25' => ['Telkomsel', 25000],
    'TSEL50' => ['Telkomsel', 50000],
    'TSEL100' => ['Telkomsel', 100000],
    'XL5' => ['XL', 5000],
    'XL10' => ['XL', 10000],
    'XL25' => ['XL', 25000],
    'XL50' => ['XL', 50000],
    'XL100' => ['XL', 100000],
    'IS5' => ['Indosat', 5000],
    'IS10' => ['Indosat', 10000],
    'IS25' => ['Indosat', 25000],
    'IS50' => ['Indosat', 50000],
    'IS100' => ['Indosat', 100000],
];

echo "\nUpdating other SKUs...\n";
foreach ($sku_updates as $sku => $criteria) {
    $stmt = $conn->prepare("UPDATE produk SET sku_code = ? WHERE provider = ? AND nominal = ? AND sku_code IS NULL");
    $stmt->bind_param("sii", $sku, $criteria[0], $criteria[1]);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo "OK: $sku ({$criteria[0]} {$criteria[1]})\n";
    }
}

// Show all pulsa products
echo "\n=== Semua produk pulsa ===\n";
$result = $conn->query("SELECT id, provider, nominal, harga_jual, sku_code FROM produk WHERE kategori_id = 1 ORDER BY provider, nominal");
while ($row = $result->fetch_assoc()) {
    $sku = $row['sku_code'] ?: '-';
    echo "[{$sku}] {$row['provider']} {$row['nominal']} -> Rp " . number_format($row['harga_jual']) . "\n";
}

$conn->close();
echo "\n=== Done! ===\n";
