-- =============================================
-- DATABASE PPOB (Payment Point Online Bank)
-- =============================================

CREATE DATABASE IF NOT EXISTS db_ppob;
USE db_ppob;

-- =============================================
-- TABEL USERS (Admin & Member)
-- =============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    no_hp VARCHAR(15) NOT NULL,
    saldo DECIMAL(15,2) DEFAULT 0,
    role ENUM('admin', 'member') DEFAULT 'member',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================
-- TABEL KATEGORI PRODUK
-- =============================================
CREATE TABLE kategori_produk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(50) NOT NULL,
    icon VARCHAR(50) NOT NULL,
    warna VARCHAR(20) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- TABEL PRODUK (Pulsa, Kuota, Token Listrik)
-- =============================================
CREATE TABLE produk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategori_id INT NOT NULL,
    kode_produk VARCHAR(50) NOT NULL UNIQUE,
    nama_produk VARCHAR(100) NOT NULL,
    provider VARCHAR(50),
    nominal DECIMAL(15,2) NOT NULL,
    harga_jual DECIMAL(15,2) NOT NULL,
    harga_modal DECIMAL(15,2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategori_produk(id)
);

-- =============================================
-- TABEL TRANSAKSI
-- =============================================
CREATE TABLE transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    produk_id INT,
    no_invoice VARCHAR(50) NOT NULL UNIQUE,
    jenis_transaksi ENUM('pulsa', 'kuota', 'listrik', 'transfer') NOT NULL,
    no_tujuan VARCHAR(50) NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    harga DECIMAL(15,2) NOT NULL,
    biaya_admin DECIMAL(15,2) DEFAULT 0,
    total_bayar DECIMAL(15,2) NOT NULL,
    saldo_sebelum DECIMAL(15,2) NOT NULL,
    saldo_sesudah DECIMAL(15,2) NOT NULL,
    status ENUM('pending', 'success', 'failed', 'refund') DEFAULT 'pending',
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (produk_id) REFERENCES produk(id)
);

-- =============================================
-- TABEL TRANSFER TUNAI
-- =============================================
CREATE TABLE transfer_tunai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaksi_id INT NOT NULL,
    bank_tujuan VARCHAR(50) NOT NULL,
    no_rekening VARCHAR(30) NOT NULL,
    nama_penerima VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id)
);

-- =============================================
-- TABEL DEPOSIT / TOP UP SALDO
-- =============================================
CREATE TABLE deposit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    no_deposit VARCHAR(50) NOT NULL UNIQUE,
    nominal DECIMAL(15,2) NOT NULL,
    metode_bayar VARCHAR(50) NOT NULL,
    bukti_transfer VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- =============================================
-- TABEL PENGATURAN
-- =============================================
CREATE TABLE pengaturan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_key VARCHAR(50) NOT NULL UNIQUE,
    nilai TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================
-- INSERT DATA DEFAULT
-- =============================================

