<?php
require_once 'config.php';
cekLogin();
$conn = koneksi();
$user_id = $_SESSION['user_id'];
$_SESSION['saldo'] = getSaldo($user_id);

// ═══ Helper Functions ═══
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(log($bytes ? $bytes : 1) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function getKategoriKuota($nama) {
    $nama = strtolower($nama);
    if (preg_match('/(daily|harian|1\s*hari)/', $nama)) return 'harian';
    if (preg_match('/(mingguan|weekly|7\s*hari)/', $nama)) return 'mingguan';
    if (preg_match('/(bulan|30\s*hari|business|30hr)/', $nama)) return 'bulanan';
    if (preg_match('/(gaming|game)/', $nama)) return 'gaming';
    if (preg_match('/(streaming|youtube|netflix|spotify|disney|vision)/', $nama)) return 'streaming';
    if (preg_match('/(sosmed|social|instagram|fb|facebook|twitter|tiktok|whatsapp|wa|telegram)/', $nama)) return 'sosmed';
    if (preg_match('/(malam|night|midnight|0\s*30)/', $nama)) return 'malam';
    return 'lainnya';
}

$daftarKategori = [
    'harian'    => ['label' => 'Harian',    'icon' => 'fa-sun'],
    'mingguan'  => ['label' => 'Mingguan',  'icon' => 'fa-calendar-week'],
    'bulanan'   => ['label' => 'Bulanan',   'icon' => 'fa-calendar-alt'],
    'gaming'    => ['label' => 'Gaming',    'icon' => 'fa-gamepad'],
    'streaming' => ['label' => 'Streaming', 'icon' => 'fa-play-circle'],
    'sosmed'    => ['label' => 'Sosmed',    'icon' => 'fa-share-alt'],
    'malam'     => ['label' => 'Malam',     'icon' => 'fa-moon'],
    'lainnya'   => ['label' => 'Lainnya',   'icon' => 'fa-ellipsis-h'],
];

// ═══ Ambil Produk Kuota ═══
$produkKuota = $conn->query("SELECT * FROM produk WHERE kategori_id = 2 AND status = 'active' ORDER BY provider, harga_jual");
$produkByProvider = [];
while ($row = $produkKuota->fetch_assoc()) {
    $produkByProvider[$row['provider']][] = $row;
}

// ═══ Proses Pembelian ═══
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Rate limiting: max 10 purchases per minute
    if (!checkRateLimit('purchase_kuota', 10, 60)) {
        setAlert('error', 'Terlalu banyak permintaan. Silakan tunggu sebentar.');
        header("Location: kuota.php"); exit;
    }
    
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: kuota.php"); exit;
    }
    $no_hp = preg_replace('/[^0-9]/', '', $_POST['no_hp'] ?? '');
    $produk_id = intval($_POST['produk_id'] ?? 0);

    if (empty($no_hp) || strlen($no_hp) < 10 || strlen($no_hp) > 15) {
        setAlert('error', 'Nomor HP tidak valid! (10-15 digit)');
    } elseif ($produk_id == 0 || $produk_id > 100000) {
        setAlert('error', 'Pilih paket data yang valid!');
    } else {
        $stmt = $conn->prepare("SELECT * FROM produk WHERE id = ?");
        $stmt->bind_param("i", $produk_id);
        $stmt->execute();
        $produk = $stmt->get_result()->fetch_assoc();
        if (!$produk) {
            setAlert('error', 'Produk tidak ditemukan!');
        } else {
            $harga = $produk['harga_jual'];
            $saldo = getSaldo($user_id);
            if ($saldo < $harga) {
                setAlert('error', 'Saldo tidak mencukupi! Silakan deposit terlebih dahulu.');
            } else {
                $invoice = generateInvoice();
                $saldo_sebelum = $saldo;
                $saldo_sesudah = $saldo - $harga;
                $stmt = $conn->prepare("INSERT INTO transaksi (user_id, produk_id, invoice_no, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan) VALUES (?, ?, ?, 'kuota', ?, ?, ?, 0, ?, ?, ?, 'success', 'Pembelian paket data berhasil')");
                $stmt->bind_param("iissddddd", $user_id, $produk_id, $invoice, $no_hp, $produk['nominal'], $harga, $harga, $saldo_sebelum, $saldo_sesudah);
                if ($stmt->execute()) {
                    updateSaldo($user_id, $harga, 'kurang');
                    $_SESSION['saldo'] = getSaldo($user_id);
                    setAlert('success', 'Pembelian paket data berhasil! Invoice: ' . $invoice);
                } else {
                    setAlert('error', 'Terjadi kesalahan. Silakan coba lagi.');
                }
            }
        }
    }
    header("Location: kuota.php"); exit;
}

