<?php
require_once 'config.php';

$conn = koneksi();

$sql = "CREATE TABLE IF NOT EXISTS transaksi_pos_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaksi_id INT NOT NULL,
    produk_id INT,
    nama_item VARCHAR(100),
    qty INT NOT NULL,
    harga_saat_transaksi DECIMAL(15,2) NOT NULL,
    total_harga DECIMAL(15,2) NOT NULL,
    is_manual ENUM('yes', 'no') DEFAULT 'no',
    FOREIGN KEY (transaksi_id) REFERENCES transaksi_pos(id) ON DELETE CASCADE,
    FOREIGN KEY (produk_id) REFERENCES produk_pos(id) ON DELETE SET NULL
)";

if ($conn->query($sql)) {
    echo "✅ Tabel transaksi_pos_detail berhasil dibuat!";
} else {
    echo "❌ Error: " . $conn->error;
}

$conn->close();
