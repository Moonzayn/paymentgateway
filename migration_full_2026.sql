-- =============================================
-- FULL DATABASE MIGRATION PPOB EXPRESS
-- Created: 2026-03-04
-- =============================================

-- DROP dan CREATE DATABASE
DROP DATABASE IF EXISTS db_ppob;
CREATE DATABASE db_ppob;
USE db_ppob;

-- =============================================
-- TABEL USERS (dengan kolum tambahan)
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
    default_store_id INT NULL,
    is_super_admin ENUM('yes', 'no') DEFAULT 'no',
    force_2fa ENUM('yes', 'no') DEFAULT 'no',
    last_2fa_login TIMESTAMP NULL,
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
    store_id INT NULL,
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
    store_id INT NULL,
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
    store_id INT NULL,
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
    store_id INT NULL,
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
-- TABEL STORES (Multi-Tenant)
-- =============================================
CREATE TABLE stores (
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
);

-- =============================================
-- TABEL STORE USERS
-- =============================================
CREATE TABLE store_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'kasir_pos', 'kasir_ppob', 'kasir_all') DEFAULT 'kasir_pos',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_store_user (store_id, user_id)
);

-- =============================================
-- TABEL HARGA PPOB STORE
-- =============================================
CREATE TABLE harga_ppob_store (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    produk_id INT NOT NULL,
    harga_jual DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE CASCADE,
    UNIQUE KEY unique_store_produk (store_id, produk_id)
);

-- =============================================
-- TABEL KATEGORI PRODUK POS
-- =============================================
CREATE TABLE kategori_produk_pos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    nama_kategori VARCHAR(50) NOT NULL,
    icon VARCHAR(50),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);

-- =============================================
-- TABEL PRODUK POS
-- =============================================
CREATE TABLE produk_pos (
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
);

-- =============================================
-- TABEL TRANSAKSI POS
-- =============================================
CREATE TABLE transaksi_pos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    user_id INT NOT NULL,
    no_invoice VARCHAR(50) NOT NULL UNIQUE,
    total_item INT DEFAULT 0,
    total_bayar DECIMAL(15,2) NOT NULL,
    metode_bayar ENUM('cash', 'qris', 'transfer') DEFAULT 'cash',
    nominal_bayar DECIMAL(15,2) DEFAULT 0,
    kembalian DECIMAL(15,2) DEFAULT 0,
    status ENUM('pending', 'completed', 'void') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_store (store_id),
    INDEX idx_user (user_id),
    INDEX idx_no_invoice (no_invoice)
);

-- =============================================
-- TABEL TRANSAKSI POS DETAIL
-- =============================================
CREATE TABLE transaksi_pos_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaksi_id INT NOT NULL,
    produk_id INT NOT NULL,
    harga_modal DECIMAL(15,2) NOT NULL,
    harga_jual DECIMAL(15,2) NOT NULL,
    qty INT DEFAULT 1,
    subtotal DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi_pos(id) ON DELETE CASCADE,
    FOREIGN KEY (produk_id) REFERENCES produk_pos(id)
);

-- =============================================
-- TABEL CHAT MESSAGES
-- =============================================
CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT,
    sender_id INT NOT NULL,
    sender_role ENUM('owner', 'kasir', 'superadmin') NOT NULL,
    sender_name VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_store_id (store_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_created_at (created_at)
);

-- =============================================
-- TABEL USER 2FA
-- =============================================
CREATE TABLE user_2fa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    secret_key VARCHAR(64) NOT NULL,
    backup_codes TEXT,
    enabled ENUM('yes', 'no') DEFAULT 'no',
    enabled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_enabled (enabled)
);

-- =============================================
-- TABEL USER 2FA LOGIN ATTEMPTS
-- =============================================
CREATE TABLE user_2fa_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success ENUM('yes', 'no') DEFAULT 'no',
    code_used VARCHAR(10),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_time (user_id, attempt_time)
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
(1, 'TSEL25', 'Pulsa Telkomsel 25K', 'Telkomsel', 25000, 30200, 25200),
(1, 'TSEL50', 'Pulsa Telkomsel 50K', 'Telkomsel', 50000, 59000, 49000),
(1, 'TSEL100', 'Pulsa Telkomsel 100K', 'Telkomsel', 100000, 115000, 98000);