-- Insert Admin Default (password: admin123)
INSERT INTO users (username, password, nama_lengkap, email, no_hp, saldo, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@ppob.com', '081234567890', 10000000, 'admin'),
('member1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Budi Santoso', 'budi@email.com', '081234567891', 500000, 'member'),
('member2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Siti Rahayu', 'siti@email.com', '081234567892', 750000, 'member');

-- Insert Kategori Produk
INSERT INTO kategori_produk (nama_kategori, icon, warna) VALUES
('Pulsa', 'fa-mobile-alt', 'blue'),
('Kuota Internet', 'fa-wifi', 'green'),
('Token Listrik', 'fa-bolt', 'yellow'),
('Transfer Tunai', 'fa-money-bill-transfer', 'purple');

-- Insert Produk Pulsa
INSERT INTO produk (kategori_id, kode_produk, nama_produk, provider, nominal, harga_jual, harga_modal) VALUES
(1, 'TSEL5', 'Pulsa Telkomsel 5K', 'Telkomsel', 5000, 6500, 5500),
(1, 'TSEL10', 'Pulsa Telkomsel 10K', 'Telkomsel', 10000, 11500, 10500),
(1, 'TSEL25', 'Pulsa Telkomsel 25K', 'Telkomsel', 25000, 26500, 25500),
(1, 'TSEL50', 'Pulsa Telkomsel 50K', 'Telkomsel', 50000, 51500, 50500),
(1, 'TSEL100', 'Pulsa Telkomsel 100K', 'Telkomsel', 100000, 101500, 100500),
(1, 'XL5', 'Pulsa XL 5K', 'XL', 5000, 6000, 5300),
(1, 'XL10', 'Pulsa XL 10K', 'XL', 10000, 11000, 10300),
(1, 'XL25', 'Pulsa XL 25K', 'XL', 25000, 26000, 25300),
(1, 'XL50', 'Pulsa XL 50K', 'XL', 50000, 51000, 50300),
(1, 'ISAT5', 'Pulsa Indosat 5K', 'Indosat', 5000, 6200, 5400),
(1, 'ISAT10', 'Pulsa Indosat 10K', 'Indosat', 10000, 11200, 10400),
(1, 'ISAT25', 'Pulsa Indosat 25K', 'Indosat', 25000, 26200, 25400),
(1, 'TRI5', 'Pulsa Tri 5K', 'Tri', 5000, 5800, 5200),
(1, 'TRI10', 'Pulsa Tri 10K', 'Tri', 10000, 10800, 10200);

-- Insert Produk Kuota
INSERT INTO produk (kategori_id, kode_produk, nama_produk, provider, nominal, harga_jual, harga_modal) VALUES
(2, 'TSELDATA1', 'Kuota Telkomsel 1GB', 'Telkomsel', 1000, 15000, 13000),
(2, 'TSELDATA2', 'Kuota Telkomsel 2GB', 'Telkomsel', 2000, 25000, 22000),
(2, 'TSELDATA5', 'Kuota Telkomsel 5GB', 'Telkomsel', 5000, 50000, 45000),
(2, 'TSELDATA10', 'Kuota Telkomsel 10GB', 'Telkomsel', 10000, 85000, 78000),
(2, 'XLDATA1', 'Kuota XL 1GB', 'XL', 1000, 12000, 10000),
(2, 'XLDATA3', 'Kuota XL 3GB', 'XL', 3000, 30000, 26000),
(2, 'XLDATA5', 'Kuota XL 5GB', 'XL', 5000, 45000, 40000),
(2, 'ISATDATA1', 'Kuota Indosat 1GB', 'Indosat', 1000, 13000, 11000),
(2, 'ISATDATA3', 'Kuota Indosat 3GB', 'Indosat', 3000, 32000, 28000);

-- Insert Produk Token Listrik
INSERT INTO produk (kategori_id, kode_produk, nama_produk, provider, nominal, harga_jual, harga_modal) VALUES
(3, 'PLN20', 'Token Listrik 20K', 'PLN', 20000, 22500, 20500),
(3, 'PLN50', 'Token Listrik 50K', 'PLN', 50000, 52500, 50500),
(3, 'PLN100', 'Token Listrik 100K', 'PLN', 100000, 102500, 100500),
(3, 'PLN200', 'Token Listrik 200K', 'PLN', 200000, 202500, 200500),
(3, 'PLN500', 'Token Listrik 500K', 'PLN', 500000, 502500, 500500),
(3, 'PLN1000', 'Token Listrik 1JT', 'PLN', 1000000, 1002500, 1000500);

-- Insert Pengaturan
INSERT INTO pengaturan (nama_key, nilai) VALUES
('nama_aplikasi', 'PPOB Express'),
('biaya_admin_transfer', '2500'),
('minimal_transfer', '10000'),
('maksimal_transfer', '50000000'),
('nomor_whatsapp', '081234567890');

-- =============================================
-- INSERT SAMPLE TRANSAKSI
-- =============================================
INSERT INTO transaksi (user_id, produk_id, no_invoice, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan, created_at) VALUES
(2, 1, 'INV20240101001', 'pulsa', '081234567890', 5000, 6500, 0, 6500, 506500, 500000, 'success', 'Pembelian pulsa berhasil', '2024-01-15 08:30:00'),
(2, 15, 'INV20240101002', 'kuota', '081234567890', 1000, 15000, 0, 15000, 515000, 500000, 'success', 'Pembelian kuota berhasil', '2024-01-16 10:15:00'),
(3, 24, 'INV20240101003', 'listrik', '12345678901', 50000, 52500, 0, 52500, 802500, 750000, 'success', 'Token: 1234-5678-9012-3456', '2024-01-17 14:20:00'),
(2, NULL, 'INV20240101004', 'transfer', '1234567890', 100000, 100000, 2500, 102500, 602500, 500000, 'success', 'Transfer ke BCA berhasil', '2024-01-18 09:45:00'),
(3, 5, 'INV20240101005', 'pulsa', '085678901234', 100000, 101500, 0, 101500, 851500, 750000, 'success', 'Pembelian pulsa berhasil', '2024-01-19 16:30:00');
