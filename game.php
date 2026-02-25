<?php
require_once 'config.php';
cekLogin();
$conn = koneksi();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$_SESSION['saldo'] = getSaldo($user_id);

// Dummy game data - Codashop style: just User ID (Zone ID for ML)
$games = [
    ['id' => 'ml', 'name' => 'Mobile Legends', 'color' => '#3653D8', 'needs_zone_id' => true,
     'products' => [
         ['id' => 1, 'name' => '5 Diamonds', 'nominal' => 5, 'price' => 3500],
         ['id' => 2, 'name' => '12 Diamonds', 'nominal' => 12, 'price' => 7500],
         ['id' => 3, 'name' => '28 Diamonds', 'nominal' => 28, 'price' => 15000],
         ['id' => 4, 'name' => '56 Diamonds', 'nominal' => 56, 'price' => 28000],
         ['id' => 5, 'name' => '84 Diamonds', 'nominal' => 84, 'price' => 42000],
         ['id' => 6, 'name' => '170 Diamonds', 'nominal' => 170, 'price' => 75000],
         ['id' => 7, 'name' => '257 Diamonds', 'nominal' => 257, 'price' => 110000],
         ['id' => 8, 'name' => '429 Diamonds', 'nominal' => 429, 'price' => 180000],
         ['id' => 9, 'name' => '514 Diamonds', 'nominal' => 514, 'price' => 215000],
         ['id' => 10, 'name' => '772 Diamonds', 'nominal' => 772, 'price' => 320000],
         ['id' => 11, 'name' => '1030 Diamonds', 'nominal' => 1030, 'price' => 420000],
         ['id' => 12, 'name' => '1545 Diamonds', 'nominal' => 1545, 'price' => 620000],
     ]],
    ['id' => 'ff', 'name' => 'Free Fire', 'color' => '#FF5272', 'needs_zone_id' => false,
     'products' => [
         ['id' => 21, 'name' => '5 Diamonds', 'nominal' => 5, 'price' => 1500],
         ['id' => 22, 'name' => '12 Diamonds', 'nominal' => 12, 'price' => 3500],
         ['id' => 23, 'name' => '50 Diamonds', 'nominal' => 50, 'price' => 12000],
         ['id' => 24, 'name' => '70 Diamonds', 'nominal' => 70, 'price' => 16000],
         ['id' => 25, 'name' => '100 Diamonds', 'nominal' => 100, 'price' => 22000],
         ['id' => 26, 'name' => '140 Diamonds', 'nominal' => 140, 'price' => 30000],
         ['id' => 27, 'name' => '200 Diamonds', 'nominal' => 200, 'price' => 42000],
         ['id' => 28, 'name' => '280 Diamonds', 'nominal' => 280, 'price' => 58000],
         ['id' => 29, 'name' => '500 Diamonds', 'nominal' => 500, 'price' => 99000],
         ['id' => 30, 'name' => '720 Diamonds', 'nominal' => 720, 'price' => 140000],
         ['id' => 31, 'name' => '1000 Diamonds', 'nominal' => 1000, 'price' => 190000],
         ['id' => 32, 'name' => '2000 Diamonds', 'nominal' => 2000, 'price' => 370000],
     ]],
    ['id' => 'pubgm', 'name' => 'PUBG Mobile', 'color' => '#FF8B00', 'needs_zone_id' => false,
     'products' => [
         ['id' => 41, 'name' => 'UC 60', 'nominal' => 60, 'price' => 12000],
         ['id' => 42, 'name' => 'UC 120', 'nominal' => 120, 'price' => 23000],
         ['id' => 43, 'name' => 'UC 180', 'nominal' => 180, 'price' => 34000],
         ['id' => 44, 'name' => 'UC 300', 'nominal' => 300, 'price' => 55000],
         ['id' => 45, 'name' => 'UC 600', 'nominal' => 600, 'price' => 105000],
         ['id' => 46, 'name' => 'UC 900', 'nominal' => 900, 'price' => 155000],
         ['id' => 47, 'name' => 'UC 1200', 'nominal' => 1200, 'price' => 205000],
         ['id' => 48, 'name' => 'UC 2400', 'nominal' => 2400, 'price' => 405000],
     ]],
     ['id' => 'genshin', 'name' => 'Genshin Impact', 'color' => '#4B9FE8', 'needs_zone_id' => false,
     'products' => [
         ['id' => 51, 'name' => '60 Genesis Crystals', 'nominal' => 60, 'price' => 15000],
         ['id' => 52, 'name' => '300 Genesis Crystals', 'nominal' => 300, 'price' => 65000],
         ['id' => 53, 'name' => '980 Genesis Crystals', 'nominal' => 980, 'price' => 195000],
         ['id' => 54, 'name' => '1980 Genesis Crystals', 'nominal' => 1980, 'price' => 385000],
         ['id' => 55, 'name' => '3280 Genesis Crystals', 'nominal' => 3280, 'price' => 625000],
         ['id' => 56, 'name' => '6480 Genesis Crystals', 'nominal' => 6480, 'price' => 1220000],
     ]],
    ['id' => 'hok', 'name' => 'Honor of Kings', 'color' => '#AE6A24', 'needs_zone_id' => false,
     'products' => [
         ['id' => 61, 'name' => '60 Points', 'nominal' => 60, 'price' => 12000],
         ['id' => 62, 'name' => '300 Points', 'nominal' => 300, 'price' => 55000],
         ['id' => 63, 'name' => '980 Points', 'nominal' => 980, 'price' => 175000],
         ['id' => 64, 'name' => '1980 Points', 'nominal' => 1980, 'price' => 350000],
     ]],
    ['id' => 'valo', 'name' => 'Valorant', 'color' => '#FF4565', 'needs_zone_id' => false,
     'products' => [
         ['id' => 81, 'name' => '125 VP', 'nominal' => 125, 'price' => 25000],
         ['id' => 82, 'name' => '375 VP', 'nominal' => 375, 'price' => 70000],
         ['id' => 83, 'name' => '700 VP', 'nominal' => 700, 'price' => 125000],
         ['id' => 84, 'name' => '1375 VP', 'nominal' => 1375, 'price' => 240000],
         ['id' => 85, 'name' => '2400 VP', 'nominal' => 2400, 'price' => 410000],
         ['id' => 86, 'name' => '5000 VP', 'nominal' => 5000, 'price' => 830000],
     ]],
    ['id' => 'codm', 'name' => 'Call of Duty Mobile', 'color' => '#FFD2D2', 'needs_zone_id' => false,
     'products' => [
         ['id' => 71, 'name' => '80 CP', 'nominal' => 80, 'price' => 15000],
         ['id' => 72, 'name' => '420 CP', 'nominal' => 420, 'price' => 75000],
         ['id' => 73, 'name' => '880 CP', 'nominal' => 880, 'price' => 150000],
         ['id' => 74, 'name' => '2400 CP', 'nominal' => 2400, 'price' => 390000],
     ]],
    ['id' => 'fifa', 'name' => 'FIFA Mobile', 'color' => '#E2CC71', 'needs_zone_id' => false,
     'products' => [
         ['id' => 91, 'name' => '100 Coins', 'nominal' => 100, 'price' => 15000],
         ['id' => 92, 'name' => '500 Coins', 'nominal' => 500, 'price' => 65000],
         ['id' => 93, 'name' => '1000 Coins', 'nominal' => 1000, 'price' => 125000],
         ['id' => 94, 'name' => '2500 Coins', 'nominal' => 2500, 'price' => 295000],
     ]],
];