-- =============================================
-- INSERT SAMPLE PRODUK KUOTA
-- =============================================
INSERT INTO produk (kategori_id, kode_produk, nama_produk, provider, nominal, harga_jual, harga_modal) VALUES
(2, 'TSEL1GB', 'Telkomsel 1GB', 'Telkomsel', 1, 15000, 12000),
(2, 'TSEL2GB', 'Telkomsel 2GB', 'Telkomsel', 2, 25000, 20000),
(2, 'TSEL5GB', 'Telkomsel 5GB', 'Telkomsel', 5, 45000, 38000),
(2, 'TSEL10GB', 'Telkomsel 10GB', 'Telkomsel', 10, 75000, 65000),
(2, 'AXIS1GB', 'Axis 1GB', 'Axis', 1, 14000, 11000),
(2, 'AXIS2GB', 'Axis 2GB', 'Axis', 2, 23000, 18000),
(2, 'INDOSAT2GB', 'Indosat 2GB', 'Indosat', 2, 24000, 19000),
(2, 'INDOSAT5GB', 'Indosat 5GB', 'Indosat', 5, 44000, 37000);

-- =============================================
-- INSERT SAMPLE PRODUK LISTRIK
-- =============================================
INSERT INTO produk (kategori_id, kode_produk, nama_produk, provider, nominal, harga_jual, harga_modal) VALUES
(3, 'PLN20', 'Token Listrik PLN 20K', 'PLN', 20000, 22500, 20500),
(3, 'PLN50', 'Token Listrik PLN 50K', 'PLN', 50000, 52500, 50500),
(3, 'PLN100', 'Token Listrik PLN 100K', 'PLN', 100000, 104000, 101000),
(3, 'PLN200', 'Token Listrik PLN 200K', 'PLN', 200000, 206000, 201000),
(3, 'PLN500', 'Token Listrik PLN 500K', 'PLN', 500000, 510000, 501000);

-- =============================================
-- INSERT SAMPLE PRODUK GAME
-- =============================================
INSERT INTO produk (kategori_id, kode_produk, nama_produk, provider, nominal, harga_jual, harga_modal) VALUES
(5, 'ML10', 'Mobile Legends 10 Diamonds', 'Moonton', 10, 4500, 3500),
(5, 'ML55', 'Mobile Legends 55 Diamonds', 'Moonton', 55, 22000, 18000),
(5, 'ML112', 'Mobile Legends 112 Diamonds', 'Moonton', 112, 42000, 35000),
(5, 'FF50', 'Free Fire 50 Diamonds', 'Garena', 50, 18000, 14000),
(5, 'FF100', 'Free Fire 100 Diamonds', 'Garena', 100, 34000, 28000),
(5, 'FF280', 'Free Fire 280 Diamonds', 'Garena', 280, 89000, 75000),
(5, 'PUBGM40', 'PUBG Mobile 40 UC', 'Tencent', 40, 18000, 14000),
(5, 'PUBGM100', 'PUBG Mobile 100 UC', 'Tencent', 100, 42000, 35000);

-- =============================================
-- INSERT SAMPLE USER (ADMIN)
-- =============================================
INSERT INTO users (username, password, nama_lengkap, email, no_hp, saldo, role, status, is_super_admin) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@ppobexpress.com', '081234567890', 0, 'admin', 'active', 'yes'),
('member1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Member Satu', 'member1@ppobexpress.com', '081234567891', 100000, 'member', 'active', 'no'),
('member2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Member Dua', 'member2@ppobexpress.com', '081234567892', 50000, 'member', 'active', 'no');

-- =============================================
-- INSERT SAMPLE STORE
-- =============================================
INSERT INTO stores (nama_toko, slug, alamat, no_hp, email, status) VALUES
('Toko Utama', 'toko-utama', 'Jl. Merdeka No. 1', '081234567890', 'toko@ppobexpress.com', 'active');

-- =============================================
-- GRANT PRIVILEGES (Optional - adjust as needed)
-- =============================================
-- GRANT ALL PRIVILEGES ON db_ppob.* TO 'root'@'localhost';
-- FLUSH PRIVILEGES;

-- =============================================
-- DONE!
-- =============================================
