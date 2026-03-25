<?php
require_once 'config.php';
cekLogin();

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$_SESSION['saldo'] = getSaldo($user_id);

// ═══ Layout Variables ═══
$pageTitle   = 'Riwayat Transaksi';
$pageIcon    = 'fas fa-history';
$pageDesc    = 'Lihat semua riwayat transaksi Anda';
$currentPage = 'riwayat';
$additionalHeadScripts = ''; // Tidak perlu script tambahan

// ═══ Filter ═══
$filterJenis   = $_GET['jenis']   ?? '';
$filterStatus  = $_GET['status']  ?? '';
$filterTanggal = $_GET['tanggal'] ?? '';
$filterBulan   = $_GET['bulan']   ?? '';
$search        = $_GET['search']   ?? '';

// ═══ Query Transaksi ═══
$where  = [];
$params = [];
$types  = '';

if ($role != 'admin') {
    $where[]  = "t.user_id = ?";
    $params[] = $user_id;
    $types   .= 'i';
}
if ($filterJenis) {
    $where[]  = "t.jenis_transaksi = ?";
    $params[] = $filterJenis;
    $types   .= 's';
}
if ($filterStatus) {
    $where[]  = "t.status = ?";
    $params[] = $filterStatus;
    $types   .= 's';
}
if ($filterTanggal) {
    $where[]  = "DATE(t.created_at) = ?";
    $params[] = $filterTanggal;
    $types   .= 's';
}
if ($filterBulan) {
    $where[]  = "DATE_FORMAT(t.created_at,'%Y-%m') = ?";
    $params[] = $filterBulan;
    $types   .= 's';
}
if ($search) {
    $where[]  = "(t.no_invoice LIKE ? OR t.no_tujuan LIKE ? OR t.ref_id LIKE ? OR t.server_id LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types   .= 'ssss';
}

$whereClause = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT t.*, p.nama_produk, p.provider, u.nama_lengkap
        FROM transaksi t
        LEFT JOIN produk p ON t.produk_id = p.id
        LEFT JOIN users u  ON t.user_id   = u.id"
       . $whereClause .
       " ORDER BY t.created_at DESC LIMIT 100";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transaksi = $stmt->get_result();

// ═══ Stats (Admin Only) ═══
if ($role == 'admin') {
    $statsQuery = $conn->query(
        "SELECT COUNT(*) as total,
         SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as sukses,
         SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
         SUM(CASE WHEN status='failed'  THEN 1 ELSE 0 END) as gagal,
         SUM(CASE WHEN status='success' THEN total_bayar ELSE 0 END) as total_sukses
         FROM transaksi WHERE DATE(created_at) = CURDATE()"
    );
    $stats = $statsQuery->fetch_assoc();

    $totalQuery = $conn->query(
        "SELECT COUNT(*) as total_transaksi,
         SUM(total_bayar) as total_offset
         FROM transaksi WHERE status = 'success'"
    );
    $total = $totalQuery->fetch_assoc();
}

$alert = getAlert();

// Include layout
include 'layout.php';
?>

<!-- ═══════════════════════════════════════════
     RIWAYAT - Custom Styles
═══════════════════════════════════════════ -->
<style>
/* ── Badge ── */
.badge {
    display: inline-block;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 600;
    white-space: nowrap;
}

