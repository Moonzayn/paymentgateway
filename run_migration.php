<?php
require_once 'config.php';

$conn = koneksi();

$queries = [
    "CREATE TABLE IF NOT EXISTS stores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_toko VARCHAR(100) NOT NULL,
        slug VARCHAR(50) UNIQUE,
        alamat TEXT,
        no_hp VARCHAR(20),
        email VARCHAR(100),
        logo VARCHAR(255),
        qr_code VARCHAR(255),
        api_key VARCHAR(100) UNIQUE,
        status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS store_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        user_id INT NOT NULL,
        role ENUM('owner', 'kasir_pos', 'kasir_ppob', 'kasir_all') DEFAULT 'kasir_pos',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_store_user (store_id, user_id)
    )",
    
    "CREATE TABLE IF NOT EXISTS harga_ppob_store (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        produk_id INT NOT NULL,
        harga_jual DECIMAL(15,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
        FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE CASCADE,
        UNIQUE KEY unique_store_produk (store_id, produk_id)
    )",
    
    "ALTER TABLE users ADD COLUMN default_store_id INT NULL",
    "ALTER TABLE users ADD COLUMN is_super_admin ENUM('yes', 'no') DEFAULT 'no'",
    "ALTER TABLE transaksi ADD COLUMN store_id INT NULL",
    "ALTER TABLE deposit ADD COLUMN store_id INT NULL",
    "ALTER TABLE produk ADD COLUMN store_id INT NULL",
    "ALTER TABLE kategori_produk ADD COLUMN store_id INT NULL",
    
    "CREATE TABLE IF NOT EXISTS kategori_produk_pos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        nama_kategori VARCHAR(50) NOT NULL,
        icon VARCHAR(50),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS produk_pos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        kategori_id INT,
        kode_barcode VARCHAR(50),
        nama_produk VARCHAR(100) NOT NULL,
        harga_jual DECIMAL(15,2) NOT NULL,
        harga_modal DECIMAL(15,2) DEFAULT 0,
        stok INT DEFAULT 0,
        foto VARCHAR(255),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
        FOREIGN KEY (kategori_id) REFERENCES kategori_produk_pos(id) ON DELETE SET NULL
    )",
    
    "CREATE TABLE IF NOT EXISTS transaksi_pos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        user_id INT NOT NULL,
        no_invoice VARCHAR(50) NOT NULL,
        total_item INT DEFAULT 0,
        subtotal DECIMAL(15,2) NOT NULL,
        diskon DECIMAL(15,2) DEFAULT 0,
        total_bayar DECIMAL(15,2) NOT NULL,
        metode_bayar ENUM('qris', 'cash') NOT NULL,
        qris_reference VARCHAR(100),
        qris_string TEXT,
        qris_expired_at TIMESTAMP NULL,
        uang_diberikan DECIMAL(15,2) DEFAULT 0,
        kembalian DECIMAL(15,2) DEFAULT 0,
        notes VARCHAR(255),
        status ENUM('pending', 'success', 'failed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id),
        INDEX idx_store (store_id),
        INDEX idx_user (user_id),
        INDEX idx_no_invoice (no_invoice),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    )",
    
    "CREATE TABLE IF NOT EXISTS transaksi_pos_detail (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaksi_id INT NOT NULL,
        produk_id INT,
        nama_item VARCHAR(100),
        qty INT NOT NULL,
        harga_saat itu DECIMAL(15,2) NOT NULL,
        total_harga DECIMAL(15,2) NOT NULL,
        is_manual ENUM('yes', 'no') DEFAULT 'no',
        FOREIGN KEY (transaksi_id) REFERENCES transaksi_pos(id) ON DELETE CASCADE,
        FOREIGN KEY (produk_id) REFERENCES produk_pos(id) ON DELETE SET NULL
    )"
];

$success = 0;
$failed = 0;

foreach ($queries as $sql) {
    try {
        if ($conn->query($sql)) {
            $success++;
            echo "✅ Success: " . substr($sql, 0, 50) . "...\n";
        }
    } catch (Exception $e) {
        $failed++;
        echo "❌ Failed: " . $e->getMessage() . "\n";
    }
}

echo "\n====================\n";
echo "Total: " . count($queries) . " queries\n";
echo "Success: $success\n";
echo "Failed: $failed\n";

$conn->close();
