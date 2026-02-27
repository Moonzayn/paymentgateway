<?php
require_once 'config.php';
cekLogin();

// Hanya admin yang bisa akses
if ($_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

$conn = koneksi();
$id_user = $_SESSION['user_id'];

// ═══ Layout Variables ═══
$pageTitle   = 'Laporan Transaksi';
$pageIcon    = 'fas fa-chart-bar';
$pageDesc    = 'Analisa data transaksi PPOB';
$currentPage = 'laporan';
$additionalHeadScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

// Default date range
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['type'] ?? 'summary';

// Export functionality
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="laporan_' . $start_date . '_' . $end_date . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Invoice', 'User', 'Jenis', 'Tujuan', 'Nominal', 'Total', 'Status', 'Tanggal']);
    
    $stmt = $conn->prepare("SELECT t.*, u.nama_lengkap 
                           FROM transaksi t 
                           JOIN users u ON t.user_id = u.id 
                           WHERE DATE(t.created_at) BETWEEN ? AND ?
                           ORDER BY t.created_at DESC");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['no_invoice'],
            $row['nama_lengkap'],
            $row['jenis_transaksi'],
            $row['no_tujuan'],
            $row['nominal'],
            $row['total_bayar'],
            $row['status'],
            $row['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// Statistics for date range
$stats = [
    'total_transaksi' => 0,
    'total_pendapatan' => 0,
    'total_profit' => 0,
    'by_type' => [],
    'by_status' => []
];

// Total transactions
$query = "SELECT COUNT(*) as total, COALESCE(SUM(total_bayar), 0) as pendapatan 
          FROM transaksi 
          WHERE DATE(created_at) BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stats['total_transaksi'] = $row['total'];
$stats['total_pendapatan'] = $row['pendapatan'];

// Profit calculation
$query = "SELECT COALESCE(SUM(t.total_bayar - COALESCE(p.harga_modal, 0)), 0) as profit 
          FROM transaksi t 
          LEFT JOIN produk p ON t.produk_id = p.id 
          WHERE DATE(t.created_at) BETWEEN ? AND ? AND t.status = 'success'";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$stats['total_profit'] = $stmt->get_result()->fetch_assoc()['profit'];

// By transaction type
$query = "SELECT jenis_transaksi, COUNT(*) as jumlah, COALESCE(SUM(total_bayar), 0) as total 
          FROM transaksi 
          WHERE DATE(created_at) BETWEEN ? AND ? 
          GROUP BY jenis_transaksi";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $stats['by_type'][$row['jenis_transaksi']] = $row;
}

// By status
$query = "SELECT status, COUNT(*) as jumlah, COALESCE(SUM(total_bayar), 0) as total 
          FROM transaksi 
          WHERE DATE(created_at) BETWEEN ? AND ? 
          GROUP BY status";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $stats['by_status'][$row['status']] = $row;
}

// Transaction list
$limit = $_GET['limit'] ?? 50;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $limit;

$query = "SELECT t.*, u.nama_lengkap, u.username, p.nama_produk 
          FROM transaksi t 
          JOIN users u ON t.user_id = u.id 
          LEFT JOIN produk p ON t.produk_id = p.id 
          WHERE DATE(t.created_at) BETWEEN ? AND ?
          ORDER BY t.created_at DESC 
          LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssii", $start_date, $end_date, $limit, $offset);
$stmt->execute();
$transaksi = $stmt->get_result();

// Total pages
$countQuery = "SELECT COUNT(*) as total FROM transaksi WHERE DATE(created_at) BETWEEN ? AND ?";
$stmt = $conn->prepare($countQuery);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Top products
$topProducts = $conn->query("SELECT p.nama_produk, p.provider, COUNT(t.id) as jumlah, SUM(t.total_bayar) as total
                            FROM transaksi t
                            JOIN produk p ON t.produk_id = p.id
                            WHERE DATE(t.created_at) BETWEEN '$start_date' AND '$end_date' AND t.status = 'success'
                            GROUP BY t.produk_id
                            ORDER BY total DESC
                            LIMIT 10");

// Top users
$topUsers = $conn->query("SELECT u.id, u.username, u.nama_lengkap, COUNT(t.id) as jumlah, SUM(t.total_bayar) as total
                          FROM transaksi t
                          JOIN users u ON t.user_id = u.id
                          WHERE DATE(t.created_at) BETWEEN '$start_date' AND '$end_date' AND t.status = 'success'
                          GROUP BY t.user_id
                          ORDER BY total DESC
                          LIMIT 10");

// Daily transactions for chart
$dailyData = $conn->query("SELECT DATE(created_at) as tanggal, COUNT(*) as jumlah, SUM(total_bayar) as total 
                            FROM transaksi 
                            WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' AND status = 'success'
                            GROUP BY DATE(created_at)
                            ORDER BY tanggal");

// Prepare chart data
$chartLabels = [];
$chartValues = [];
while ($row = $dailyData->fetch_assoc()) {
    $chartLabels[] = date('d M', strtotime($row['tanggal']));
    $chartValues[] = $row['total'];
}

$alert = getAlert();
$_SESSION['saldo'] = getSaldo($id_user);

// Include layout
include 'layout.php';
?>

<!-- ═══════════════════════════════════════════
     LAPORAN - Custom Styles
═══════════════════════════════════════════ -->
<style>
:root {
    --primary-blue: #2563eb;
    --secondary-blue: #3b82f6;
    --light-blue: #eff6ff;
    --success-green: #10b981;
    --warning-yellow: #f59e0b;
    --danger-red: #ef4444;
    --purple-600: #7c3aed;
}

/* ── Badge ── */
.badge {
    display: inline-block;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 600;
    white-space: nowrap;
}

.badge-success {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.badge-pending {
    background-color: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}

.badge-failed {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* ── Card ── */
.card {
    background: white;
    border-radius: 0.75rem;
    border: 1px solid #e8ecf0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* ── Stat Card ── */
.stat-card {
    border-radius: 0.75rem;
    border-left-width: 4px;
    background: white;
    padding: 1rem 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-card.blue { border-left-color: var(--primary-blue); }
.stat-card.green { border-left-color: var(--success-green); }
.stat-card.purple { border-left-color: var(--purple-600); }
.stat-card.yellow { border-left-color: var(--warning-yellow); }

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
.table-row:hover td {
    background: #f8fafc;
}
.table-row:last-child td {
    border-bottom: none;
}

/* ── Input Field ── */
.input-field {
    transition: all 0.2s ease;
}
.input-field:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* ── Button Primary ── */
.btn-primary {
    background-color: var(--primary-blue);
    color: white;
    transition: all 0.2s ease;
}
.btn-primary:hover {
    background-color: #1d4ed8;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* ── Alert ── */
.alert {
    animation: slideIn 0.3s ease;
}
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

/* ── Animation Delays ── */
.delay-100 { animation-delay: 0.1s; }
.delay-200 { animation-delay: 0.2s; }
.delay-300 { animation-delay: 0.3s; }
.delay-400 { animation-delay: 0.4s; }

/* ── Quick Filter Button ── */
.quick-filter {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
    background-color: #f3f4f6;
    color: #4b5563;
    border-radius: 9999px;
    transition: all 0.2s ease;
    text-decoration: none;
}
.quick-filter:hover {
    background-color: #e5e7eb;
    color: #1f2937;
}
.quick-filter.blue {
    background-color: #dbeafe;
    color: #1e40af;
}
.quick-filter.blue:hover {
    background-color: #bfdbfe;
}

/* ── Chart Container ── */
.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

/* ── Icon Circle ── */
.icon-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ── Pagination ── */
.pagination-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.375rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    color: #374151;
    background-color: white;
    transition: all 0.2s ease;
}
.pagination-link:hover {
    background-color: #f9fafb;
    border-color: #9ca3af;
}
.pagination-link.active {
    background-color: var(--primary-blue);
    color: white;
    border-color: var(--primary-blue);
}
</style>

<!-- ═══════════════════════════════════════════
     LAPORAN - Content
═══════════════════════════════════════════ -->

<!-- Page Title (using welcome-section style from dashboard) -->

<!-- Alert Message -->
<?php if ($alert): ?>
<div class="alert mb-6 p-4 rounded-lg flex items-center gap-3 <?= $alert['type'] == 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>" style="animation: slideIn 0.3s ease;">
    <i class="fas <?= $alert['type'] == 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> text-lg"></i>
    <span class="font-medium"><?= $alert['message'] ?></span>
</div>
<?php endif; ?>

<!-- Filter Section -->
<div class="card p-6 mb-6 animate-slide-in delay-100">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
            <input type="date" name="start_date" value="<?= $start_date ?>" 
                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
            <input type="date" name="end_date" value="<?= $end_date ?>" 
                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tampilkan</label>
            <select name="limit" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10 Data</option>
                <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25 Data</option>
                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 Data</option>
                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100 Data</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn-primary flex-1 py-2 px-4 rounded-lg font-medium flex items-center justify-center gap-2">
                <i class="fas fa-filter"></i>
                Filter
            </button>
            <a href="laporan.php" class="py-2 px-4 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                Reset
            </a>
        </div>
    </form>
    
    <!-- Quick Date Filters -->
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="?start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-d') ?>" 
           class="quick-filter blue">
            <i class="fas fa-calendar-alt mr-1"></i> Bulan Ini
        </a>
        <a href="?start_date=<?= date('Y-m-d', strtotime('-7 days')) ?>&end_date=<?= date('Y-m-d') ?>" 
           class="quick-filter">
            <i class="fas fa-calendar-week mr-1"></i> 7 Hari
        </a>
        <a href="?start_date=<?= date('Y-m-d', strtotime('-30 days')) ?>&end_date=<?= date('Y-m-d') ?>" 
           class="quick-filter">
            <i class="fas fa-calendar-alt mr-1"></i> 30 Hari
        </a>
        <a href="?start_date=<?= date('Y-01-01') ?>&end_date=<?= date('Y-12-31') ?>" 
           class="quick-filter">
            <i class="fas fa-calendar-year mr-1"></i> Tahun Ini
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 animate-slide-in delay-200">
    <div class="stat-card blue">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Total Transaksi</p>
                <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_transaksi']) ?></p>
            </div>
            <div class="icon-circle bg-blue-50">
                <i class="fas fa-receipt text-blue-600"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card green">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Total Pendapatan</p>
                <p class="text-2xl font-bold text-green-600"><?= rupiah($stats['total_pendapatan']) ?></p>
            </div>
            <div class="icon-circle bg-green-50">
                <i class="fas fa-money-bill text-green-600"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card purple">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Total Profit</p>
                <p class="text-2xl font-bold text-purple-600"><?= rupiah($stats['total_profit']) ?></p>
            </div>
            <div class="icon-circle bg-purple-50">
                <i class="fas fa-chart-line text-purple-600"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card yellow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Rata-rata Transaksi</p>
                <p class="text-2xl font-bold text-yellow-600">
                    <?= $stats['total_transaksi'] > 0 ? rupiah($stats['total_pendapatan'] / $stats['total_transaksi']) : rupiah(0) ?>
                </p>
            </div>
            <div class="icon-circle bg-yellow-50">
                <i class="fas fa-calculator text-yellow-600"></i>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Tables Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 animate-slide-in delay-300">
    <!-- Transactions by Type -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-pie text-blue-600"></i>
            Transaksi per Jenis
        </h3>
        <div class="space-y-3">
            <?php foreach ($stats['by_type'] as $type => $data): ?>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="icon-circle bg-gray-100">
                        <i class="fas <?= $type == 'pulsa' ? 'fa-mobile-alt' : ($type == 'kuota' ? 'fa-wifi' : ($type == 'listrik' ? 'fa-bolt' : 'fa-money-bill-transfer')) ?> text-gray-600"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 capitalize"><?= $type ?></p>
                        <p class="text-sm text-gray-500"><?= number_format($data['jumlah']) ?> transaksi</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-semibold text-gray-900"><?= rupiah($data['total']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($stats['by_type'])): ?>
            <p class="text-gray-500 text-center py-4">Tidak ada data</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Transactions by Status -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-bar text-blue-600"></i>
            Transaksi per Status
        </h3>
        <div class="space-y-3">
            <?php foreach ($stats['by_status'] as $status => $data): ?>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="icon-circle <?= $status == 'success' ? 'bg-green-100' : ($status == 'pending' ? 'bg-yellow-100' : 'bg-red-100') ?>">
                        <i class="fas <?= $status == 'success' ? 'fa-check' : ($status == 'pending' ? 'fa-clock' : 'fa-times') ?> <?= $status == 'success' ? 'text-green-600' : ($status == 'pending' ? 'text-yellow-600' : 'text-red-600') ?>"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 capitalize"><?= $status ?></p>
                        <p class="text-sm text-gray-500"><?= number_format($data['jumlah']) ?> transaksi</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-semibold text-gray-900"><?= rupiah($data['total']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($stats['by_status'])): ?>
            <p class="text-gray-500 text-center py-4">Tidak ada data</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Top Products & Users -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 animate-slide-in delay-300">
    <!-- Top Products -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="fas fa-crown text-yellow-500"></i>
            Produk Terlaris
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="table-header-row">
                    <tr>
                        <th class="text-left">Produk</th>
                        <th class="text-right">Terjual</th>
                        <th class="text-right">Pendapatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php while ($product = $topProducts->fetch_assoc()): ?>
                    <tr class="table-row">
                        <td class="px-3 py-2">
                            <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($product['nama_produk']) ?></p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($product['provider']) ?></p>
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900"><?= number_format($product['jumlah']) ?></td>
                        <td class="px-3 py-2 text-right text-green-600 font-medium"><?= rupiah($product['total']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($topProducts->num_rows == 0): ?>
                    <tr>
                        <td colspan="3" class="px-3 py-4 text-center text-gray-500">Tidak ada data</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Top Users -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="fas fa-trophy text-yellow-500"></i>
            User Terbanyak Transaksi
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="table-header-row">
                    <tr>
                        <th class="text-left">User</th>
                        <th class="text-right">Transaksi</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php while ($user = $topUsers->fetch_assoc()): ?>
                    <tr class="table-row">
                        <td class="px-3 py-2">
                            <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($user['nama_lengkap']) ?></p>
                            <p class="text-xs text-gray-500">@<?= htmlspecialchars($user['username']) ?></p>
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900"><?= number_format($user['jumlah']) ?></td>
                        <td class="px-3 py-2 text-right text-green-600 font-medium"><?= rupiah($user['total']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($topUsers->num_rows == 0): ?>
                    <tr>
                        <td colspan="3" class="px-3 py-4 text-center text-gray-500">Tidak ada data</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart Section -->
<?php if (!empty($chartLabels) && !empty($chartValues)): ?>
<div class="card p-6 mb-6 animate-slide-in delay-400">
    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
        <i class="fas fa-chart-line text-blue-600"></i>
        Grafik Transaksi Harian
    </h3>
    <div class="chart-container">
        <canvas id="transaksiChart"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- Transaction List -->
<div class="card overflow-hidden animate-slide-in delay-400">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-wrap gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Daftar Transaksi</h3>
            <p class="text-sm text-gray-500"><?= number_format($totalRecords) ?> total transaksi</p>
        </div>
        <div class="flex gap-2">
            <a href="?export=csv&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
               class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 transition flex items-center gap-2">
                <i class="fas fa-file-csv"></i>
                Export CSV
            </a>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="table-header-row">
                    <th class="text-left">Invoice</th>
                    <th class="text-left">User</th>
                    <th class="text-left">Jenis</th>
                    <th class="text-left">Tujuan</th>
                    <th class="text-right">Nominal</th>
                    <th class="text-right">Total</th>
                    <th class="text-center">Status</th>
                    <th class="text-left">Tanggal</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($t = $transaksi->fetch_assoc()): ?>
                <tr class="table-row">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="font-mono text-sm text-blue-600"><?= htmlspecialchars($t['no_invoice']) ?></span>
                    </td>
                    <td class="px-6 py-4">
                        <p class="font-medium text-gray-900"><?= htmlspecialchars($t['nama_lengkap']) ?></p>
                        <p class="text-xs text-gray-500">@<?= htmlspecialchars($t['username']) ?></p>
                    </td>
                    <td class="px-6 py-4">
                        <span class="capitalize"><?= $t['jenis_transaksi'] ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap font-mono text-sm">
                        <?= htmlspecialchars($t['no_tujuan']) ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <?= rupiah($t['nominal']) ?>
                    </td>
                    <td class="px-6 py-4 text-right font-semibold text-gray-900">
                        <?= rupiah($t['total_bayar']) ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="badge badge-<?= $t['status'] ?>">
                            <?= ucfirst($t['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= date('d/m/Y H:i', strtotime($t['created_at'])) ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($transaksi->num_rows == 0): ?>
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-3"></i>
                        <p>Tidak ada transaksi dalam periode ini</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-4 border-t border-gray-200">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">
                Halaman <?= $page ?> dari <?= $totalPages ?>
            </p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&limit=<?= $limit ?>" 
                   class="pagination-link">
                    <i class="fas fa-chevron-left mr-1"></i> Previous
                </a>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                <a href="?page=<?= $i ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&limit=<?= $limit ?>" 
                   class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&limit=<?= $limit ?>" 
                   class="pagination-link">
                    Next <i class="fas fa-chevron-right ml-1"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════
     LAPORAN - JavaScript
═══════════════════════════════════════════ -->
<script>
// Chart initialization
<?php if (!empty($chartLabels) && !empty($chartValues)): ?>
const ctx = document.getElementById('transaksiChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Total Transaksi (Rp)',
            data: <?= json_encode($chartValues) ?>,
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            borderColor: 'rgba(37, 99, 235, 0.8)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#2563eb',
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
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
                    callback: function(value) {
                        if (value >= 1000000) return 'Rp ' + (value / 1000000).toFixed(1) + 'jt';
                        if (value >= 1000) return 'Rp ' + (value / 1000).toFixed(0) + 'rb';
                        return 'Rp ' + value;
                    }
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});
<?php endif; ?>

// Auto-submit filter on date change
document.querySelectorAll('input[name="start_date"], input[name="end_date"], select[name="limit"]').forEach(el => {
    el.addEventListener('change', function() {
        this.closest('form').submit();
    });
});

// Debounce for filter
let filterTimeout;
document.querySelector('form')?.addEventListener('input', function(e) {
    if (e.target.name === 'search') {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            this.submit();
        }, 500);
    }
});
</script>

<?php 
include 'layout_footer.php';
$conn->close();
?>