$popularGames = ['ml', 'pubgm', 'ff', 'genshin'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Rate limiting: max 10 purchases per minute
    if (!checkRateLimit('purchase_game', 10, 60)) {
        setAlert('error', 'Terlalu banyak permintaan. Silakan tunggu sebentar.');
        header("Location: game.php"); exit;
    }
    
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: game.php"); exit;
    }

    $game_id = $_POST['game_id'] ?? '';
    $player_id = trim($_POST['player_id'] ?? '');
    $zone_id = $_POST['zone_id'] ?? ''; // Codashop style - only for ML
    $produk_id = intval($_POST['produk_id'] ?? 0);

    $selectedGame = null;
    $selectedProduct = null;

    foreach ($games as $game) {
        if ($game['id'] == $game_id) {
            $selectedGame = $game;
            foreach ($game['products'] as $product) {
                if ($product['id'] == $produk_id) {
                    $selectedProduct = $product;
                    break;
                }
            }
            break;
        }
    }

    if (!$selectedGame || empty($player_id)) {
        setAlert('error', 'Masukkan Player ID yang valid!');
    } elseif ($selectedGame['needs_zone_id'] && empty($zone_id)) {
        setAlert('error', 'Masukkan Zone ID!');
    } elseif (!$selectedProduct) {
        setAlert('error', 'Pilih nominal yang valid!');
    } else {
        $saldo = getSaldo($user_id);
        $harga = $selectedProduct['price'];
        if ($saldo < $harga) {
            setAlert('error', 'Saldo tidak mencukupi! Silakan deposit terlebih dahulu.');
        } else {
            $invoice = generateInvoice();
            $saldo_sebelum = $saldo;
            $saldo_sesudah = $saldo - $harga;
            $keterangan = "Top up {$selectedGame['name']} - {$selectedProduct['name']}" . ($zone_id ? " (Zone: {$zone_id})" : "");

            $stmt = $conn->prepare("INSERT INTO transaksi (user_id, no_invoice, jenis_transaksi, no_tujuan, customer_id, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan) VALUES (?, ?, 'game', ?, ?, ?, ?, 0, ?, ?, ?, 'success', ?)");
            $stmt->bind_param("isssddddd s", $user_id, $invoice, $player_id, $zone_id ?: $player_id, $selectedProduct['nominal'], $harga, $harga, $saldo_sebelum, $saldo_sesudah, $keterangan);

            if ($stmt->execute()) {
                updateSaldo($user_id, $harga, 'kurang');
                $_SESSION['saldo'] = getSaldo($user_id);
                $_SESSION['game_result'] = [
                    'invoice' => $invoice,
                    'game' => $selectedGame['name'],
                    'product' => $selectedProduct['name'],
                    'player_id' => $player_id,
                    'harga' => $harga
                ];
                setAlert('success', 'Pembelian berhasil! Invoice: ' . $invoice);
            } else {
                setAlert('error', 'Terjadi kesalahan. Silakan coba lagi.');
            }
        }
    }
    header("Location: game.php"); exit;
}

