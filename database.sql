-- =============================================
-- FULL DATABASE PPOB EXPRESS
-- Export date: 2026-02-26
-- =============================================

-- DROP dan CREATE DATABASE
DROP DATABASE IF EXISTS db_ppob;
CREATE DATABASE db_ppob;
USE db_ppob;

-- =============================================
-- TABEL USERS
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
-- TABEL PRODUK
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
    ref_id VARCHAR(100) NULL,
    no_invoice VARCHAR(50) NOT NULL UNIQUE,
    jenis_transaksi ENUM('pulsa', 'kuota', 'listrik', 'transfer', 'game', 'deposit', 'admin') NOT NULL,
    no_tujuan VARCHAR(50) NOT NULL,
    server_id VARCHAR(50) NULL,
    customer_id VARCHAR(50) NULL,
    nominal DECIMAL(15,2) NOT NULL,
    harga DECIMAL(15,2) NOT NULL,
    biaya_admin DECIMAL(15,2) DEFAULT 0,
    total_bayar DECIMAL(15,2) NOT NULL,
    saldo_sebelum DECIMAL(15,2) NOT NULL,
    saldo_sesudah DECIMAL(15,2) NOT NULL,
    status ENUM('pending', 'success', 'failed', 'refund') DEFAULT 'pending',
    keterangan TEXT,
    api_response TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (produk_id) REFERENCES produk(id),
    INDEX idx_user (user_id),
    INDEX idx_produk (produk_id),
    INDEX idx_no_invoice (no_invoice),
    INDEX idx_jenis (jenis_transaksi),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- =============================================
-- TABEL DEPOSIT
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
-- TABEL RATE LIMITS
-- =============================================
CREATE TABLE rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(100) NOT NULL,
    requests_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_endpoint (ip_address, endpoint),
    INDEX idx_window (window_start)
);

-- =============================================
-- TABEL TRANSACTION LOCKS
-- =============================================
CREATE TABLE transaction_locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lock_key VARCHAR(100) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_lock_key (lock_key),
    INDEX idx_expires (expires_at)
);

-- =============================================
-- TABEL WEBHOOK LOGS
-- =============================================
CREATE TABLE webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(100) NOT NULL,
    payload TEXT,
    response TEXT,
    status_code INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_endpoint (endpoint),
    INDEX idx_created (created_at)
);

-- =============================================
-- TABEL EMAIL QUEUE
-- =============================================
CREATE TABLE email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(255) NOT NULL,
    subject VARCHAR(500),
    body TEXT,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_to_email (to_email)
);

-- =============================================
-- TABEL SECURITY LOGS
-- =============================================
CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
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
-- TABEL ADMIN ACTIVITY
-- =============================================
CREATE TABLE admin_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
);

-- =============================================
-- TABEL API KEYS
-- =============================================
CREATE TABLE api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    api_key VARCHAR(255) NOT NULL UNIQUE,
    secret_key VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user (user_id)
);

-- =============================================
-- TABEL LOGIN ATTEMPTS
-- =============================================
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempts INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier),
    INDEX idx_ip (ip_address)
);

-- =============================================
-- TABEL USER SESSIONS
-- =============================================
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user (user_id),
    INDEX idx_session (session_id)
);

-- =============================================
-- INSERT DATA DEFAULT - KATEGORI
-- =============================================
INSERT INTO kategori_produk (id, nama_kategori, icon, warna, status) VALUES
(1, 'Pulsa', 'fa-mobile-alt', 'blue', 'active'),
(2, 'Kuota Internet', 'fa-wifi', 'green', 'active'),
(3, 'Token Listrik', 'fa-bolt', 'yellow', 'active'),
(4, 'Transfer Tunai', 'fa-money-bill-transfer', 'purple', 'active'),
(5, 'Game', 'fa-gamepad', '#8b5cf6', 'active');

-- =============================================
-- INSERT DATA DEFAULT - PENGATURAN
-- =============================================
INSERT INTO pengaturan (nama_key, nilai) VALUES
('nama_aplikasi', 'PPOB Express'),
('minimal_deposit', '10000'),
('biaya_admin_transfer', '2500'),
('minimal_transfer', '10000'),
('maksimal_transfer', '50000000'),
('biaya_admin_listrik', '2500'),
('nomor_whatsapp', '081234567890'),
('min_password_length', '8'),
('max_login_attempts', '5'),
('login_timeout', '900'),
('session_lifetime', '3600'),
('maintenance_mode', 'false');

