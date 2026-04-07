<?php
require_once 'config.php';
cekLogin();
$conn = koneksi();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$_SESSION['saldo'] = getSaldo($user_id);

// ═══ Statistik Admin ═══
if ($role == 'admin') {
    // Total User
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'member'");
    $totalUser = $result->fetch_assoc()['total'];

    // Transaksi Hari Ini
    $result = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(total_bayar), 0) as pendapatan FROM transaksi WHERE DATE(created_at) = CURDATE() AND status = 'success'");
    $row = $result->fetch_assoc();
    $transaksiHariIni = $row['total'];
    $pendapatanHariIni = $row['pendapatan'];

    // Transaksi Bulan Ini
    $result = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(total_bayar), 0) as pendapatan FROM transaksi WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'success'");
    $row = $result->fetch_assoc();
    $transaksiBulanIni = $row['total'];
    $pendapatanBulanIni = $row['pendapatan'];

    // Profit Bulan Ini
    $result = $conn->query("SELECT COALESCE(SUM(COALESCE(t.harga - COALESCE(p.harga_modal, 0), 0)), 0) as profit FROM transaksi t LEFT JOIN produk p ON t.produk_id = p.id WHERE MONTH(t.created_at) = MONTH(CURDATE()) AND YEAR(t.created_at) = YEAR(CURDATE()) AND t.status = 'success'");
    $profitBulanIni = $result->fetch_assoc()['profit'];

    // Transaksi Terakhir (Admin - semua user)
    $transaksiAdmin = $conn->query("SELECT t.*, p.nama_produk, u.nama_lengkap FROM transaksi t LEFT JOIN produk p ON t.produk_id = p.id LEFT JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 10");

    // Data Chart (7 hari terakhir)
    $chartData = $conn->query("SELECT DATE(created_at) as tanggal, COUNT(*) as jumlah, SUM(total_bayar) as total FROM transaksi WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 'success' GROUP BY DATE(created_at) ORDER BY tanggal");
    $chartLabels = []; $chartValues = [];
    while ($row = $chartData->fetch_assoc()) {
        $chartLabels[] = date('d M', strtotime($row['tanggal']));
        $chartValues[] = $row['total'];
    }

    // Kategori Produk
    $kategori = $conn->query("SELECT * FROM kategori_produk WHERE status = 'active'");
}

// Transaksi Terakhir (User)
$stmt = $conn->prepare("SELECT t.*, p.nama_produk FROM transaksi t LEFT JOIN produk p ON t.produk_id = p.id WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transaksiTerakhir = $stmt->get_result();

// ═══ Layout Variables ═══
$pageTitle   = 'Dashboard';
$pageIcon    = 'fas fa-home';
$currentPage = 'index';
$alert       = getAlert();

// Additional head scripts (Chart.js)
$additionalHeadScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

include 'layout.php';
?>

<!-- ═══════════════════════════════════════════
     DASHBOARD - Custom Styles
     ═══════════════════════════════════════════ -->
<style>
/* ── Welcome ── */
.welcome-section {
    animation: fadeUp 0.5s ease forwards;
}

/* ── Stat Cards ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}
@media (min-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(4, 1fr); }
}

.stat-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.25rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    animation: fadeUp 0.5s ease forwards;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.stat-card .stat-icon {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.stat-card .stat-value {
    font-size: 1.5rem; font-weight: 700; color: var(--text);
    line-height: 1.2;
}
.stat-card .stat-label {
    font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.25rem;
}

/* ── Quick Menu ── */
.quick-menu-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}
@media (min-width: 768px) {
    .quick-menu-grid { grid-template-columns: repeat(4, 1fr); }
}

.quick-menu-item {
    display: flex; flex-direction: column; align-items: center;
    padding: 1.25rem 1rem;
    background: white; border: 1px solid var(--border);
    border-radius: 16px;
    text-decoration: none;
    transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
}
.quick-menu-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(99,83,216,0.12);
}
.quick-menu-item:active { transform: translateY(-2px); }
.quick-menu-item .menu-icon {
    width: 48px; height: 48px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 0.75rem;
    transition: transform 0.3s ease;
    font-size: 1.25rem;
}
.quick-menu-item:hover .menu-icon { transform: scale(1.1); }
.quick-menu-item .menu-label {
    font-size: 0.813rem; font-weight: 500; color: var(--text);
}
.quick-menu-item .menu-sublabel {
    font-size: 0.688rem; color: var(--text-muted); margin-top: 2px;
    display: none;
}
@media (min-width: 768px) {
    .quick-menu-item .menu-sublabel { display: block; }
}