$gameResult = isset($_SESSION['game_result']) ? $_SESSION['game_result'] : null;
unset($_SESSION['game_result']);

// ═══ Layout Variables ═══
$pageTitle   = 'Top Up Game';
$pageIcon    = 'fas fa-gamepad';
$pageDesc    = 'Diamonds, UC, dan lainnya';
$currentPage = 'game';
$alert       = getAlert();

require_once 'layout.php';
?>

<!-- ═══════════════════════════════════════════
     GAME PAGE - Custom Styles
     ═══════════════════════════════════════════ -->
<style>
/* ── Game Cards ── */
.game-card {
    transition: all 0.2s ease;
    cursor: pointer;
    border: 2px solid transparent;
    border-radius: 16px;
    overflow: hidden;
    position: relative;
}
.game-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.game-card.selected {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99,83,216,0.15);
}
.game-card .game-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 20px;
    margin-bottom: 8px;
    transition: transform 0.2s;
}
.game-card:hover .game-icon { transform: scale(1.05); }
.game-card .game-name {
    font-size: 12px; font-weight: 500;
    color: var(--text-secondary);
    text-align: center;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* ── Popular Games (horizontal scroll) ── */
.popular-scroll {
    display: flex; gap: 12px;
    overflow-x: auto; padding-bottom: 8px;
    scrollbar-width: none;
}
.popular-scroll::-webkit-scrollbar { display: none; }
.popular-card {
    flex-shrink: 0;
    width: 80px;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 12px 8px;
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 2px solid transparent;
    text-align: center;
}
.popular-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.popular-card.selected { border-color: var(--primary); }

/* ── All Games Grid ── */
.games-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}
@media (min-width: 768px) { .games-grid { grid-template-columns: repeat(6, 1fr); } }
@media (min-width: 1024px) { .games-grid { grid-template-columns: repeat(8, 1fr); } }

.all-game-card {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 12px 8px;
    border-radius: 16px; aspect-ratio: 1;
    cursor: pointer; transition: all 0.2s ease;
    border: 2px solid transparent;
}
.all-game-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.all-game-card.selected { border-color: var(--primary); background: var(--primary-50); }