-- =============================================
-- INSERT SAMPLE PRODUK PULSA
-- =============================================
INSERT INTO produk (kategori_id, kode_produk, nama_produk, provider, nominal, harga_jual, harga_modal) VALUES
(1, 'TSEL5', 'Pulsa Telkomsel 5K', 'Telkomsel', 5000, 6500, 5500),
(1, 'TSEL10', 'Pulsa Telkomsel 10K', 'Telkomsel', 10000, 12500, 10500),
(1, 'TSEL20', 'Pulsa Telkomsel 20K', 'Telkomsel', 20000, 24500, 20500),
(1, 'TSEL25', 'Pulsa Telkomsel 25K', 'Telkomsel', 25000, 30000, 25000),
(1, 'TSEL50', 'Pulsa Telkomsel 50K', 'Telkomsel', 50000, 58500, 50000),
(1, 'TSEL100', 'Pulsa Telkomsel 100K', 'Telkomsel', 100000, 115000, 100000),
(1, 'XL5', 'Pulsa XL 5K', 'XL Axiata', 5000, 7000, 5500),
(1, 'XL10', 'Pulsa XL 10K', 'XL Axiata', 10000, 13000, 10500),
(1, 'AXIS5', 'Pulsa Axis 5K', 'Axis', 5000, 7500, 5500),
(1, 'AXIS10', 'Pulsa Axis 10K', 'Axis', 10000, 13500, 10500),
(1, 'INDOSAT10', 'Pulsa Indosat 10K', 'Indosat', 10000, 13000, 10500),
(1, 'INDOSAT20', 'Pulsa Indosat 20K', 'Indosat', 20000, 25000, 20500),
(1, 'SMARTFREN10', 'Pulsa Smartfren 10K', 'Smartfren', 10000, 13500, 10500),
(1, 'THREE10', 'Pulsa Three 10K', 'Three', 10000, 13500, 10500);

-- =============================================
-- INSERT SAMPLE PRODUK KUOTA
-- =============================================
INSERT INTO produk (kategori_id, kode_produk, nama_produk, provider, nominal, harga_jual, harga_modal) VALUES
(2, 'TSEL1GB', 'Kuota Telkomsel 1GB', 'Telkomsel', 1024, 15000, 12000),
(2, 'TSEL2GB', 'Kuota Telkomsel 2GB', 'Telkomsel', 2048, 25000, 20000),
(2, 'TSEL5GB', 'Kuota Telkomsel 5GB', 'Telkomsel', 5120, 50000, 42000),
(2, 'XL1GB', 'Kuota XL 1GB', 'XL Axiata', 1024, 14000, 11000),
(2, 'XL3GB', 'Kuota XL 3GB', 'XL Axiata', 3072, 35000, 28000),
(2, 'AXIS1GB', 'Kuota Axis 1GB', 'Axis', 1024, 14500, 11500),
(2, 'INDOSAT2GB', 'Kuota Indosat 2GB', 'Indosat', 2048, 27000, 22000),
(2, 'SMARTFREN3GB', 'Kuota Smartfren 3GB', 'Smartfren', 3072, 33000, 27000),
(2, 'THREE3GB', 'Kuota Three 3GB', 'Three', 3072, 30000, 25000);

-- =============================================
-- INSERT SAMPLE PRODUK LISTRIK
-- =============================================
INSERT INTO produk (kategori_id, kode_produk, nama_produk, provider, nominal, harga_jual, harga_modal) VALUES
(3, 'PLN20', 'Token Listrik PLN 20K', 'PLN', 20000, 22500, 20000),
(3, 'PLN50', 'Token Listrik PLN 50K', 'PLN', 50000, 52500, 50000),
(3, 'PLN100', 'Token Listrik PLN 100K', 'PLN', 100000, 102500, 100000),
(3, 'PLN150', 'Token Listrik PLN 150K', 'PLN', 150000, 152500, 150000),
(3, 'PLN200', 'Token Listrik PLN 200K', 'PLN', 200000, 202500, 200000),
(3, 'PLN500', 'Token Listrik PLN 500K', 'PLN', 500000, 502500, 500000);

-- =============================================
-- INSERT SAMPLE PRODUK GAME
-- =============================================
INSERT INTO produk (kategori_id, kode_produk, nama_produk, provider, nominal, harga_jual, harga_modal) VALUES
(5, 'ML_DIAMOND_70', 'Mobile Legends 70 Diamonds', 'Moonton', 70000, 15000, 12000),
(5, 'ML_DIAMOND_140', 'Mobile Legends 140 Diamonds', 'Moonton', 140000, 28000, 24000),
(5, 'ML_DIAMOND_280', 'Mobile Legends 280 Diamonds', 'Moonton', 280000, 55000, 48000),
(5, 'ML_DIAMOND_500', 'Mobile Legends 500 Diamonds', 'Moonton', 500000, 95000, 85000),
(5, 'FF_DIAMOND_70', 'Free Fire 70 Diamonds', 'Garena', 70000, 14000, 11000),
(5, 'FF_DIAMOND_140', 'Free Fire 140 Diamonds', 'Garena', 140000, 27000, 22000),
(5, 'FF_DIAMOND_280', 'Free Fire 280 Diamonds', 'Garena', 280000, 53000, 45000),
(5, 'FF_DIAMOND_500', 'Free Fire 500 Diamonds', 'Garena', 500000, 93000, 80000),
(5, 'PUBGM_60', 'PUBG Mobile 60 UC', 'Tencent', 60000, 18000, 15000),
(5, 'PUBGM_120', 'PUBG Mobile 120 UC', 'Tencent', 120000, 35000, 30000),
(5, 'PUBGM_300', 'PUBG Mobile 300 UC', 'Tencent', 300000, 85000, 75000);

-- =============================================
-- SELESAI!
-- =============================================
SELECT '✅ Database created successfully!' as message;