/* Jenis badges */
.badge.jenis-pulsa    { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
.badge.jenis-kuota    { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.badge.jenis-listrik  { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.badge.jenis-game     { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
.badge.jenis-transfer { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.badge.jenis-deposit  { background: #cffafe; color: #0e7490; border: 1px solid #a5f3fc; }

/* Status badges */
.badge.status-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.badge.status-pending { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.badge.status-failed  { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

/* ── Stat Card ── */
.stat-card {
    border-radius: 0.75rem;
    border: 1px solid #e8ecf0;
    background: white;
    padding: 1rem 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* ── Card ── */
.card {
    background: white;
    border: 1px solid #e8ecf0;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}

/* ── Table ── */
.table-header-row th {
    background: #f8fafc;
    color: #374151;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.75rem 1.25rem;
    border-bottom: 2px solid #e5e7eb;
}
.table-row td {
    padding: 0.875rem 1.25rem;
    font-size: 0.875rem;
    color: #374151;
    border-bottom: 1px solid #f1f5f9;
}
.table-row:hover td { background: #f8fafc; }
.table-row:last-child td { border-bottom: none; }

/* ── Mobile Transaction Card ── */
.trx-card {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s ease;
}
.trx-card:hover { background: #f8fafc; }
.trx-card:last-child { border-bottom: none; }

/* ── Filter Icon Rotate ── */
#filterIcon { transition: transform 0.3s ease; }
.icon-rotated { transform: rotate(180deg); }

/* ── Animation ── */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-slide-in {
    animation: slideIn 0.3s ease forwards;
}

.delay-100 { animation-delay: 0.1s; }
.delay-200 { animation-delay: 0.2s; }
.delay-300 { animation-delay: 0.3s; }

/* ── Responsive helpers ── */
@media (max-width: 767px) {
    .hide-mobile { display: none !important; }
}
@media (min-width: 768px) {
    .hide-desktop { display: none !important; }
}
</style>

<!-- ═══════════════════════════════════════════
     RIWAYAT - Content
═══════════════════════════════════════════ -->

<!-- Alert Message (if any) -->
<?php if ($alert): ?>
<div class="alert mb-6 p-4 rounded-lg flex items-center gap-3 <?= $alert['type'] == 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>" style="animation: slideIn 0.3s ease;">
    <i class="fas <?= $alert['type'] == 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> text-lg"></i>
    <span class="font-medium"><?= $alert['message'] ?></span>
</div>
<?php endif; ?>

<!-- Page content wrapper -->
<div class="space-y-5">

    <!-- ── Stats (Admin Only) ── -->
    <?php if ($role == 'admin'): ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 animate-slide-in delay-100">
        <!-- Hari Ini -->
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Hari Ini</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?></p>
                </div>
                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center">
                    <i class="fas fa-calendar-day text-blue-600"></i>
                </div>
            </div>
        </div>
        <!-- Sukses -->
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Sukses</p>
                    <p class="text-2xl font-bold text-green-600"><?= $stats['sukses'] ?></p>
                </div>
                <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600"></i>
                </div>
            </div>
        </div>
        <!-- Pending -->
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Pending</p>
                    <p class="text-2xl font-bold text-amber-600"><?= $stats['pending'] ?></p>
                </div>
                <div class="w-10 h-10 rounded-full bg-amber-50 flex items-center justify-center">
                    <i class="fas fa-clock text-amber-600"></i>
                </div>
            </div>
        </div>
        <!-- Omset Sukses -->
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Omset Sukses</p>
                    <p class="text-lg font-bold text-blue-600"><?= rupiah($stats['total_sukses'] ?? 0) ?></p>
                </div>
                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center">
                    <i class="fas fa-coins text-blue-600"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Filter Card ── -->
    <div class="card animate-slide-in delay-200">

        <!-- Mobile: Collapsible -->
        <div class="hide-desktop">
            <button onclick="toggleFilter()"
                class="w-full flex items-center justify-between px-4 py-3.5 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-xl transition">
                <span class="flex items-center gap-2">
                    <i class="fas fa-filter text-blue-600"></i>
                    Filter Transaksi
                    <?php if ($filterJenis || $filterStatus || $filterTanggal || $filterBulan || $search): ?>
                    <span class="w-2 h-2 bg-blue-600 rounded-full"></span>
                    <?php endif; ?>
                </span>
                <i id="filterIcon" class="fas fa-chevron-down text-gray-400 text-xs"></i>
            </button>
            <div id="filterContent" class="hidden px-4 pb-4 border-t border-gray-100 space-y-3">
                <form method="GET" class="pt-3 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Cari</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Invoice atau No. HP..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Jenis</label>
                        <select name="jenis" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Semua Jenis</option>
                            <option value="pulsa"    <?= $filterJenis=='pulsa'?'selected':'' ?>>Pulsa</option>
                            <option value="kuota"    <?= $filterJenis=='kuota'?'selected':'' ?>>Paket Data</option>
                            <option value="listrik"  <?= $filterJenis=='listrik'?'selected':'' ?>>Token Listrik</option>
                            <option value="game"     <?= $filterJenis=='game'?'selected':'' ?>>Top Up Game</option>
                            <option value="transfer" <?= $filterJenis=='transfer'?'selected':'' ?>>Transfer</option>
                            <option value="deposit"  <?= $filterJenis=='deposit'?'selected':'' ?>>Deposit</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Semua Status</option>
                            <option value="success" <?= $filterStatus=='success'?'selected':'' ?>>Sukses</option>
                            <option value="pending" <?= $filterStatus=='pending'?'selected':'' ?>>Pending</option>
                            <option value="failed"  <?= $filterStatus=='failed'?'selected':'' ?>>Gagal</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal</label>
                        <input type="date" name="tanggal" value="<?= $filterTanggal ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Bulan</label>
                        <input type="month" name="bulan" value="<?= $filterBulan ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium flex items-center gap-2 transition">
                            <i class="fas fa-filter text-xs"></i> Terapkan Filter
                        </button>
                        <a href="riwayat.php" class="px-5 py-2 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg text-sm font-medium flex items-center gap-2 transition">
                            <i class="fas fa-sync-alt text-xs"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Desktop: Full Form -->
        <div class="hide-mobile p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-filter text-blue-600 text-sm"></i>
                    Filter Transaksi
                </h3>
                <?php if ($filterJenis || $filterStatus || $filterTanggal || $filterBulan || $search): ?>
                <a href="riwayat.php" class="text-xs text-blue-600 hover:underline flex items-center gap-1">
                    <i class="fas fa-times"></i> Hapus Filter
                </a>
                <?php endif; ?>
            </div>
            <form method="GET">
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Cari</label>
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Invoice, No. HP, Ref ID, atau SN..."
                                class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Jenis Transaksi</label>
                        <select name="jenis" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Semua Jenis</option>
                            <option value="pulsa"    <?= $filterJenis=='pulsa'?'selected':'' ?>>Pulsa</option>
                            <option value="kuota"    <?= $filterJenis=='kuota'?'selected':'' ?>>Paket Data</option>
                            <option value="listrik"  <?= $filterJenis=='listrik'?'selected':'' ?>>Token Listrik</option>
                            <option value="game"     <?= $filterJenis=='game'?'selected':'' ?>>Top Up Game</option>
                            <option value="transfer" <?= $filterJenis=='transfer'?'selected':'' ?>>Transfer</option>
                            <option value="deposit"  <?= $filterJenis=='deposit'?'selected':'' ?>>Deposit</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Semua Status</option>
                            <option value="success" <?= $filterStatus=='success'?'selected':'' ?>>Sukses</option>
                            <option value="pending" <?= $filterStatus=='pending'?'selected':'' ?>>Pending</option>
                            <option value="failed"  <?= $filterStatus=='failed'?'selected':'' ?>>Gagal</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Tanggal</label>
                        <input type="date" name="tanggal" value="<?= $filterTanggal ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Bulan</label>
                        <input type="month" name="bulan" value="<?= $filterBulan ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium flex items-center gap-1.5 transition">
                        <i class="fas fa-filter text-xs"></i> Terapkan Filter
                    </button>
                    <a href="riwayat.php" class="px-5 py-2 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg text-sm font-medium flex items-center gap-1.5 transition">
                        <i class="fas fa-sync-alt text-xs"></i> Reset
                    </a>
                    <button type="button" onclick="exportToCSV()" class="px-5 py-2 border border-blue-300 hover:bg-blue-50 text-blue-700 rounded-lg text-sm font-medium flex items-center gap-2 transition">
                        <i class="fas fa-file-csv text-xs"></i> Export CSV
                    </button>
                    <button type="button" onclick="exportToExcel()" class="ml-auto px-5 py-2 border border-green-300 hover:bg-green-50 text-green-700 rounded-lg text-sm font-medium flex items-center gap-2 transition">
                        <i class="fas fa-file-excel text-xs"></i> Export Excel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Transaction List ── -->
    <div class="card animate-slide-in delay-300 overflow-hidden">
        <!-- Card Header -->
        <div class="px-4 md:px-5 py-4 flex items-center justify-between flex-wrap gap-3 border-b border-gray-100">
            <div>
                <h3 class="font-semibold text-gray-900">Daftar Transaksi</h3>
                <p class="text-xs text-gray-400 mt-0.5"><?= $transaksi->num_rows ?> data ditemukan</p>
            </div>
            <!-- Mobile Export -->
            <div class="flex gap-2">
                <button onclick="exportToCSV()"
                    class="hide-desktop px-3 py-1.5 border border-blue-300 hover:bg-blue-50 text-blue-700 rounded-lg text-xs font-medium flex items-center gap-1.5 transition">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
                <button onclick="exportToExcel()"
                    class="hide-desktop px-3 py-1.5 border border-green-300 hover:bg-green-50 text-green-700 rounded-lg text-xs font-medium flex items-center gap-1.5 transition">
                    <i class="fas fa-file-excel"></i> Export
                </button>
            </div>
        </div>

        <?php if ($transaksi->num_rows > 0): ?>

        <!-- Desktop Table -->
        <div class="hide-mobile overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-header-row">
                        <?php if ($role == 'admin'): ?>
                        <th class="text-left">Invoice</th>
                        <?php endif; ?>
                        <th class="text-left">Tanggal</th>
                        <th class="text-left">Provider</th>
                        <th class="text-left">No. Tujuan</th>
                        <th class="text-left">Produk</th>
                        <th class="text-left">Total</th>
                        <th class="text-left">Ref ID</th>
                        <th class="text-left">SN</th>
                        <th class="text-left">Status</th>
                        <th class="text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $transaksi->data_seek(0);
                while ($trx = $transaksi->fetch_assoc()):
                ?>
                    <tr class="table-row">
                        <?php if ($role == 'admin'): ?>
                        <td class="font-mono text-xs text-gray-600 whitespace-nowrap"><?= htmlspecialchars($trx['no_invoice']) ?></td>
                        <?php endif; ?>
                        <td class="text-xs text-gray-500 whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($trx['created_at'])) ?></td>
                        <td class="text-xs whitespace-nowrap">
                            <?php
                            $provider = $trx['provider'] ?? '';
                            $providerClass = '';
                            if ($provider === 'Telkomsel') $providerClass = 'background:#e2001a;color:white;';
                            elseif ($provider === 'XL') $providerClass = 'background:#009a0e;color:white;';
                            elseif ($provider === 'Indosat') $providerClass = 'background:#ff9600;color:white;';
                            elseif ($provider === 'Tri') $providerClass = 'background:#8b418b;color:white;';
                            ?>
                            <span style="<?= $providerClass ?: 'background:#f1f5f9;color:#64748b;' ?>padding:0.2rem 0.5rem;border-radius:999px;font-weight:600;font-size:0.7rem;">
                                <?= htmlspecialchars($provider ?: $trx['jenis_transaksi']) ?>
                            </span>
                        </td>
                        <td class="font-mono text-xs whitespace-nowrap"><?= htmlspecialchars($trx['no_tujuan']) ?></td>
                        <td class="max-w-[120px] truncate text-xs"><?= htmlspecialchars($trx['nama_produk'] ?? '-') ?></td>
                        <td class="font-semibold whitespace-nowrap text-xs"><?= rupiah($trx['total_bayar']) ?></td>
                        <td class="font-mono text-xs text-gray-500 whitespace-nowrap" title="Ref ID Digiflazz">
                            <?= htmlspecialchars($trx['ref_id'] ?? '-') ?>
                        </td>
                        <td class="font-mono text-xs whitespace-nowrap" title="Serial Number">
                            <?php
                            $sn = $trx['server_id'] ?? '';
                            echo $sn ? htmlspecialchars($sn) : '<span class="text-gray-300">-</span>';
                            ?>
                        </td>
                        <td class="whitespace-nowrap">
                            <span class="badge status-<?= $trx['status'] ?>"><?= ucfirst($trx['status']) ?></span>
                        </td>
                        <td class="whitespace-nowrap">
                            <a href="detail_transaksi.php?id=<?= $trx['id'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-medium transition" title="Lihat Detail">
                                <i class="fas fa-eye"></i> Detail
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards -->
        <div class="hide-desktop">
        <?php
        $transaksi->data_seek(0);
        while ($trx = $transaksi->fetch_assoc()):
        ?>
            <div class="trx-card">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center flex-wrap gap-1.5">
                        <?php if ($role == 'admin'): ?>
                        <span class="font-mono text-xs text-gray-600"><?= htmlspecialchars($trx['no_invoice']) ?></span>
                        <?php endif; ?>
                        <span class="badge status-<?= $trx['status'] ?>"><?= ucfirst($trx['status']) ?></span>
                    </div>
                    <span class="font-bold text-sm text-blue-700 whitespace-nowrap"><?= rupiah($trx['total_bayar']) ?></span>
                </div>
                <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                    <div>
                        <p class="text-gray-400">Provider</p>
                        <p class="text-gray-700 font-medium"><?= htmlspecialchars($trx['provider'] ?: ucfirst($trx['jenis_transaksi'])) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400">No. Tujuan</p>
                        <p class="font-mono text-gray-700"><?= htmlspecialchars($trx['no_tujuan']) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400">Produk</p>
                        <p class="text-gray-700 truncate"><?= htmlspecialchars($trx['nama_produk'] ?? '-') ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400">SN</p>
                        <p class="font-mono text-gray-700 truncate"><?= htmlspecialchars($trx['server_id'] ?? '-') ?></p>
                    </div>
                    <?php if ($role == 'admin'): ?>
                    <div class="col-span-2">
                        <p class="text-gray-400">Ref ID</p>
                        <p class="font-mono text-gray-600 truncate"><?= htmlspecialchars($trx['ref_id'] ?? '-') ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                    <p class="text-xs text-gray-400">
                        <i class="fas fa-clock mr-1"></i><?= date('d M Y, H:i', strtotime($trx['created_at'])) ?>
                    </p>
                    <a href="detail_transaksi.php?id=<?= $trx['id'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-medium transition">
                        <i class="fas fa-eye"></i> Detail
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
        </div>

        <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-16 px-4">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-inbox text-gray-300 text-3xl"></i>
            </div>
            <h3 class="font-semibold text-gray-700 mb-1">Tidak ada transaksi</h3>
            <p class="text-sm text-gray-400 mb-5">Tidak ada data yang sesuai dengan filter.</p>
            <a href="riwayat.php"
               class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition">
                <i class="fas fa-sync-alt"></i> Reset Filter
            </a>
        </div>
        <?php endif; ?>
    </div>

</div><!-- End page content wrapper -->

<!-- ═══════════════════════════════════════════
     RIWAYAT - JavaScript
═══════════════════════════════════════════ -->
<script>
// ═══════════════════════════════════════════
// AUTO REFRESH PENDING TRANSACTIONS (AJAX POLLING)
// ═══════════════════════════════════════════
let pendingCheckInterval = null;
let lastCheckTime = null;

function getPendingTransactionIds() {
    const ids = [];

    // Desktop table rows
    document.querySelectorAll('tr.table-row').forEach(row => {
        const badge = row.querySelector('.badge.status-pending');
        if (badge) {
            const link = row.querySelector('a[href*="detail_transaksi.php"]');
            if (link) {
                const url = new URL(link.href, window.location.origin);
                ids.push(url.searchParams.get('id'));
            }
        }
    });

    // Mobile cards
    document.querySelectorAll('.trx-card').forEach(card => {
        const badge = card.querySelector('.badge.status-pending');
        if (badge) {
            const link = card.querySelector('a[href*="detail_transaksi.php"]');
            if (link) {
                const url = new URL(link.href, window.location.origin);
                ids.push(url.searchParams.get('id'));
            }
        }
    });

    return [...new Set(ids)]; // Remove duplicates
}

function checkPendingTransactions() {
    const pendingIds = getPendingTransactionIds();

    if (pendingIds.length === 0) {
        stopPendingCheck();
        return;
    }

    // Show checking indicator on all pending badges
    document.querySelectorAll('.badge.status-pending').forEach(badge => {
        badge.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i>';
        badge.style.opacity = '0.6';
    });

    // Update last check indicator
    if (lastCheckTime) {
        const el = document.getElementById('lastCheckTime');
        if (el) el.textContent = 'Mengecek...';
    }

    fetch('api_check_status.php?ids=' + pendingIds.join(','), {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
    })
    .then(res => {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    })
    .then(data => {
        if (data.success && data.results) {
            let changedCount = 0;
            data.results.forEach(result => {
                if (updatePendingRow(result)) changedCount++;
            });

            // Show toast if some changed
            if (changedCount > 0) {
                const successCount = data.results.filter(r => r.status === 'success').length;
                if (successCount > 0) {
                    showToast(`${successCount} transaksi berhasil!`, 'success');
                }
            }
        }

        // Update last check time
        lastCheckTime = new Date();
        const el = document.getElementById('lastCheckTime');
        if (el) el.textContent = 'Terakhir cek: ' + formatTime(lastCheckTime);

        // Reset badges
        resetPendingBadges();

        // Stop if no more pending
        if (getPendingTransactionIds().length === 0) {
            stopPendingCheck();
            showToast('Semua transaksi pending sudah diproses!', 'success');
        }
    })
    .catch(err => {
        console.error('Check pending error:', err);
        resetPendingBadges();
        const el = document.getElementById('lastCheckTime');
        if (el) el.textContent = 'Error koneksi, retry 30 detik lagi...';
    });
}

function resetPendingBadges() {
    document.querySelectorAll('.badge.status-pending').forEach(badge => {
        badge.innerHTML = '<i class="fas fa-clock"></i> Pending';
        badge.style.opacity = '1';
    });
}

function updatePendingRow(result) {
    let updated = false;

    // Desktop row
    document.querySelectorAll('tr.table-row').forEach(row => {
        const link = row.querySelector(`a[href="detail_transaksi.php?id=${result.id}"]`);
        if (!link) return;

        const statusTd = row.querySelector('td:nth-child(7)');

        if (result.status === 'success') {
            if (statusTd) statusTd.innerHTML = '<span class="badge status-success"><i class="fas fa-check-circle"></i> Sukses</span>';
            row.style.background = '#ecfdf5';
            updated = true;
        } else if (result.status === 'failed') {
            if (statusTd) statusTd.innerHTML = '<span class="badge status-failed"><i class="fas fa-times-circle"></i> Gagal</span>';
            row.style.background = '#fef2f2';
            updated = true;
        } else if (result.status === 'pending' && result.is_suspect) {
            if (statusTd) statusTd.innerHTML = '<span class="badge" style="background:#fef3c7;color:#92400e;"><i class="fas fa-exclamation-triangle"></i> Suspect</span>';
            updated = true;
        }
    });

    // Mobile card
    document.querySelectorAll('.trx-card').forEach(card => {
        const link = card.querySelector(`a[href="detail_transaksi.php?id=${result.id}"]`);
        if (!link) return;

        const parent = card;
        // Find badge container
        const badgeEls = parent.querySelectorAll('.badge');
        badgeEls.forEach(badge => {
            if (result.status === 'success') {
                if (badge.classList.contains('status-pending')) {
                    badge.className = 'badge status-success';
                    badge.innerHTML = '<i class="fas fa-check-circle"></i> Sukses';
                }
                parent.style.background = '#ecfdf5';
                updated = true;
            } else if (result.status === 'failed') {
                if (badge.classList.contains('status-pending')) {
                    badge.className = 'badge status-failed';
                    badge.innerHTML = '<i class="fas fa-times-circle"></i> Gagal';
                }
                parent.style.background = '#fef2f2';
                updated = true;
            }
        });
    });

    return updated;
}

function formatTime(date) {
    return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function startPendingCheck() {
    if (pendingCheckInterval) return;
    checkPendingTransactions();
    pendingCheckInterval = setInterval(checkPendingTransactions, 30000);
}

function stopPendingCheck() {
    if (pendingCheckInterval) {
        clearInterval(pendingCheckInterval);
        pendingCheckInterval = null;
    }
}

// Start auto-check if there are pending transactions
document.addEventListener('DOMContentLoaded', function() {
    const hasPending = document.querySelectorAll('.badge.status-pending').length > 0;
    if (hasPending) {
        startPendingCheck();

        // Add auto-refresh indicator
        const header = document.querySelector('.card .px-4, .card .px-5');
        if (header) {
            const indicator = document.createElement('div');
            indicator.id = 'autoRefreshBadge';
            indicator.style.cssText = 'display:inline-flex;align-items:center;gap:0.5rem;padding:0.25rem 0.75rem;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:2rem;font-size:0.75rem;color:#065f46;font-weight:500;margin-left:auto;';
            indicator.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Auto-refresh aktif <span id="lastCheckTime" style="color:#059669;font-weight:700;"></span>';
            header.appendChild(indicator);
        }
    }

    window.addEventListener('beforeunload', stopPendingCheck);
});
</script>

<script>
// ═══════════════════════════════════════════
// FILTER TOGGLE (mobile)
// ═══════════════════════════════════════════
function toggleFilter() {
    const content  = document.getElementById('filterContent');
    const icon     = document.getElementById('filterIcon');
    const isHidden = content.classList.contains('hidden');

    if (isHidden) {
        content.classList.remove('hidden');
        icon.classList.add('icon-rotated');
    } else {
        content.classList.add('hidden');
        icon.classList.remove('icon-rotated');
    }
}

// ═══════════════════════════════════════════
// AUTO-SUBMIT FILTER on change (desktop only)
// ═══════════════════════════════════════════
document.querySelectorAll('.hide-mobile form[method="GET"] select, .hide-mobile form[method="GET"] input[type="date"], .hide-mobile form[method="GET"] input[type="month"]')
    .forEach(el => el.addEventListener('change', function() {
        this.closest('form').submit();
    }));

// ═══════════════════════════════════════════
// EXPORT TO CSV
// ═══════════════════════════════════════════
function exportToCSV() {
    const table = document.querySelector('table');
    if (!table) { 
        showToast('Tidak ada data untuk diexport', 'error'); 
        return; 
    }

    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    for (const row of rows) {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        for (const col of cols) {
            // Get text content and clean up
            let text = col.innerText.replace(/"/g, '""').trim();
            rowData.push(`"${text}"`);
        }
        csv.push(rowData.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `riwayat-transaksi-${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    showToast('Export CSV berhasil!', 'success');
}

// ═══════════════════════════════════════════
// EXPORT TO EXCEL
// ═══════════════════════════════════════════
function exportToExcel() {
    const table = document.querySelector('table');
    if (!table) { 
        showToast('Tidak ada data untuk diexport', 'error'); 
        return; 
    }

    const html = `<html><head><style>
        table{border-collapse:collapse;width:100%}
        th,td{border:1px solid #ddd;padding:8px;text-align:left}
        th{background:#2f2f2f;font-weight:bold;color:#f2f2f2}
    </style></head><body>
        <h2>Riwayat Transaksi PPOB Express</h2>
        <p>Tanggal Export: ${new Date().toLocaleDateString('id-ID')}</p>
        ${table.outerHTML}
    </body></html>`;

    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `riwayat-transaksi-${new Date().toISOString().split('T')[0]}.xls`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    showToast('Export Excel berhasil!', 'success');
}

// ═══════════════════════════════════════════
// TOAST NOTIFICATION
// ═══════════════════════════════════════════
function showToast(message, type = 'info') {
    const existing = document.getElementById('toastNotif');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.id = 'toastNotif';
    toast.style.cssText = 'position:fixed;top:80px;right:20px;z-index:9999;min-width:280px;max-width:400px;padding:1rem 1.25rem;border-radius:0.5rem;display:flex;align-items:center;gap:0.75rem;animation:slideIn 0.3s ease;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
    
    if (type === 'success') {
        toast.style.background = '#10b981';
        toast.style.color = 'white';
        toast.innerHTML = '<i class="fas fa-check-circle"></i><span>' + message + '</span>';
    } else if (type === 'error') {
        toast.style.background = '#ef4444';
        toast.style.color = 'white';
        toast.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>' + message + '</span>';
    } else {
        toast.style.background = '#3b82f6';
        toast.style.color = 'white';
        toast.innerHTML = '<i class="fas fa-info-circle"></i><span>' + message + '</span>';
    }
    
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}
</script>

<?php 
include 'layout_footer.php';
$conn->close();
?>