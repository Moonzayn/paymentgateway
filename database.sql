-- =============================================
-- DATABASE UPDATE PPOB EXPRESS (Safe Version)
-- Run this file to update your database
-- Won't error if columns/tables already exist
-- =============================================

USE db_ppob;

-- =============================================
-- 1. KATEGORI PRODUK - Add Game category
-- =============================================
INSERT IGNORE INTO kategori_produk (id, nama_kategori, icon, warna, status) 
VALUES (4, 'Game', 'fa-gamepad', '#8b5cf6', 'active');

-- =============================================
-- 2. TRANSAKSI - Add new columns (Safe - won't error)
-- =============================================
-- Add ref_id column
-- Check if column exists first
SET @dbname = DATABASE();
SET @tablename = 'transaksi';
SET @columnname = 'ref_id';
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT ''Column ref_id already exists'' as msg',
    'ALTER TABLE transaksi ADD COLUMN ref_id VARCHAR(100) NULL AFTER produk_id'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add server_id column
SET @columnname = 'server_id';
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT ''Column server_id already exists'' as msg',
    'ALTER TABLE transaksi ADD COLUMN server_id VARCHAR(50) NULL AFTER no_tujuan'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add customer_id column
SET @columnname = 'customer_id';
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT ''Column customer_id already exists'' as msg',
    'ALTER TABLE transaksi ADD COLUMN customer_id VARCHAR(50) NULL AFTER server_id'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add api_response column
SET @columnname = 'api_response';
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT ''Column api_response already exists'' as msg',
    'ALTER TABLE transaksi ADD COLUMN api_response TEXT NULL AFTER keterangan'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================
-- 3. TRANSAKSI - Update enum for game support
-- =============================================
-- This might error if 'game' already in enum, wrap in ignore
-- ALTER TABLE transaksi MODIFY COLUMN jenis_transaksi ENUM('pulsa', 'kuota', 'listrik', 'transfer', 'game', 'deposit', 'admin') NOT NULL;

-- =============================================
-- 4. RATE LIMITS TABLE - For rate limiting security
-- =============================================
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(100) NOT NULL,
    requests_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_endpoint (ip_address, endpoint),
    INDEX idx_window (window_start)
);

-- =============================================
-- 5. TRANSACTION LOCKS - Prevent race conditions
-- =============================================
CREATE TABLE IF NOT EXISTS transaction_locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lock_key VARCHAR(100) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_lock_key (lock_key),
    INDEX idx_expires (expires_at)
);

-- =============================================
-- 6. WEBHOOK LOGS - For API callbacks
-- =============================================
CREATE TABLE IF NOT EXISTS webhook_logs (
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
-- 7. EMAIL QUEUE - For async email sending
-- =============================================
CREATE TABLE IF NOT EXISTS email_queue (
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
-- 8. SECURITY LOGS - Audit trail
-- =============================================
CREATE TABLE IF NOT EXISTS security_logs (
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
-- 9. SAMPLE GAME PRODUCTS (Optional - for testing)
-- =============================================
INSERT IGNORE INTO produk (kategori_id, kode_produk, nama_produk, provider, nominal, harga_jual, harga_modal) VALUES
(4, 'ML_DIAMOND_70', 'Mobile Legends 70 Diamonds', 'Moonton', 70000, 15000, 12000),
(4, 'ML_DIAMOND_140', 'Mobile Legends 140 Diamonds', 'Moonton', 140000, 28000, 24000),
(4, 'ML_DIAMOND_280', 'Mobile Legends 280 Diamonds', 'Moonton', 280000, 55000, 48000),
(4, 'ML_DIAMOND_500', 'Mobile Legends 500 Diamonds', 'Moonton', 500000, 95000, 85000),
(4, 'FF_DIAMOND_70', 'Free Fire 70 Diamonds', 'Garena', 70000, 14000, 11000),
(4, 'FF_DIAMOND_140', 'Free Fire 140 Diamonds', 'Garena', 140000, 27000, 22000),
(4, 'FF_DIAMOND_280', 'Free Fire 280 Diamonds', 'Garena', 280000, 53000, 45000),
(4, 'FF_DIAMOND_500', 'Free Fire 500 Diamonds', 'Garena', 500000, 93000, 80000),
(4, 'PUBGM_60', 'PUBG Mobile 60 UC', 'Tencent', 60000, 18000, 15000),
(4, 'PUBGM_120', 'PUBG Mobile 120 UC', 'Tencent', 120000, 35000, 30000),
(4, 'PUBGM_300', 'PUBG Mobile 300 UC', 'Tencent', 300000, 85000, 75000);

-- =============================================
-- 10. DEFAULT SETTINGS (if not exists)
-- =============================================
INSERT IGNORE INTO pengaturan (nama_key, nilai) VALUES
('minimal_deposit', '10000'),
('biaya_admin_transfer', '2500'),
('minimal_transfer', '10000'),
('maksimal_transfer', '50000000'),
('biaya_admin_listrik', '2500');

-- =============================================
-- DONE!
-- =============================================
SELECT '✅ Database update completed successfully!' as message;

-- Show all tables
SHOW TABLES;