/* ── Section Cards ── */
.section-card {
    background: white; border: 1px solid var(--border);
    border-radius: 12px; padding: 20px;
    margin-bottom: 20px;
    animation: fadeUp 0.4s ease;
}
.section-title {
    font-size: 14px; font-weight: 600; color: var(--text);
    margin-bottom: 12px;
    display: flex; align-items: center; gap: 8px;
}

/* ── Selected Game Display ── */
.selected-game-display {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 16px;
}
.selected-game-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 22px; flex-shrink: 0;
}

/* ── Zone ID ── */
/* ── Product Cards ── */
.product-card {
    background: white; border: 2px solid var(--border);
    border-radius: 12px; padding: 16px;
    cursor: pointer; transition: all 0.2s ease;
    position: relative;
}
.product-card:hover {
    border-color: var(--primary-light);
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.product-card.selected {
    border-color: var(--primary);
    background: var(--primary-50);
}
.product-card .check-icon {
    position: absolute; top: 8px; right: 8px;
    width: 20px; height: 20px;
    background: var(--primary); color: white;
    border-radius: 50%; font-size: 10px;
    display: none; align-items: center; justify-content: center;
}
.product-card.selected .check-icon { display: flex; }
.product-name { font-weight: 600; color: var(--text); margin-bottom: 4px; }
.product-price { font-weight: 700; color: var(--primary); }

/* ── Product Grid ── */
.product-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
@media (min-width: 768px) { .product-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 1024px) { .product-grid { grid-template-columns: repeat(4, 1fr); } }

/* ── Sticky Summary ── */
.sticky-summary {
    position: fixed; bottom: 20px;
    left: 50%; transform: translateX(-50%);
    width: calc(100% - 48px); max-width: 600px;
    background: white; border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: 0 -4px 24px rgba(0,0,0,0.1);
    padding: 16px; z-index: 100;
    display: none;
    animation: slideUp 0.35s ease;
}
.sticky-summary.show { display: block; }
@keyframes slideUp {
    from { opacity: 0; transform: translateX(-50%) translateY(20px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
}

/* ── Search ── */
.search-input {
    width: 100%; padding: 10px 14px 10px 40px;
    border: 1px solid var(--border); border-radius: 10px;
    font-size: 14px; outline: none;
    transition: all 0.2s;
}
.search-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99,83,216,0.08);
}

/* ── Success Card ── */
.success-card {
    background: white; border: 1px solid var(--border);
    border-radius: 16px; padding: 32px 20px;
    text-align: center; margin-bottom: 24px;
    animation: fadeUp 0.5s ease;
}
.success-icon {
    width: 80px; height: 80px;
    background: #dcfce7; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
    color: #16a34a; font-size: 36px;
}
.success-details {
    background: var(--bg); border-radius: 12px;
    padding: 16px; text-align: left;
    max-width: 400px; margin: 24px auto 0;
}
.success-detail-row {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 8px; font-size: 14px;
    padding: 6px 0; border-bottom: 1px solid var(--border);
}
.success-detail-row:last-child { border-bottom: none; }
.detail-label { color: var(--text-secondary); }
.detail-value { font-weight: 500; color: var(--text); text-align: right; }

/* ── Toast ── */
.toast-notification {
    position: fixed; top: 80px; right: 20px; z-index: 9999;
    padding: 14px 18px; border-radius: 10px;
    display: flex; align-items: center; gap: 10px;
    font-size: 14px; min-width: 280px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    animation: slideInRight 0.4s ease;
}
.toast-notification.success { background: #dcfce7; color: #166534; }
.toast-notification.error { background: #fee2e2; color: #991b1b; }
@keyframes slideInRight {
    from { opacity: 0; transform: translateX(30px); }
    to { opacity: 1; transform: translateX(0); }
}

/* ── Responsive ── */
@media (max-width: 768px) {
    .sticky-summary {
        bottom: 0; left: 0; right: 0;
        transform: none; width: 100%;
        max-width: 100%; border-radius: 16px 16px 0 0;
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
}
</style>

<!-- ═══════════════════════════════════════════
     GAME PAGE - Content
     ═══════════════════════════════════════════ -->

<!-- Success Modal -->
<?php if ($gameResult): ?>
<div class="success-card">
    <div class="success-icon"><i class="fas fa-check"></i></div>
    <h2 style="font-size:22px;font-weight:700;margin-bottom:8px;">Pembelian Berhasil!</h2>
    <p style="color:var(--text-secondary);margin-bottom:8px;">Invoice: <?= $gameResult['invoice'] ?></p>

    <div class="success-details">
        <div class="success-detail-row">
            <span class="detail-label">Game</span>
            <span class="detail-value"><?= $gameResult['game'] ?></span>
        </div>
        <div class="success-detail-row">
            <span class="detail-label">Item</span>
            <span class="detail-value"><?= $gameResult['product'] ?></span>
        </div>
        <div class="success-detail-row">
            <span class="detail-label">Player ID</span>
            <span class="detail-value" style="font-family:monospace;"><?= $gameResult['player_id'] ?></span>
        </div>
        <div class="success-detail-row">
            <span class="detail-label">Total</span>
            <span class="detail-value" style="color:var(--primary);font-weight:700;">Rp <?= number_format($gameResult['harga'], 0, ',', '.') ?></span>
        </div>
    </div>

    <a href="game.php" style="display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:white;padding:10px 24px;border-radius:10px;text-decoration:none;font-weight:600;margin-top:20px;transition:all 0.2s;">
        <i class="fas fa-redo"></i> Beli Lagi
    </a>
</div>
<?php else: ?>

<form method="POST" id="formGame">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <input type="hidden" name="game_id" id="gameId" value="">
    <input type="hidden" name="produk_id" id="produkId" value="">
    <input type="hidden" name="zone_id" id="zoneIdValue" value="">

    <!-- Search -->
    <div class="card" style="padding:16px;margin-bottom:16px;">
        <div style="position:relative;">
            <i class="fas fa-search" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
            <input type="text" class="search-input" id="gameSearch" placeholder="Cari game..." onkeyup="filterGames()">
        </div>
    </div>

    <!-- Popular Games -->
    <div style="margin-bottom:16px;">
        <h3 style="font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:12px;">Populer</h3>
        <div class="popular-scroll" id="popularGames">
            <?php foreach ($popularGames as $popId): ?>
            <?php foreach ($games as $g): ?>
            <?php if ($g['id'] == $popId): ?>
            <button type="button" class="popular-card game-card"
                    onclick="selectGame('<?= $g['id'] ?>')"
                    data-game-id="<?= $g['id'] ?>"
                    data-game-name="<?= htmlspecialchars($g['name']) ?>"
                    data-game-color="<?= $g['color'] ?>"
                    style="background:<?= $g['color'] ?>20;border-color:<?= $g['color'] ?>40">
                <div class="game-icon" style="background:<?= $g['color'] ?>;">
                    <i class="fas fa-gamepad"></i>
                </div>
                <span class="game-name"><?= $g['name'] ?></span>
            </button>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- All Games -->
    <div class="card" style="padding:16px;margin-bottom:20px;">
        <h3 style="font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:12px;">Semua Game</h3>
        <div class="games-grid" id="allGames">
            <?php foreach ($games as $game): ?>
            <button type="button" class="all-game-card game-card"
                    onclick="selectGame('<?= $game['id'] ?>')"
                    data-game-id="<?= $game['id'] ?>"
                    data-game-name="<?= htmlspecialchars($game['name']) ?>"
                    data-game-color="<?= $game['color'] ?>"
                    data-game-needs-zone-id="<?= $game['needs_zone_id'] ?? false ?>"
                    style="background:<?= $game['color'] ?>15;border-color:<?= $game['color'] ?>">
                <div class="game-icon" style="background:<?= $game['color'] ?>;width:40px;height:40px;border-radius:10px;">
                    <i class="fas fa-gamepad" style="font-size:16px;"></i>
                </div>
                <span class="game-name" style="font-size:11px;margin-top:6px;"><?= $game['name'] ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Player ID Section (shown after game selection) -->
    <div class="section-card" id="playerIdSection" style="display:none;">
        <div class="selected-game-display">
            <div class="selected-game-icon" id="selectedGameIcon" style="background:var(--primary);">
                <i class="fas fa-gamepad"></i>
            </div>
            <div>
                <h3 id="selectedGameName" style="font-weight:600;color:var(--text);">-</h3>
                <p style="font-size:13px;color:var(--text-secondary);">Masukkan Player ID</p>
            </div>
        </div>

        <!-- Zone ID (only for ML) -->
        <div id="zoneIdSection" style="display:none;" class="mb-4">
            <label style="display:block;font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:8px;">
                <i class="fas fa-id-card-alt"></i> Zone ID
            </label>
            <input type="text" id="zoneId" name="zone_id"
                   class="search-input" style="padding-left:14px;"
                   placeholder="Masukkan Zone ID...">
            <p style="font-size:12px;color:var(--text-muted);margin-top:6px;">
                *) Cara melihat: Profil → Basic Info → Zone ID
            </p>
        </div>

        <!-- Player ID Input -->
        <div>
            <label style="display:block;font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:8px;">Player ID</label>
            <input type="text" id="playerId" name="player_id"
                   class="search-input" style="padding-left:14px;"
                   placeholder="Masukkan Player ID..."
                   oninput="validatePlayerId()">
            <p id="playerIdHint" style="font-size:12px;color:var(--text-muted);margin-top:6px;">
                *) ditemukan di profil karakter game
            </p>
        </div>
    </div>

    <!-- Product Selection (shown after player ID) -->
    <div class="section-card" id="productSection" style="display:none;">
        <h3 class="section-title">Pilih Nominal</h3>
        <div class="product-grid" id="productGrid">
            <!-- Populated by JS -->
        </div>
    </div>

    <!-- Sticky Summary -->
    <div class="sticky-summary" id="summarySection">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <div>
                <p style="font-size:12px;color:var(--text-secondary);">Item</p>
                <p id="selectedProductName" style="font-weight:600;color:var(--text);">-</p>
            </div>
            <div style="text-align:right;">
                <p style="font-size:12px;color:var(--text-secondary);">Total</p>
                <p id="totalPrice" style="font-size:20px;font-weight:700;color:var(--primary);">Rp 0</p>
            </div>
        </div>
        <div style="display:flex;gap:12px;">
            <button type="button" onclick="resetSelection()"
                    style="flex:1;padding:10px;border:1px solid var(--border);border-radius:10px;background:white;color:var(--text-secondary);cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;gap:6px;">
                <i class="fas fa-times"></i> Batal
            </button>
            <button type="submit"
                    style="flex:1;padding:10px;border:none;border-radius:10px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;font-weight:600;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 2px 8px rgba(99,83,216,0.3);">
                <i class="fas fa-shopping-cart"></i> Beli
            </button>
        </div>
    </div>
</form>
<?php endif; ?>

<!-- ═══════════════════════════════════════════
     GAME PAGE - JavaScript
     ═══════════════════════════════════════════ -->
<script>
// Game data from PHP
const games = <?= json_encode(array_map(function($g) {
    return [
        'id' => $g['id'], 'name' => $g['name'], 'color' => $g['color'],
        'needs_zone_id' => $g['needs_zone_id'] ?? false,
        'products' => array_map(function($p) {
            return ['id' => $p['id'], 'name' => $p['name'], 'price' => $p['price']];
        }, $g['products'])
    ];
}, $games)) ?>;

let selectedGame = null;
let selectedProduct = null;

// ══════════ SELECT GAME ══════════
function selectGame(gameId) {
    selectedGame = games.find(g => g.id === gameId);
    if (!selectedGame) return;

    // Update game selection UI
    document.querySelectorAll('.game-card').forEach(card => {
        card.classList.remove('selected');
        if (card.dataset.gameId === gameId) card.classList.add('selected');
    });

    // Update hidden input
    document.getElementById('gameId').value = gameId;

    // Show player ID section
    const playerIdSection = document.getElementById('playerIdSection');
    playerIdSection.style.display = 'block';
    playerIdSection.classList.add('fade-in');

    // Update selected game display
    document.getElementById('selectedGameName').textContent = selectedGame.name;
    const icon = document.getElementById('selectedGameIcon');
    icon.style.background = selectedGame.color;

    // Show/hide Zone ID section (only for ML)
    const zoneIdSection = document.getElementById('zoneIdSection');
    if (selectedGame.needs_zone_id) {
        zoneIdSection.style.display = 'block';
    } else {
        zoneIdSection.style.display = 'none';
        document.getElementById('zoneId').value = '';
    }
    document.getElementById('zoneIdValue').value = '';

    // Reset product selection
    selectedProduct = null;
    document.getElementById('produkId').value = '';
    document.getElementById('productSection').style.display = 'none';
    document.getElementById('summarySection').classList.remove('show');
    document.getElementById('playerId').value = '';

    // Scroll to player ID section
    playerIdSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ══════════ VALIDATE PLAYER ID ══════════
function validatePlayerId() {
    const playerId = document.getElementById('playerId').value.trim();
    if (playerId.length >= 3) {
        const productSection = document.getElementById('productSection');
        productSection.style.display = 'block';
        productSection.classList.add('fade-in');

        // Populate products
        const productGrid = document.getElementById('productGrid');
        productGrid.innerHTML = selectedGame.products.map(p => `
            <div class="product-card" onclick="selectProduct(${p.id}, '${p.name}', ${p.price})">
                <div class="check-icon"><i class="fas fa-check" style="font-size:10px;"></i></div>
                <p class="product-name">${p.name}</p>
                <p class="product-price">${formatRupiah(p.price)}</p>
            </div>
        `).join('');

        productSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// ══════════ SELECT PRODUCT ══════════
function selectProduct(produkId, productName, price) {
    selectedProduct = { id: produkId, name: productName, price: price };

    // Update UI
    document.querySelectorAll('.product-card').forEach(card => card.classList.remove('selected'));
    event.currentTarget.classList.add('selected');

    // Update hidden input
    document.getElementById('produkId').value = produkId;

    // Update summary
    document.getElementById('selectedProductName').textContent = productName;
    document.getElementById('totalPrice').textContent = formatRupiah(price) + ' Rp';

    // Show summary
    document.getElementById('summarySection').classList.add('show');

    if (window.innerWidth < 768) {
        document.getElementById('summarySection').scrollIntoView({ behavior: 'smooth', block: 'end' });
    }
}

// ══════════ RESET ══════════
function resetSelection() {
    selectedGame = null;
    selectedProduct = null;

    document.getElementById('gameId').value = '';
    document.getElementById('produkId').value = '';
    document.getElementById('zoneIdValue').value = '';
    document.getElementById('playerId').value = '';

    document.querySelectorAll('.game-card').forEach(card => card.classList.remove('selected'));
    document.querySelectorAll('.product-card').forEach(card => card.classList.remove('selected'));

    document.getElementById('playerIdSection').style.display = 'none';
    document.getElementById('productSection').style.display = 'none';
    document.getElementById('summarySection').classList.remove('show');
}

// ══════════ SEARCH ══════════
function filterGames() {
    const search = document.getElementById('gameSearch').value.toLowerCase();
    document.querySelectorAll('[data-game-id]').forEach(card => {
        const name = card.dataset.gameName?.toLowerCase() || '';
        card.style.display = (name.includes(search) || search === '') ? '' : 'none';
    });
}

// ══════════ FORMAT ══════════
function formatRupiah(num) {
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// ══════════ FORM VALIDATION ══════════
document.getElementById('formGame')?.addEventListener('submit', function(e) {
    const gameId = document.getElementById('gameId').value;
    const playerId = document.getElementById('playerId').value.trim();
    const zoneId = document.getElementById('zoneId').value.trim();
    const produkId = document.getElementById('produkId').value;

    if (!gameId) { e.preventDefault(); alert('Pilih game terlebih dahulu!'); return; }
    if (!playerId || playerId.length < 3) { e.preventDefault(); alert('Masukkan Player ID yang valid!'); return; }
    if (selectedGame?.needs_zone_id && !zoneId) { e.preventDefault(); alert('Masukkan Zone ID!'); return; }
    if (!produkId) { e.preventDefault(); alert('Pilih nominal terlebih dahulu!'); return; }

    // Set zone_id value
    document.getElementById('zoneIdValue').value = zoneId;

    const confirmMsg = `Konfirmasi Pembelian\n\nGame: ${selectedGame.name}\nPlayer ID: ${playerId}${zoneId ? '\nZone ID: ' + zoneId : ''}\nItem: ${selectedProduct.name}\nTotal: ${formatRupiah(selectedProduct.price)}\n\nLanjutkan?`;
    if (!confirm(confirmMsg)) { e.preventDefault(); }
});
</script>

<?php
require_once 'layout_footer.php';
$conn->close();
?>