<?php
/**
 * mysqlfixer.php — Auto-fix & seed database schema
 *
 * Jalankan via browser: http://localhost/paymentgateway/mysqlfixer.php
 * Atau CLI: php mysqlfixer.php
 *
 * Aman dijalankan berkali-kali (idempotent).
 */

// ─── Konfigurasi ────────────────────────────────────────────────────────────
$config = [
    'host'     => '127.0.0.1',
    'user'     => 'root',
    'pass'     => '',
    'database' => 'db_ppob',
];

// ─── Helper ─────────────────────────────────────────────────────────────────
function msg($type, $text) {
    $icons = ['ok' => '✅', 'warn' => '⚠️', 'err' => '❌', 'info' => 'ℹ️', 'skip' => '➡️'];
    echo $icons[$type] . " $text\n";
}

function query($conn, $sql, $silent = false) {
    $result = $conn->query($sql);
    if (!$result && !$silent) {
        msg('err', "SQL Error: " . $conn->error . "\n   Query: " . substr($sql, 0, 120));
    }
    return $result;
}

function colExists($conn, $table, $column) {
    $r = $conn->query("SELECT 1 FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME = '$table'
                          AND COLUMN_NAME = '$column'");
    return $r && $r->num_rows > 0;
}

function tableExists($conn, $table) {
    $r = $conn->query("SELECT 1 FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME = '$table'");
    return $r && $r->num_rows > 0;
}

function indexExists($conn, $table, $index) {
    $r = $conn->query("SELECT 1 FROM information_schema.STATISTICS
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME = '$table'
                          AND INDEX_NAME = '$index'");
    return $r && $r->num_rows > 0;
}

function seedRow($conn, $table, $uniqueCol, $uniqueVal, $data) {
    $r = $conn->query("SELECT 1 FROM `$table` WHERE `$uniqueCol` = '$uniqueVal'");
    if ($r && $r->num_rows > 0) {
        return false; // already exists
    }
    $cols = implode(', ', array_keys($data));
    $vals = implode(', ', array_map(fn($v) => "'" . $conn->real_escape_string($v) . "'", $data));
    $conn->query("INSERT INTO `$table` ($cols) VALUES ($vals)");
    return true;
}

// ─── Koneksi ────────────────────────────────────────────────────────────────
$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    echo "<!DOCTYPE html><html><head>
    <meta charset='UTF-8'>
    <title>MySQL Fixer</title>
    <style>
        body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; }
        .box { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1.5rem; max-width: 800px; margin: 2rem auto; }
        h1 { color: #38bdf8; margin-top: 0; }
        .ok { color: #4ade80; } .warn { color: #fbbf24; } .err { color: #f87171; }
        .skip { color: #94a3b8; } .info { color: #60a5fa; }
        pre { background: #0f172a; border-radius: 6px; padding: 1rem; overflow-x: auto; font-size: 0.85rem; line-height: 1.6; }
        a { color: #38bdf8; }
    </style>
    </head><body><div class='box'><h1>⚙️ MySQL Fixer</h1><pre>";
}

echo "Connecting to {$config['database']}...\n";
$conn = @new mysqli($config['host'], $config['user'], $config['pass'], $config['database']);
if ($conn->connect_error) {
    $conn = @new mysqli($config['host'], $config['user'], $config['pass']);
    if ($conn->connect_error) {
        msg('err', "Connection failed: " . $conn->connect_error);
        exit(1);
    }
    $conn->query("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $conn->select_db($config['database']);
    msg('ok', "Database '{$config['database']}' created.");
}

$conn->set_charset('utf8mb4');
msg('ok', "Connected.\n");

// ─── 1. produk table ─────────────────────────────────────────────────────────
echo "\n═══ TABLE: produk ═══\n";

if (!tableExists($conn, 'produk')) {
    $conn->query("CREATE TABLE `produk` (
        `id` int NOT NULL AUTO_INCREMENT,
        `kategori_id` int NOT NULL,
        `kode_produk` varchar(50) NOT NULL,
        `nama_produk` varchar(100) NOT NULL,
        `provider` varchar(50) DEFAULT NULL,
        `source_aggregator` varchar(50) NOT NULL DEFAULT 'digiflazz',
        `nominal` bigint unsigned DEFAULT 0,
        `nominal_display` varchar(50) DEFAULT NULL,
        `harga_jual` decimal(15,2) NOT NULL,
        `harga_modal` decimal(15,2) NOT NULL,
        `status` enum('active','inactive') DEFAULT 'active',
        `store_id` int DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_produk_kategori` (`kategori_id`),
        KEY `idx_produk_status` (`status`),
        KEY `idx_produk_kode` (`kode_produk`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    msg('ok', "Table `produk` created.");
} else {
    msg('skip', "Table `produk` already exists.");
}

// Add missing columns
$produkCols = [
    'source_aggregator' => "ADD COLUMN `source_aggregator` varchar(50) NOT NULL DEFAULT 'digiflazz' AFTER `provider`",
    'nominal_display'   => "ADD COLUMN `nominal_display` varchar(50) DEFAULT NULL AFTER `nominal`",
    'store_id'          => "ADD COLUMN `store_id` int DEFAULT NULL AFTER `status`",
    'created_at'        => "ADD COLUMN `created_at` timestamp DEFAULT CURRENT_TIMESTAMP AFTER `store_id`",
];
foreach ($produkCols as $col => $alter) {
    if (!colExists($conn, 'produk', $col)) {
        $conn->query("ALTER TABLE `produk` $alter");
        msg('ok', "Column `produk.$col` added.");
    } else {
        msg('skip', "Column `produk.$col` already exists.");
    }
}

// ─── 2. product_pricing table ───────────────────────────────────────────────
echo "\n═══ TABLE: product_pricing ═══\n";

if (!tableExists($conn, 'product_pricing')) {
    $conn->query("CREATE TABLE `product_pricing` (
        `id` int NOT NULL AUTO_INCREMENT,
        `product_id` int DEFAULT NULL,
        `sku_code` varchar(50) NOT NULL,
        `provider` varchar(50) NOT NULL,
        `aggregator` varchar(50) NOT NULL,
        `aggregator_sku` varchar(100) NOT NULL,
        `seller_name` varchar(100) NOT NULL DEFAULT 'default',
        `harga_modal` decimal(15,2) NOT NULL,
        `harga_jual` decimal(15,2) NOT NULL,
        `success_rate` decimal(5,1) DEFAULT '0.0',
        `is_active` enum('yes','no') DEFAULT 'yes',
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_pp_product_agg_seller` (`product_id`,`aggregator`,`seller_name`),
        KEY `idx_product_pricing_sku` (`sku_code`),
        KEY `idx_product_pricing_aggregator` (`aggregator`),
        KEY `idx_product_pricing_active` (`is_active`),
        CONSTRAINT `fk_pp_product` FOREIGN KEY (`product_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    msg('ok', "Table `product_pricing` created.");
} else {
    msg('skip', "Table `product_pricing` already exists.");
}

// Add missing columns
$ppCols = [
    'seller_name'   => "ADD COLUMN `seller_name` varchar(100) NOT NULL DEFAULT 'default' AFTER `aggregator_sku`",
    'success_rate'  => "ADD COLUMN `success_rate` decimal(5,1) DEFAULT '0.0' AFTER `harga_jual`",
    'is_active'     => "ADD COLUMN `is_active` enum('yes','no') DEFAULT 'yes' AFTER `success_rate`",
    'updated_at'    => "ADD COLUMN `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `is_active`",
];
foreach ($ppCols as $col => $alter) {
    if (!colExists($conn, 'product_pricing', $col)) {
        $conn->query("ALTER TABLE `product_pricing` $alter");
        msg('ok', "Column `product_pricing.$col` added.");
    } else {
        msg('skip', "Column `product_pricing.$col` already exists.");
    }
}

// Add FK constraint
$r = $conn->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'product_pricing'
                      AND CONSTRAINT_NAME = 'fk_pp_product'");
if (!$r || $r->num_rows == 0) {
    $conn->query("ALTER TABLE `product_pricing`
                  ADD CONSTRAINT `fk_pp_product`
                  FOREIGN KEY (`product_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE");
    msg('ok', "Foreign key `fk_pp_product` added.");
} else {
    msg('skip', "Foreign key `fk_pp_product` already exists.");
}

// Drop incorrect old unique key
foreach (['uk_sku_agg_seller'] as $idx) {
    if (indexExists($conn, 'product_pricing', $idx)) {
        $conn->query("ALTER TABLE `product_pricing` DROP INDEX `$idx`");
        msg('ok', "Index `$idx` dropped (was incorrect constraint).");
    } else {
        msg('skip', "Index `$idx` does not exist.");
    }
}

// Add correct indexes
foreach ([
    'uk_pp_product_agg_seller' => "ADD UNIQUE INDEX `uk_pp_product_agg_seller` (`product_id`, `aggregator`, `seller_name`)",
    'idx_product_pricing_sku'    => "ADD INDEX `idx_product_pricing_sku` (`sku_code`)",
    'idx_product_pricing_agg'    => "ADD INDEX `idx_product_pricing_aggregator` (`aggregator`)",
    'idx_product_pricing_active' => "ADD INDEX `idx_product_pricing_active` (`is_active`)",
] as $idx => $alter) {
    if (!indexExists($conn, 'product_pricing', $idx)) {
        $conn->query("ALTER TABLE `product_pricing` $alter");
        msg('ok', "Index `$idx` added.");
    } else {
        msg('skip', "Index `$idx` already exists.");
    }
}

// ─── 3. kategori_produk table ───────────────────────────────────────────────
echo "\n═══ TABLE: kategori_produk ═══\n";

if (!tableExists($conn, 'kategori_produk')) {
    $conn->query("CREATE TABLE `kategori_produk` (
        `id` int NOT NULL AUTO_INCREMENT,
        `nama_kategori` varchar(50) NOT NULL,
        `icon` varchar(50) NOT NULL,
        `warna` varchar(20) NOT NULL,
        `status` enum('active','inactive') DEFAULT 'active',
        `store_id` int DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    msg('ok', "Table `kategori_produk` created.");
} else {
    msg('skip', "Table `kategori_produk` already exists.");
}

$kategoriSeed = [
    ['id' => 1, 'nama_kategori' => 'Pulsa',          'icon' => 'fa-mobile-alt',           'warna' => 'blue',   'status' => 'active'],
    ['id' => 2, 'nama_kategori' => 'Kuota Internet',  'icon' => 'fa-wifi',                 'warna' => 'green',  'status' => 'active'],
    ['id' => 3, 'nama_kategori' => 'Token Listrik',   'icon' => 'fa-bolt',                 'warna' => 'yellow', 'status' => 'active'],
    ['id' => 4, 'nama_kategori' => 'Transfer Tunai',  'icon' => 'fa-money-bill-transfer',   'warna' => 'purple', 'status' => 'active'],
    ['id' => 5, 'nama_kategori' => 'Game',             'icon' => 'fa-gamepad',               'warna' => '#8b5cf6', 'status' => 'active'],
];
foreach ($kategoriSeed as $kat) {
    $r = $conn->query("SELECT 1 FROM `kategori_produk` WHERE `id` = {$kat['id']}");
    if ($r && $r->num_rows > 0) {
        $conn->query("UPDATE `kategori_produk` SET
                      `nama_kategori` = '{$kat['nama_kategori']}',
                      `icon` = '{$kat['icon']}',
                      `warna` = '{$kat['warna']}',
                      `status` = '{$kat['status']}'
                      WHERE `id` = {$kat['id']}");
        msg('skip', "Kategori [{$kat['id']}] {$kat['nama_kategori']} — updated.");
    } else {
        $cols = implode(', ', array_keys($kat));
        $vals = implode(', ', array_map('intval', $kat));
        // store_id & created_at have defaults, skip
        $conn->query("INSERT INTO `kategori_produk` ($cols) VALUES ($vals)");
        msg('ok', "Kategori [{$kat['id']}] {$kat['nama_kategori']} — inserted.");
    }
}

// ─── 4. aggregators table ───────────────────────────────────────────────────
echo "\n═══ TABLE: aggregators ═══\n";

if (!tableExists($conn, 'aggregators')) {
    $conn->query("CREATE TABLE `aggregators` (
        `id` int NOT NULL AUTO_INCREMENT,
        `name` varchar(50) NOT NULL,
        `label` varchar(100) NOT NULL,
        `api_url` text DEFAULT NULL,
        `username` varchar(255) DEFAULT NULL,
        `api_key` text DEFAULT NULL,
        `testing` enum('true','false') DEFAULT 'false',
        `is_active` enum('yes','no') DEFAULT 'yes',
        `is_default` enum('yes','no') DEFAULT 'no',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_aggregator_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    msg('ok', "Table `aggregators` created.");
} else {
    msg('skip', "Table `aggregators` already exists.");
}

$aggregatorSeed = [
    [
        'name' => 'digiflazz', 'label' => 'Digiflazz',
        'api_url' => 'https://api.digiflazz.com/v1',
        'username' => '', 'api_key' => '',
        'testing' => 'false', 'is_active' => 'yes', 'is_default' => 'yes',
    ],
    [
        'name' => 'bipassa', 'label' => 'Bipassa',
        'api_url' => 'https://api.bipassa.com/v1',
        'username' => '', 'api_key' => '',
        'testing' => 'false', 'is_active' => 'yes', 'is_default' => 'no',
    ],
];
foreach ($aggregatorSeed as $agg) {
    $r = $conn->query("SELECT 1 FROM `aggregators` WHERE `name` = '{$agg['name']}'");
    if ($r && $r->num_rows > 0) {
        $conn->query("UPDATE `aggregators` SET
                      `label` = '{$agg['label']}',
                      `api_url` = '{$agg['api_url']}',
                      `is_active` = '{$agg['is_active']}',
                      `is_default` = '{$agg['is_default']}'
                      WHERE `name` = '{$agg['name']}'");
        msg('skip', "Aggregator '{$agg['name']}' — updated.");
    } else {
        $cols = implode(', ', array_keys($agg));
        $vals = implode(', ', array_map(fn($v) => "'" . $conn->real_escape_string($v) . "'", $agg));
        $conn->query("INSERT INTO `aggregators` ($cols) VALUES ($vals)");
        msg('ok', "Aggregator '{$agg['name']}' — inserted.");
    }
}

// ─── 5. produk.source_aggregator FK cleanup ──────────────────────────────────
echo "\n═══ CLEANUP: produk ═══\n";
$r = $conn->query("SELECT id FROM produk WHERE source_aggregator = '' OR source_aggregator IS NULL LIMIT 10");
if ($r && $r->num_rows > 0) {
    $conn->query("UPDATE produk SET source_aggregator = 'digiflazz'
                  WHERE source_aggregator = '' OR source_aggregator IS NULL");
    msg('ok', "Fixed {$r->num_rows} produk with empty source_aggregator → 'digiflazz'.");
} else {
    msg('skip', "No produk with empty source_aggregator.");
}

// ─── 6. product_pricing seller_name fix ────────────────────────────────────
echo "\n═══ CLEANUP: product_pricing ═══\n";
$r = $conn->query("SELECT id FROM product_pricing WHERE seller_name = '' OR seller_name IS NULL LIMIT 10");
if ($r && $r->num_rows > 0) {
    $conn->query("UPDATE product_pricing SET seller_name = 'default'
                  WHERE seller_name = '' OR seller_name IS NULL");
    msg('ok', "Fixed {$r->num_rows} product_pricing with empty seller_name → 'default'.");
} else {
    msg('skip', "No product_pricing with empty seller_name.");
}

// ─── Summary ─────────────────────────────────────────────────────────────────
echo "\n";
echo "══════════════════════════════════════════\n";
echo "  DONE — All fixes applied.\n";
echo "══════════════════════════════════════════\n";

$conn->close();

if (!$isCli) {
    echo "</pre>
    <p><a href='kelola_produk.php'>→ Buka Kelola Produk</a></p>
    </div></body></html>";
}