// ═══ Layout Variables ═══
$pageTitle   = 'Paket Data';
$pageIcon    = 'fas fa-wifi';
$pageDesc    = 'Pilih paket data sesuai kebutuhan Anda';
$currentPage = 'kuota';
$alert       = getAlert();

include 'layout.php';
?>

<!-- ═══════════════════════════════════════════
     KUOTA PAGE - Custom Styles
     ═══════════════════════════════════════════ -->
<style>
/* ── Phone Input ── */
.phone-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}
.phone-card-title {
    font-size: 14px; font-weight: 600; color: var(--text);
    display: flex; align-items: center; gap: 8px; margin-bottom: 14px;
}
.phone-card-title i { color: var(--primary); font-size: 16px; }
.phone-group { position: relative; }
.phone-group .input-icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: 16px; pointer-events: none;
    transition: color 0.2s ease;
}
.phone-group:focus-within .input-icon { color: var(--primary); }
.phone-field {
    width: 100%; padding: 12px 12px 12px 44px;
    border: 2px solid var(--border); border-radius: 12px;
    font-size: 15px; color: var(--text); background: var(--bg);
    outline: none; transition: all 0.2s ease;
}
.phone-field:focus {
    border-color: var(--primary); background: var(--surface);
    box-shadow: 0 0 0 3px rgba(99,83,216,0.08);
}
.phone-field::placeholder { color: var(--text-muted); }
.provider-hint {
    display: flex; align-items: center; gap: 6px;
    margin-top: 10px; font-size: 13px; color: var(--text-muted); min-height: 20px;
}
.provider-detected { font-weight: 600; color: var(--primary); }