/* ── Main Content Grid ── */
.content-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.25rem;
}
@media (min-width: 1024px) {
    .content-grid { grid-template-columns: 1fr 1fr; }
    .content-grid .span-2 { grid-column: span 2; }
    .content-grid .span-1 { grid-column: span 1; }
}

/* ── Section Card ── */
.section-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    overflow: hidden;
    animation: fadeUp 0.5s ease forwards;
}
.section-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
}
.section-title {
    font-weight: 600; font-size: 0.938rem; color: var(--text);
    display: flex; align-items: center; gap: 0.5rem;
}
.section-title i { color: var(--primary); font-size: 0.875rem; }
.section-subtitle {
    font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;
}
.section-link {
    font-size: 0.75rem; color: var(--primary); text-decoration: none;
    font-weight: 500; display: flex; align-items: center; gap: 4px;
    transition: opacity 0.2s;
}
.section-link:hover { opacity: 0.7; }
.section-body { padding: 1.25rem; }

/* ── Transaction Row ── */
.trx-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s ease;
}
.trx-row:last-child { border-bottom: none; }
.trx-row:hover { background: #f8fafc; border-radius: 8px; padding-left: 8px; padding-right: 8px; margin: 0 -8px; }

.trx-left { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
.trx-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 0.875rem;
}
.trx-info { min-width: 0; }
.trx-name { font-size: 0.813rem; font-weight: 500; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.trx-dest { font-size: 0.688rem; color: var(--text-muted); font-family: monospace; }
.trx-right { text-align: right; flex-shrink: 0; }
.trx-amount { font-size: 0.813rem; font-weight: 700; color: var(--primary); }
.trx-time { font-size: 0.625rem; color: var(--text-muted); margin-top: 2px; }

/* ── Badge ── */
.badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    font-size: 0.625rem;
    font-weight: 600;
    white-space: nowrap;
}
.badge-success { background: #dcfce7; color: #166534; }
.badge-pending { background: #fef9c3; color: #854d0e; }
.badge-failed  { background: #fee2e2; color: #991b1b; }
.badge-blue    { background: #dbeafe; color: #1e40af; }
.badge-green   { background: #dcfce7; color: #166534; }
.badge-yellow  { background: #fef9c3; color: #854d0e; }
.badge-purple  { background: #f3e8ff; color: #7c3aed; }

/* ── Promo Banner ── */
.promo-banner {
    background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 60%, #7c3aed 100%);
    border-radius: 16px;
    padding: 1.5rem;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    overflow: hidden;
    position: relative;
    animation: fadeUp 0.5s ease forwards;
}
.promo-banner::before {
    content: '';
    position: absolute;
    top: -30px; right: -30px;
    width: 120px; height: 120px;
    background: rgba(255,255,255,0.08);
    border-radius: 50%;
}
.promo-banner::after {
    content: '';
    position: absolute;
    bottom: -40px; right: 60px;
    width: 80px; height: 80px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.promo-content { position: relative; z-index: 1; }
.promo-tag {
    display: inline-block;
    background: rgba(255,255,255,0.15);
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.688rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    backdrop-filter: blur(4px);
}
.promo-title {
    font-size: 1.125rem; font-weight: 700; margin-bottom: 0.375rem;
}
.promo-desc {
    font-size: 0.813rem; opacity: 0.85; max-width: 400px;
}
.promo-btn {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: white; color: var(--primary);
    padding: 0.625rem 1.25rem; border-radius: 10px;
    font-size: 0.813rem; font-weight: 600;
    text-decoration: none; position: relative; z-index: 1;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    flex-shrink: 0;
}
.promo-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,0,0,0.2); }

/* ── Empty State ── */
.empty-state {
    text-align: center; padding: 2.5rem 1rem;
}
.empty-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--primary-50); color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; margin: 0 auto 0.75rem;
}
.empty-title { font-size: 0.875rem; font-weight: 600; color: var(--text); }
.empty-text { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }

/* ── Chart ── */
.chart-container {
    position: relative;
    height: 250px;
}
@media (min-width: 768px) {
    .chart-container { height: 280px; }
}

/* ── Animations ── */
.delay-100 { animation-delay: 0.1s; }
.delay-200 { animation-delay: 0.2s; }
.delay-300 { animation-delay: 0.3s; }
.delay-400 { animation-delay: 0.4s; }
</style>

<!-- ═══════════════════════════════════════════
     DASHBOARD - Content
     ═══════════════════════════════════════════ -->

<!-- Welcome Section -->
<div class="welcome-section" style="margin-bottom:1.5rem;">
    <h2 style="font-size:1.375rem;font-weight:700;color:var(--text);">
        Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>! 👋
    </h2>
    <p style="font-size:0.813rem;color:var(--text-secondary);margin-top:0.25rem;">
        Kelola layanan PPOB Anda dengan mudah dan cepat
    </p>
</div>

<!-- ── Admin Stats ── -->
<?php if ($role == 'admin'): ?>
<div class="stats-grid" style="margin-bottom:1.5rem;">
    <div class="stat-card delay-100">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
            <div>
                <p class="stat-label">Total Member</p>
                <p class="stat-value"><?= number_format($totalUser) ?></p>
            </div>
            <div class="stat-icon" style="background:#dbeafe;">
                <i class="fas fa-users" style="color:#2563eb;"></i>
            </div>
        </div>
    </div>

    <div class="stat-card delay-200">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
            <div>
                <p class="stat-label">Transaksi Hari Ini</p>
                <p class="stat-value"><?= number_format($transaksiHariIni) ?></p>
            </div>
            <div class="stat-icon" style="background:#dcfce7;">
                <i class="fas fa-receipt" style="color:#16a34a;"></i>
            </div>
        </div>
    </div>

    <div class="stat-card delay-300">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
            <div>
                <p class="stat-label">Omset Bulan Ini</p>
                <p style="font-size:1.125rem;font-weight:700;color:#d97706;"><?= rupiah($pendapatanBulanIni) ?></p>
            </div>
            <div class="stat-icon" style="background:#fef3c7;">
                <i class="fas fa-coins" style="color:#d97706;"></i>
            </div>
        </div>
    </div>

    <div class="stat-card delay-400">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
            <div>
                <p class="stat-label">Profit Bulan Ini</p>
                <p style="font-size:1.125rem;font-weight:700;color:#4338ca;"><?= rupiah($profitBulanIni) ?></p>
            </div>
            <div class="stat-icon" style="background:#e0e7ff;">
                <i class="fas fa-chart-line" style="color:#4338ca;"></i>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Quick Menu ── -->
<div class="section-card delay-200" style="margin-bottom:1.5rem;">
    <div class="section-header">
        <div>
            <h3 class="section-title"><i class="fas fa-bolt"></i> Layanan Cepat</h3>
        </div>
    </div>
    <div style="padding:1.25rem;">
        <div class="quick-menu-grid">
            <a href="pulsa.php" class="quick-menu-item">
                <div class="menu-icon" style="background:#dbeafe;">
                    <i class="fas fa-mobile-alt" style="color:#2563eb;"></i>
                </div>
                <span class="menu-label">Pulsa</span>
                <span class="menu-sublabel">Isi pulsa cepat</span>
            </a>
            <a href="kuota.php" class="quick-menu-item">
                <div class="menu-icon" style="background:#dcfce7;">
                    <i class="fas fa-wifi" style="color:#16a34a;"></i>
                </div>
                <span class="menu-label">Kuota</span>
                <span class="menu-sublabel">Paket data</span>
            </a>
            <a href="listrik.php" class="quick-menu-item">
                <div class="menu-icon" style="background:#fef3c7;">
                    <i class="fas fa-bolt" style="color:#d97706;"></i>
                </div>
                <span class="menu-label">Listrik</span>
                <span class="menu-sublabel">Token PLN</span>
            </a>
            <a href="transfer.php" class="quick-menu-item">
                <div class="menu-icon" style="background:#f3e8ff;">
                    <i class="fas fa-money-bill-transfer" style="color:#7c3aed;"></i>
                </div>
                <span class="menu-label">Transfer</span>
                <span class="menu-sublabel">Kirim uang</span>
            </a>
        </div>
    </div>
</div>

<!-- ── Kategori Prepaid ── -->
<div class="section-card delay-300" style="margin-bottom:1.5rem;">
    <div class="section-header">
        <div>
            <h3 class="section-title"><i class="fas fa-star"></i> Prepaid</h3>
        </div>
    </div>
    <div style="padding:1rem 1.25rem;">
        <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:0.5rem;" class="kategori-scroll">
            <a href="pulsa.php" class="kategori-item">
                <div class="kategori-icon" style="background:#dbeafe;"><i class="fas fa-mobile-alt" style="color:#2563eb;"></i></div>
                <span class="kategori-label">Pulsa</span>
            </a>
            <a href="kuota.php" class="kategori-item">
                <div class="kategori-icon" style="background:#dcfce7;"><i class="fas fa-wifi" style="color:#16a34a;"></i></div>
                <span class="kategori-label">Paket Data</span>
            </a>
            <a href="listrik.php" class="kategori-item">
                <div class="kategori-icon" style="background:#fef3c7;"><i class="fas fa-bolt" style="color:#d97706;"></i></div>
                <span class="kategori-label">Token PLN</span>
            </a>
            <a href="#" class="kategori-item" onclick="alert('Fitur Wallet akan segera hadir!');return false;">
                <div class="kategori-icon" style="background:#fce7f3;"><i class="fas fa-wallet" style="color:#db2777;"></i></div>
                <span class="kategori-label">Wallet</span>
            </a>
            <a href="#" class="kategori-item" onclick="alert('Fitur Bank Transfer akan segera hadir!');return false;">
                <div class="kategori-icon" style="background:#e0e7ff;"><i class="fas fa-university" style="color:#4338ca;"></i></div>
                <span class="kategori-label">Bank Transfer</span>
            </a>
            <a href="game.php" class="kategori-item">
                <div class="kategori-icon" style="background:#f3e8ff;"><i class="fas fa-gamepad" style="color:#7c3aed;"></i></div>
                <span class="kategori-label">Games</span>
            </a>
        </div>
    </div>
</div>

<!-- ── Kategori Postpaid ── -->
<div class="section-card delay-400" style="margin-bottom:1.5rem;">
    <div class="section-header">
        <div>
            <h3 class="section-title"><i class="fas fa-file-invoice-dollar"></i> Postpaid</h3>
        </div>
    </div>
    <div style="padding:1rem 1.25rem;">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;" class="kategori-scroll">
            <a href="listrik.php" class="kategori-item">
                <div class="kategori-icon" style="background:#fef3c7;"><i class="fas fa-bolt" style="color:#d97706;"></i></div>
                <span class="kategori-label">PLN</span>
            </a>
            <a href="#" class="kategori-item" onclick="alert('Fitur PDAM akan segera hadir!');return false;">
                <div class="kategori-icon" style="background:#cffafe;"><i class="fas fa-tint" style="color:#0891b2;"></i></div>
                <span class="kategori-label">PDAM</span>
            </a>
            <a href="#" class="kategori-item" onclick="alert('Fitur TV & Internet akan segera hadir!');return false;">
                <div class="kategori-icon" style="background:#fae8ff;"><i class="fas fa-tv" style="color:#c026d3;"></i></div>
                <span class="kategori-label">TV & Internet</span>
            </a>
            <a href="#" class="kategori-item" onclick="alert('Fitur Telpon akan segera hadir!');return false;">
                <div class="kategori-icon" style="background:#fee2e2;"><i class="fas fa-phone" style="color:#dc2626;"></i></div>
                <span class="kategori-label">Telpon</span>
            </a>
            <a href="#" class="kategori-item" onclick="alert('Fitur GAS akan segera hadir!');return false;">
                <div class="kategori-icon" style="background:#ffedd5;"><i class="fas fa-fire" style="color:#ea580c;"></i></div>
                <span class="kategori-label">GAS</span>
            </a>
            <a href="#" class="kategori-item" onclick="alert('Fitur BPJS akan segera hadir!');return false;">
                <div class="kategori-icon" style="background:#dbeafe;"><i class="fas fa-notes-medical" style="color:#2563eb;"></i></div>
                <span class="kategori-label">BPJS</span>
            </a>
        </div>
    </div>
</div>

<style>
.kategori-item {
    display:flex; flex-direction:column; align-items:center;
    padding:0.75rem 0.5rem; background:#f8fafc; border:1px solid var(--border);
    border-radius:12px; text-decoration:none; transition:all 0.2s ease;
}
.kategori-item:hover {
    background:white; transform:translateY(-2px);
    box-shadow:0 4px 12px rgba(99,83,216,0.1);
}
.kategori-icon {
    width:36px; height:36px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    margin-bottom:0.375rem; font-size:0.875rem;
}
.kategori-label {
    font-size:0.688rem; font-weight:500; color:var(--text);
    text-align:center;
}
@media (min-width:640px) {
    .kategori-item { padding:1rem 0.5rem; }
    .kategori-icon { width:40px; height:40px; font-size:1rem; margin-bottom:0.5rem; }
    .kategori-label { font-size:0.75rem; }
}
</style>

<!-- Mobile: Horizontal Scroll Kategori -->
<style>
@media (max-width:639px) {
    .kategori-scroll {
        display:flex; gap:0.5rem; overflow-x:auto; padding-bottom:0.5rem;
        -webkit-overflow-scrolling:touch; scrollbar-width:none;
    }
    .kategori-scroll::-webkit-scrollbar { display:none; }
    .kategori-scroll .kategori-item {
        flex-shrink:0; min-width:80px; padding:0.75rem 0.75rem;
    }
    .kategori-scroll .kategori-icon {
        width:32px; height:32px; font-size:0.75rem; margin-bottom:0.375rem;
    }
    .kategori-scroll .kategori-label { font-size:0.625rem; }
}
</style>

<!-- ── Content Grid ── -->
<div class="content-grid">

    <!-- Chart Section (Admin Only) -->
    <?php if ($role == 'admin'): ?>
    <div class="section-card delay-300 <?= $role == 'admin' ? 'span-2' : '' ?>">
        <div class="section-header">
            <div>
                <h3 class="section-title">
                    <i class="fas fa-chart-bar"></i> Statistik Transaksi 7 Hari Terakhir
                </h3>
            </div>
            <span style="font-size:0.75rem;color:var(--text-muted);background:#f1f5f9;padding:0.25rem 0.75rem;border-radius:999px;">
                Total: <?= rupiah(array_sum($chartValues)) ?>
            </span>
        </div>
        <div class="section-body">
            <div class="chart-container">
                <canvas id="transaksiChart"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Transaksi Terakhir -->
    <div class="section-card delay-300 <?= $role == 'admin' ? 'span-2' : '' ?>">
        <div class="section-header">
            <div>
                <h3 class="section-title">
                    <i class="fas fa-history"></i> Transaksi Terakhir
                </h3>
                <p class="section-subtitle"><?= $transaksiTerakhir->num_rows ?> transaksi terbaru</p>
            </div>
            <a href="riwayat.php" class="section-link">
                Lihat Semua <i class="fas fa-chevron-right" style="font-size:10px;"></i>
            </a>
        </div>

        <?php if ($transaksiTerakhir->num_rows > 0): ?>
        <div class="section-body" style="padding:0.75rem 1.25rem;">
            <?php
            $transaksiTerakhir->data_seek(0);
            while ($trx = $transaksiTerakhir->fetch_assoc()):
                // Icon & color based on type
                $iconClass = 'fas fa-receipt'; $iconColor = '#64748b'; $iconBg = '#f1f5f9';
                switch($trx['jenis_transaksi']) {
                    case 'pulsa':    $iconClass = 'fas fa-mobile-alt';          $iconColor = '#2563eb'; $iconBg = '#dbeafe'; break;
                    case 'kuota':    $iconClass = 'fas fa-wifi';                $iconColor = '#16a34a'; $iconBg = '#dcfce7'; break;
                    case 'listrik':  $iconClass = 'fas fa-bolt';                $iconColor = '#d97706'; $iconBg = '#fef3c7'; break;
                    case 'transfer': $iconClass = 'fas fa-money-bill-transfer'; $iconColor = '#7c3aed'; $iconBg = '#f3e8ff'; break;
                }

                // Status badge
                $badgeClass = 'badge-success'; $statusLabel = 'Success';
                switch($trx['status']) {
                    case 'pending': $badgeClass = 'badge-pending'; $statusLabel = 'Pending'; break;
                    case 'failed':  $badgeClass = 'badge-failed';  $statusLabel = 'Failed'; break;
                }
            ?>
            <div class="trx-row">
                <div class="trx-left">
                    <div class="trx-icon" style="background:<?= $iconBg ?>;">
                        <i class="<?= $iconClass ?>" style="color:<?= $iconColor ?>;"></i>
                    </div>
                    <div class="trx-info">
                        <div class="trx-name"><?= htmlspecialchars($trx['nama_produk'] ?? ucfirst($trx['jenis_transaksi'])) ?></div>
                        <div class="trx-dest"><?= htmlspecialchars($trx['no_tujuan']) ?></div>
                    </div>
                </div>
                <div class="trx-right">
                    <div class="trx-amount"><?= rupiah($trx['total_bayar']) ?></div>
                    <div class="trx-time">
                        <span class="badge <?= $badgeClass ?>" style="margin-right:4px;"><?= $statusLabel ?></span>
                        <?= date('d M, H:i', strtotime($trx['created_at'])) ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <h3 class="empty-title">Belum ada transaksi</h3>
            <p class="empty-text">Mulai transaksi pertama Anda</p>
            <a href="pulsa.php" style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--primary);color:white;padding:0.5rem 1rem;border-radius:8px;font-size:0.813rem;font-weight:500;text-decoration:none;margin-top:1rem;transition:all 0.2s;">
                <i class="fas fa-plus"></i> Transaksi Baru
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Promo Banner (Member Only) -->
    <?php if ($role != 'admin'): ?>
    <div class="span-2 delay-300" style="margin-top:0.25rem;">
        <div class="promo-banner">
            <div class="promo-content">
                <span class="promo-tag">Promo</span>
                <h3 class="promo-title">🎉 Cashback 10%!</h3>
                <p class="promo-desc">Khusus pembelian pulsa minimal Rp 50.000. Berlaku hingga 31 Desember.</p>
            </div>
            <a href="pulsa.php" class="promo-btn">
                <i class="fas fa-bolt"></i> Beli Sekarang
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ═══════════════════════════════════════════
     DASHBOARD - JavaScript
     ═══════════════════════════════════════════ -->
<?php if ($role == 'admin' && !empty($chartLabels)): ?>
<script>
const ctx = document.getElementById('transaksiChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Total Transaksi (Rp)',
            data: <?= json_encode($chartValues) ?>,
            backgroundColor: 'rgba(99, 83, 216, 0.08)',
            borderColor: 'rgba(99, 83, 216, 0.8)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: 'rgb(99, 83, 216)',
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                mode: 'index',
                intersect: false,
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                titleFont: { size: 13 },
                bodyFont: { size: 13 },
                callbacks: {
                    label: function(context) {
                        return 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    drawBorder: false,
                    color: 'rgba(0, 0, 0, 0.04)'
                },
                ticks: {
                    font: { size: 11 },
                    callback: function(value) {
                        if (value >= 1000000) return 'Rp ' + (value / 1000000).toFixed(1) + 'jt';
                        else if (value >= 1000) return 'Rp ' + (value / 1000).toFixed(0) + 'rb';
                        return 'Rp ' + value;
                    }
                }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 11 } }
            }
        },
        interaction: {
            intersect: false,
            mode: 'nearest'
        }
    }
});
</script>
<?php endif; ?>

<?php
include 'layout_footer.php';
$conn->close();
?>