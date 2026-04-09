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
    )",
    
    "CREATE TABLE IF NOT EXISTS games (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(50) UNIQUE NOT NULL,
        provider VARCHAR(50) DEFAULT NULL,
        color VARCHAR(20) DEFAULT '#6353D8',
        icon_url VARCHAR(255),
        needs_zone_id ENUM('yes', 'no') DEFAULT 'no',
        is_active ENUM('yes', 'no') DEFAULT 'yes',
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )"
];

// Insert games data
$games = [
    ['Mobile Legends', 'mobile-legends', 'Moonton', '#3653D8', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/MLBB-2025-tiles-178x178.jpg', 'yes', 1],
    ['Free Fire', 'free-fire', 'Garena', '#FF5272', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/free-fire-tile-codacash-new.jpg', 'no', 2],
    ['PUBG Mobile', 'pubg-mobile', 'Tencent', '#FF8B00', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/pubgm_tile_aug2024.jpg', 'no', 3],
    ['Genshin Impact', 'genshin-impact', 'HoYoverse', '#4B9FE8', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/genshinimpact_tile.jpg', 'no', 4],
    ['Valorant', 'valorant', 'Riot Games', '#FF4565', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/valorant_tile.jpg', 'no', 5],
    ['Call of Duty Mobile', 'cod-mobile', 'Activision', '#FFD2D2', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/CODM-tile-codacash-new.jpg', 'no', 6],
    ['FIFA Mobile', 'fifa-mobile', 'EA Sports', '#E2CC71', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/EA_FC_Oct_2025.png', 'no', 7],
    ['Honor of Kings', 'honor-of-kings', 'Tencent', '#AE6A24', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/HonorofKings_Codacash178x178.jpg', 'no', 8],
    ['Free Fire MAX', 'free-fire-max', 'Garena', '#FF5272', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/FFMAX_tile.jpg', 'no', 9],
    ['One Punch Man', 'one-punch-man', 'NEEDEE', '#F5A623', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/onepunchman.jpg', 'no', 10],
    ['Blood Strike', 'blood-strike', 'Netmarble', '#FF4444', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/BloodStrike_tile.jpg', 'no', 11],
    ['Asphalt 9', 'asphalt-9', 'Gameloft', '#2D5BE3', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/Asphalt9_tile.jpg', 'no', 12],
    ['MapleStory M', 'maplestory-m', 'Nexon', '#FF8C00', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/MapleStoryM_tile.jpg', 'no', 13],
    ['Ragnarok M', 'ragnarok-m', 'Gravity', '#FF6B35', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/RagnarokM_tile.jpg', 'no', 14],
    ['League of Legends Wild Rift', 'lol-wild-rift', 'Riot Games', '#C89B3C', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/lolwildrift_tile.jpg', 'no', 15],
    ['Arena of Valor', 'arena-of-valor', 'Tencent', '#E53935', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/AOV_tile.jpg', 'no', 16],
    ['Higgs Domino', 'higgs-domino', 'Topkar', '#FF6B6B', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/higgs.jpg', 'no', 17],
    ['AXE: Offline Royale', 'axe-offline-royale', 'Cubic Games', '#FFD700', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/axe.jpg', 'no', 18],
    ['Marvel Future Fight', 'marvel-future-fight', 'Netmarble', '#E53935', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/marvel.jpg', 'no', 19],
    ['Saint Seiya: Awakening', 'saint-seiya', 'Netmarble', '#1E90FF', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/saintseiya.jpg', 'no', 20],
    ['Dragon Raja', 'dragon-raja', 'Archosaurus', '#FF4500', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/dragonraja.jpg', 'no', 21],
    ['LifeAfter', 'lifeafter', 'NetEase', '#FF6B35', 'https://cdn1.codashop.com/S/content/mobile/images/product-tiles/lifeafter.jpg', 'no', 22]
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

// Check if games table already has data (after table creation)
$checkGames = $conn->query("SELECT COUNT(*) as cnt FROM games");
$gamesCount = $checkGames ? $checkGames->fetch_assoc()['cnt'] : 0;

if ($gamesCount == 0) {
    $stmt = $conn->prepare("INSERT IGNORE INTO games (name, slug, provider, color, icon_url, needs_zone_id, display_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($games as $game) {
        $stmt->bind_param("ssssssi", $game[0], $game[1], $game[2], $game[3], $game[4], $game[5], $game[6]);
        $stmt->execute();
    }
    echo "✅ Inserted " . count($games) . " games\n";
}

echo "\n====================\n";
echo "Total: " . count($queries) . " queries\n";
echo "Success: $success\n";
echo "Failed: $failed\n";

$conn->close();