/* ── Provider Section ── */
.provider-section { margin-bottom: 28px; display: none; }
.provider-header {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 12px; padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}
.provider-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 700; font-size: 14px; flex-shrink: 0;
}
.provider-icon.telkomsel { background: linear-gradient(135deg, #cd6262, #9b91b1); }
.provider-icon.indosat   { background: linear-gradient(135deg, #5fe9b0, #9d7706); }
.provider-icon.xl        { background: linear-gradient(135deg, #e0a59e, #02847c); }
.provider-icon.tri       { background: linear-gradient(135deg, #ce8499, #eb81d5); }
.provider-icon.axis      { background: linear-gradient(135deg, #b8c5a3, #7ca3de); }
.provider-icon.smartfren { background: linear-gradient(135deg, #cd4444, #6262cd); }
.provider-info { flex: 1; }
.provider-name { font-size: 15px; font-weight: 600; color: var(--text); }
.provider-count { font-size: 12px; color: var(--text-muted); }

/* ── Filter Tabs ── */
.filter-tabs {
    display: flex; gap: 8px; overflow-x: auto;
    padding-bottom: 12px; margin-bottom: 12px;
    scrollbar-width: none;
}
.filter-tabs::-webkit-scrollbar { display: none; }
.filter-tab {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 12px; border: 1.5px solid var(--border);
    border-radius: 999px; background: var(--surface);
    color: var(--text-secondary); font-size: 12px; font-weight: 500;
    cursor: pointer; white-space: nowrap; flex-shrink: 0;
    transition: all 0.2s ease;
}
.filter-tab:hover { border-color: var(--primary); color: var(--primary); }
.filter-tab.active {
    background: var(--primary); border-color: var(--primary); color: white;
}
.filter-tab i { font-size: 10px; }

/* ── Product Grid ── */
.product-grid {
    display: grid; grid-template-columns: 1fr; gap: 8px;
}
@media (min-width: 640px) {
    .product-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 900px) {
    .product-grid { grid-template-columns: repeat(3, 1fr); }
}

/* ── Product Card ── */
.product-card {
    background: var(--surface); border: 2px solid var(--border);
    border-radius: 12px; padding: 14px 16px;
    cursor: pointer; transition: all 0.2s ease;
    position: relative; display: flex; align-items: center; gap: 12px;
}
.product-card:hover {
    border-color: #39c5df; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.product-card.selected {
    border-color: var(--primary); background: var(--primary-50);
    box-shadow: 0 0 0 3px rgba(99,83,216,0.1);
}

/* Product selector circle */
.product-selector {
    width: 20px; height: 20px; border: 2px solid var(--border);
    border-radius: 999px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s ease;
}
.product-selector::after {
    content: ''; width: 8px; height: 8px;
    border-radius: 999px; background: transparent;
    transition: all 0.2s ease;
}
.product-card.selected .product-selector {
    border-color: var(--primary);
}
.product-card.selected .product-selector::after {
    background: var(--primary);
}

.product-detail { flex: 1; min-width: 0; }
.product-name {
    font-size: 13.5px; font-weight: 600; color: var(--text);
    margin-bottom: 4px; line-height: 1.3;
}
.product-meta {
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
}
.product-badge {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 2px 7px; border-radius: 999px;
    background: var(--bg); font-size: 10.5px;
    font-weight: 500; color: var(--text-secondary);
}

.product-pricing { flex-shrink: 0; text-align: right; }
.product-price { font-size: 15px; font-weight: 700; color: var(--primary); white-space: nowrap; }
.product-quota { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

/* ── Placeholder ── */
.kuota-empty {
    text-align: center; padding: 48px 20px;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 12px;
}
.kuota-empty-icon {
    width: 72px; height: 72px; border-radius: 999px;
    background: var(--bg); display: flex;
    align-items: center; justify-content: center;
    margin: 0 auto 16px; font-size: 28px; color: var(--text-muted);
}
.kuota-empty-title { font-size: 16px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
.kuota-empty-text { font-size: 13px; color: var(--text-muted); }

/* ── Sticky Summary ── */
.sticky-summary {
    position: fixed; bottom: 0; right: 0;
    left: var(--sidebar-w);
    background: var(--surface); border-top: 1px solid var(--border);
    padding: 12px 24px; z-index: 35;
    box-shadow: 0 -4px 24px rgba(0,0,0,0.06);
    transform: translateY(100%);
    transition: all 0.35s cubic-bezier(0.4,0,0.2,1);
}
.sticky-summary.show { transform: translateY(0); }
.sticky-summary.expanded { left: 0; }
.summary-inner {
    max-width: 960px; margin: 0 auto;
    display: flex; align-items: center; gap: 16px;
}
.summary-info { flex: 1; min-width: 0; }
.summary-label { font-size: 11px; color: var(--text-muted); margin-bottom: 2px; font-weight: 500; }
.summary-product {
    font-size: 14px; font-weight: 600; color: var(--text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.summary-price-section { text-align: right; flex-shrink: 0; }
.summary-price-label { font-size: 11px; color: var(--text-muted); margin-bottom: 2px; font-weight: 500; }
.summary-price { font-size: 18px; font-weight: 700; color: var(--primary); }
.btn-clear {
    width: 36px; height: 36px; border-radius: 999px;
    background: var(--bg); border: none;
    color: var(--text-muted); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: all 0.2s ease;
}
.btn-clear:hover { background: #fee2e2; color: var(--error); }
.btn-buy {
    display: inline-flex; align-items: center; justify-content: center;
    gap: 8px; padding: 10px 24px; border: none;
    border-radius: 10px; font-size: 14px; font-weight: 600;
    color: white; background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    cursor: pointer; transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(99,83,216,0.25);
    min-width: 140px; white-space: nowrap;
}
.btn-buy:hover {
    box-shadow: 0 4px 16px rgba(99,83,216,0.35);
    transform: translateY(-1px);
}
.btn-loading {
    position: relative; color: transparent !important; pointer-events: none;
}
.btn-loading::after {
    content: ''; position: absolute;
    width: 18px; height: 18px;
    border: 2px solid #ffffff; border-right-color: transparent;
    border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Load More ── */
.btn-outline {
    display: inline-flex; align-items: center; justify-content: center;
    gap: 8px; padding: 10px 24px; border: 2px solid var(--border);
    border-radius: 10px; background: transparent;
    color: var(--text-secondary); font-size: 14px; font-weight: 600;
    cursor: pointer; transition: all 0.2s ease; white-space: nowrap;
}
.btn-outline:hover {
    border-color: var(--primary); color: var(--primary);
    background: var(--primary-50);
}

/* ── Toast ── */
.toast-notification {
    position: fixed; top: 80px; right: 20px; z-index: 9999;
    min-width: 280px; max-width: 400px;
    padding: 1rem 1.25rem; border-radius: 12px;
    display: flex; align-items: center; gap: 10px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.12);
    animation: slideInToast 0.4s ease;
    font-size: 14px;
}
.toast-notification.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.toast-notification.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.toast-notification.info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
@keyframes slideInToast {
    from { opacity: 0; transform: translateX(40px); }
    to { opacity: 1; transform: translateX(0); }
}

/* ── Responsive ── */
@media (max-width: 768px) {
    .sticky-summary { left: 0 !important; padding: 12px 16px; }
    .summary-inner { flex-wrap: wrap; }
    .summary-info { flex: 1; }
    .summary-price-section { display: none; }
    .btn-buy { flex: 1; }
    .summary-inner .btn-clear { order: -1; }
    .footer { display: none; }
}
</style>

<!-- ═══════════════════════════════════════════
     KUOTA PAGE - Content
     ═══════════════════════════════════════════ -->

<form method="POST" action="" id="formKuota">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <input type="hidden" name="produk_id" id="produkId" value="">

    <!-- Phone Input -->
    <div class="phone-card">
        <div class="phone-card-title">
            <i class="fas fa-phone-alt"></i> Nomor Handphone
        </div>
        <div class="phone-group">
            <i class="fas fa-mobile-alt input-icon"></i>
            <input type="tel" class="phone-field" id="noHp" name="no_hp"
                   placeholder="Contoh: 08123456789" maxlength="15" required
                   oninput="this.value = this.value.replace(/[^0-9]/g, ''); detectProvider();">
        </div>
        <div class="provider-hint" id="providerHint">
            <span id="providerText">Masukkan nomor untuk mendeteksi provider</span>
        </div>
    </div>

    <!-- Product List -->
    <?php if (empty($produkByProvider)): ?>
    <div class="kuota-empty">
        <div class="kuota-empty-icon"><i class="fas fa-wifi"></i></div>
        <div class="kuota-empty-title">Tidak Ada Paket Tersedia</div>
        <div class="kuota-empty-text">Paket data belum tersedia saat ini</div>
    </div>
    <?php else: ?>

    <!-- Placeholder (before phone entered) -->
    <div id="productPlaceholder" class="kuota-empty">
        <div class="kuota-empty-icon"><i class="fas fa-mobile-alt"></i></div>
        <div class="kuota-empty-title">Masukkan Nomor HP</div>
        <div class="kuota-empty-text">Paket data akan muncul setelah nomor diisi</div>
    </div>

    <!-- Provider Sections -->
    <?php foreach ($produkByProvider as $provider => $produkList):
        $providerLower = strtolower($provider);
        $providerClass = in_array($providerLower, ['telkomsel','indosat','xl','tri','axis','smartfren']) ? $providerLower : 'telkomsel';
        $totalProduk = count($produkList);
    ?>
    <div class="provider-section" data-provider="<?= strtolower($provider) ?>" style="display: none;">
        <div class="provider-header">
            <div class="provider-icon <?= $providerClass ?>">
                <?= strtoupper(substr($provider, 0, 1)) ?>
            </div>
            <div class="provider-info">
                <div class="provider-name"><?= htmlspecialchars($provider) ?></div>
                <div class="provider-count"><?= $totalProduk ?> paket tersedia</div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs" id="filterTabs-<?= strtolower($provider) ?>">
            <button type="button" class="filter-tab active" data-filter="all"
                    onclick="filterProducts('<?= strtolower($provider) ?>', 'all', this)">Semua</button>
            <?php foreach ($daftarKategori as $key => $cat): ?>
            <button type="button" class="filter-tab" data-filter="<?= $key ?>"
                    onclick="filterProducts('<?= strtolower($provider) ?>', '<?= $key ?>', this)">
                <i class="fas <?= $cat['icon'] ?>"></i> <?= $cat['label'] ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Product Grid -->
        <div class="product-grid" id="products-<?= strtolower($provider) ?>">
            <?php
            $displayCount = min(20, $totalProduk);
            for ($i = 0; $i < $displayCount; $i++):
                $p = $produkList[$i];
                $kategori = getKategoriKuota($p['produk_nama'] ?? '');
                $kat = $daftarKategori[$kategori] ?? $daftarKategori['lainnya'];
            ?>
            <?php $produkNama = $p['produk_nama'] ?? ''; ?>
            <?php $produkId = $p['id'] ?? ''; ?>
            <?php $produkHarga = $p['harga_jual'] ?? 0; ?>
            <?php $produkNominal = $p['nominal'] ?? 0; ?>
            <div class="product-card"
                 data-id="<?= $produkId ?>"
                 data-name="<?= htmlspecialchars($produkNama, ENT_QUOTES) ?>"
                 data-price="<?= $produkHarga ?>"
                 data-kategori="<?= $kategori ?>"
                 onclick="selectProduct(this)">
                <div class="product-selector"></div>
                <div class="product-detail">
                    <div class="product-name"><?= htmlspecialchars($produkNama) ?></div>
                    <div class="product-meta">
                        <span class="product-badge"><i class="fas fa-signal"></i> 4G/LTE</span>
                        <span class="product-badge"><i class="fas <?= $kat['icon'] ?>"></i> <?= $kat['label'] ?></span>
                    </div>
                </div>
                <div class="product-pricing">
                    <div class="product-price"><?= rupiah($produkHarga) ?></div>
                    <div class="product-quota"><?= formatBytes($produkNominal) ?></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <?php if ($totalProduk > 20): ?>
        <div class="text-center" style="margin-top:16px;">
            <button type="button" class="btn-outline"
                    id="loadMore-<?= strtolower($provider) ?>"
                    onclick="loadMoreProducts('<?= strtolower($provider) ?>', <?= $totalProduk ?>)">
                <i class="fas fa-plus"></i> Tampilkan lainnya (<?= $totalProduk - 20 ?> lagi)
            </button>
        </div>
        <?php endif; ?>

        <!-- Store product data for JS -->
        <script>
            window.providerProducts = window.providerProducts || {};
            window.providerProducts['<?= strtolower($provider) ?>'] = <?= json_encode(array_map(function($p) {
                return [
                    'id' => $p['id'],
                    'name' => htmlspecialchars($p['produk_nama'] ?? '', ENT_QUOTES),
                    'price' => $p['harga_jual'],
                    'nominal' => $p['nominal'],
                    'kategori' => getKategoriKuota($p['produk_nama'])
                ];
            }, $produkList)) ?>;
        </script>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Sticky Summary -->
    <div class="sticky-summary" id="stickySummary">
        <div class="summary-inner">
            <button type="button" class="btn-clear" title="Batal" onclick="resetSelection()">
                <i class="fas fa-times"></i>
            </button>
            <div class="summary-info">
                <div class="summary-label">Produk dipilih</div>
                <div class="summary-product" id="summaryProduct">-</div>
            </div>
            <div class="summary-price-section">
                <div class="summary-price-label">Total</div>
                <div class="summary-price" id="summaryPrice">Rp 0</div>
            </div>
            <button type="submit" class="btn-buy" id="btnSubmit">
                <i class="fas fa-shopping-cart"></i>
                <span>Beli Paket</span>
            </button>
        </div>
    </div>
</form>

<!-- ═══════════════════════════════════════════
     KUOTA PAGE - JavaScript
     ═══════════════════════════════════════════ -->
<script>
// ══════════ REFERENCES ══════════
const stickySummary = document.getElementById('stickySummary');

// ══════════ PROVIDER DETECTION ══════════
const providerPrefixes = {
    'Telkomsel': ['0811','0812','0813','0821','0822','0823','0851','0852','0853'],
    'Indosat':   ['0814','0815','0816','0855','0856','0857','0858'],
    'XL':        ['0817','0818','0819','0859','0877','0878'],
    'Tri':       ['0895','0896','0897','0898','0899'],
    'Axis':      ['0831','0832','0833','0838'],
    'Smartfren': ['0881','0882','0883','0884','0885','0886','0887','0888','0889']
};

function detectProvider() {
    const noHp = document.getElementById('noHp').value;
    const text = document.getElementById('providerText');
    const hint = document.getElementById('providerHint');

    if (noHp.length < 4) {
        text.innerHTML = 'Masukkan nomor untuk mendeteksi provider';
        text.classList.remove('provider-detected');

        // Hide provider sections, show placeholder
        document.querySelectorAll('.provider-section').forEach(s => s.style.display = 'none');
        document.getElementById('productPlaceholder').style.display = 'block';
        return;
    }

    const prefix = noHp.substring(0, 4);
    let detected = null;

    for (const [provider, prefixes] of Object.entries(providerPrefixes)) {
        if (prefixes.some(p => prefix.startsWith(p))) {
            detected = provider;
            break;
        }
    }

    if (detected) {
        text.innerHTML = `<i class="fas fa-check-circle" style="color:var(--success)"></i> <span class="provider-detected">${detected}</span> terdeteksi`;

        // Show detected provider section, hide others, hide placeholder
        document.getElementById('productPlaceholder').style.display = 'none';
        document.querySelectorAll('.provider-section').forEach(s => s.style.display = 'none');
        const detectedSection = document.querySelector(`.provider-section[data-provider="${detected.toLowerCase()}"]`);
        if (detectedSection) {
            detectedSection.style.display = 'block';

            // Reset filter to "all"
            const filterContainer = document.getElementById('filterTabs-' + detected.toLowerCase());
            if (filterContainer) {
                filterContainer.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                filterContainer.querySelector('.filter-tab[data-filter="all"]')?.classList.add('active');
            }
        }
    } else {
        text.innerHTML = '<i class="fas fa-info-circle"></i> Provider tidak dikenali';
        text.classList.remove('provider-detected');

        // Show all provider sections, hide placeholder
        document.getElementById('productPlaceholder').style.display = 'none';
        document.querySelectorAll('.provider-section').forEach(s => s.style.display = 'block');
    }
}

// ══════════ PRODUCT SELECTION ══════════
let selectedProduct = null;
window.activeFilters = {};

function selectProduct(card) {
    // Toggle selection
    if (card.classList.contains('selected')) {
        resetSelection();
        return;
    }

    // Remove previous
    document.querySelectorAll('.product-card').forEach(c => c.classList.remove('selected'));

    // Select new
    card.classList.add('selected');
    selectedProduct = {
        id: card.dataset.id,
        name: card.dataset.name,
        price: parseInt(card.dataset.price)
    };

    // Update form
    document.getElementById('produkId').value = selectedProduct.id;

    // Update summary
    document.getElementById('summaryProduct').textContent = selectedProduct.name;
    document.getElementById('summaryPrice').textContent = formatRupiah(selectedProduct.price);

    // Show summary
    stickySummary.classList.add('show');

    // Scroll on mobile
    if (isMobile()) {
        setTimeout(() => {
            stickySummary.scrollIntoView({ behavior: 'smooth', block: 'end' });
        }, 100);
    }
}

function resetSelection() {
    document.querySelectorAll('.product-card').forEach(c => c.classList.remove('selected'));
    selectedProduct = null;
    document.getElementById('produkId').value = '';
    document.getElementById('summaryProduct').textContent = '-';
    document.getElementById('summaryPrice').textContent = 'Rp 0';
    stickySummary.classList.remove('show');
}

// ══════════ FILTER ══════════
function filterProducts(provider, filter, btn) {
    // Update active tab
    const container = document.getElementById('filterTabs-' + provider);
    container.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');

    // Filter products
    const grid = document.getElementById('products-' + provider);
    const cards = grid.querySelectorAll('.product-card');
    let visibleCount = 0;

    cards.forEach(card => {
        if (filter === 'all') {
            card.style.display = '';
            visibleCount++;
        } else if (card.dataset.kategori === filter) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    window.activeFilters[provider] = filter;

    // Update load more button visibility
    const loadMoreBtn = document.getElementById('loadMore-' + provider);
    if (loadMoreBtn) {
        const totalProducts = window.providerProducts?.[provider]?.length || 0;
        const currentVisible = grid.querySelectorAll('.product-card:not([style*="display: none"])').length;
        if (currentVisible >= totalProducts || visibleCount === 0) {
            loadMoreBtn.style.display = 'none';
        } else {
            loadMoreBtn.style.display = '';
        }
    }
}

// ══════════ LOAD MORE ══════════
const kategoriLabels = {
    'harian':    { label: 'Harian',    icon: 'fa-sun' },
    'mingguan':  { label: 'Mingguan',  icon: 'fa-calendar-week' },
    'bulanan':   { label: 'Bulanan',   icon: 'fa-calendar-alt' },
    'gaming':    { label: 'Gaming',    icon: 'fa-gamepad' },
    'streaming': { label: 'Streaming', icon: 'fa-play-circle' },
    'sosmed':    { label: 'Sosmed',    icon: 'fa-share-alt' },
    'malam':     { label: 'Malam',     icon: 'fa-moon' },
    'lainnya':   { label: 'Lainnya',   icon: 'fa-ellipsis-h' }
};

function loadMoreProducts(provider, total) {
    const grid = document.getElementById('products-' + provider);
    const btn = document.getElementById('loadMore-' + provider);
    const products = window.providerProducts[provider];

    if (!products || !grid) return;

    const currentCount = grid.querySelectorAll('.product-card').length;
    const nextCount = Math.min(currentCount + 20, total);

    const activeFilter = window.activeFilters[provider] || 'all';
    const filterContainer = document.getElementById('filterTabs-' + provider);

    let visibleAdded = 0;

    for (let i = currentCount; i < nextCount; i++) {
        const p = products[i];
        const card = document.createElement('div');
        card.className = 'product-card';
        card.dataset.id = p.id;
        card.dataset.name = p.name;
        card.dataset.price = p.price;
        card.dataset.kategori = p.kategori;
        card.onclick = function() { selectProduct(this); };

        // Hide if not matching filter
        if (activeFilter !== 'all' && p.kategori !== activeFilter) {
            card.style.display = 'none';
        } else {
            visibleAdded++;
        }

        const kat = kategoriLabels[p.kategori] || kategoriLabels['lainnya'];

        card.innerHTML = `
            <div class="product-selector"></div>
            <div class="product-detail">
                <div class="product-name">${p.name}</div>
                <div class="product-meta">
                    <span class="product-badge"><i class="fas fa-signal"></i> 4G/LTE</span>
                    <span class="product-badge"><i class="fas ${kat.icon}"></i> ${kat.label}</span>
                </div>
            </div>
            <div class="product-pricing">
                <div class="product-price">${formatRupiah(p.price)}</div>
                <div class="product-quota">${formatBytesJS(p.nominal)}</div>
            </div>`;
        grid.appendChild(card);
    }

    if (btn) {
        if (nextCount >= total) {
            btn.style.display = 'none';
        } else {
            btn.innerHTML = `<i class="fas fa-plus"></i> Tampilkan lainnya (${total - nextCount} lagi)`;
        }
    }
}

// ══════════ UTILITIES ══════════
function formatRupiah(num) {
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function formatBytesJS(bytes, precision = 2) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    bytes = Math.max(bytes, 0);
    let pow = Math.floor(Math.log(bytes || 1) / Math.log(1024));
    pow = Math.min(pow, units.length - 1);
    return (bytes / Math.pow(1024, pow)).toFixed(precision) + ' ' + units[pow];
}

function isMobile() { return window.innerWidth <= 768; }

function showToast(message, type = 'info') {
    const existing = document.getElementById('toastNotif');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'toastNotif';
    toast.className = 'toast-notification ' + type;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i><span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// ══════════ FORM SUBMISSION ══════════
document.getElementById('formKuota').addEventListener('submit', function(e) {
    const noHp = document.getElementById('noHp').value;
    const produkId = document.getElementById('produkId').value;

    if (!noHp || noHp.length < 10) {
        e.preventDefault();
        showToast('Masukkan nomor HP yang valid (minimal 10 digit)', 'error');
        return;
    }

    if (!produkId) {
        e.preventDefault();
        showToast('Pilih paket data terlebih dahulu', 'error');
        return;
    }

    // Show loading
    const btn = document.getElementById('btnSubmit');
    btn.classList.add('btn-loading');
    btn.disabled = true;
});

// ══════════ SIDEBAR SYNC ══════════
// Sync sticky summary left position with sidebar state
function syncSummaryPosition() {
    if (!isMobile()) {
        const mainWrapper = document.getElementById('mainWrapper');
        if (mainWrapper && mainWrapper.classList.contains('sidebar-hidden')) {
            stickySummary.classList.add('expanded');
        } else {
            stickySummary.classList.remove('expanded');
        }
    }
}

// Override sidebar toggle to also sync summary
const origToggle = window.toggleSidebar;
if (origToggle) {
    window.toggleSidebar = function() {
        origToggle();
        setTimeout(syncSummaryPosition, 350);
    };
}

// ══════════ KEYBOARD SHORTCUT ══════════
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (selectedProduct) {
            resetSelection();
        }
    }
});

// ══════════ INIT ══════════
document.addEventListener('DOMContentLoaded', function() {
    syncSummaryPosition();
});
</script>

<?php
include 'layout_footer.php';
$conn->close();
?>