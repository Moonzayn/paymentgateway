<?php
require_once 'config.php';
cekLogin();

// Hanya admin yang bisa akses
if ($_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

$conn = koneksi();

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
$query = "SELECT COALESCE(SUM(t.total_bayar - COALESCE(p.harga_modal * t.nominal, 0)), 0) as profit 
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

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - PPOB Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #3b82f6;
            --light-blue: #eff6ff;
        }
        
        .sidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }
        
        .sidebar.closed {
            transform: translateX(-100%);
        }
        
        @media (min-width: 768px) {
            .sidebar {
                transform: translateX(0);
            }
            .sidebar.closed {
                transform: translateX(0);
            }
        }
        
        .menu-item {
            transition: all 0.2s ease;
            border-radius: 0.5rem;
        }
        
        .menu-item.active {
            background-color: var(--light-blue);
            color: var(--primary-blue);
            font-weight: 600;
            border-left: 4px solid var(--primary-blue);
        }
        
        .menu-item:hover:not(.active) {
            background-color: #f8fafc;
            color: var(--primary-blue);
        }
        
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        
        .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-failed {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .table-header {
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .table-row {
            transition: background-color 0.2s ease;
        }
        
        .table-row:hover {
            background-color: #f9fafb;
        }
        
        .sticky-header {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        /* Toggle Button Animation */
        .toggle-btn {
            transition: transform 0.2s ease, background-color 0.2s ease;
        }
        
        .toggle-btn:active {
            transform: scale(0.95);
        }
        
        .sidebar {
            transition: transform 0.3s ease-in-out, width 0.3s ease-in-out;
        }
        
        .sidebar.hidden {
            transform: translateX(-100%);
            width: 0;
            padding: 0;
            overflow: hidden;
        }
        
        .sidebar-backdrop {
            transition: opacity 0.3s ease-in-out;
        }
        
        .sidebar-backdrop.hidden {
            opacity: 0;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar fixed md:relative z-30 w-64 h-full bg-white border-r border-gray-200 flex flex-col">
            <div class="p-5 border-b border-gray-100">
                <h1 class="text-xl font-bold flex items-center gap-2 text-gray-800">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-wallet text-white text-sm"></i>
                    </div>
                    <span>PPOB<span class="text-blue-600">Express</span></span>
                </h1>
            </div>
            
            <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
                <a href="index.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
                    <i class="fas fa-home w-5"></i>
                    <span>Dashboard</span>
                </a>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <div class="pt-4 mt-4 border-t border-gray-100">
                    <p class="px-4 text-xs text-gray-500 uppercase tracking-wider mb-2 font-semibold">Admin Menu</p>
                    <a href="kelola_user.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
                        <i class="fas fa-users w-5"></i>
                        <span>Kelola User</span>
                    </a>
                    <a href="kelola_produk.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
                        <i class="fas fa-box w-5"></i>
                        <span>Kelola Produk</span>
                    </a>
                    <a href="laporan.php" class="menu-item active flex items-center gap-3 px-4 py-3 text-gray-700">
                        <i class="fas fa-chart-bar w-5"></i>
                        <span>Laporan</span>
                    </a>
                </div>
                <?php endif; ?>
                <a href="pulsa.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
                    <i class="fas fa-mobile-alt w-5"></i>
                    <span>Isi Pulsa</span>
                </a>
                <a href="kuota.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
                    <i class="fas fa-wifi w-5"></i>
                    <span>Paket Data</span>
                </a>
                <a href="listrik.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
                    <i class="fas fa-bolt w-5"></i>
                    <span>Token Listrik</span>
                </a>
                <a href="transfer.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
                    <i class="fas fa-money-bill-transfer w-5"></i>
                    <span>Transfer Tunai</span>
                </a>
                <a href="riwayat.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
                    <i class="fas fa-history w-5"></i>
                    <span>Riwayat Transaksi</span>
                </a>
                <a href="deposit.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
                    <i class="fas fa-plus-circle w-5"></i>
                    <span>Deposit Saldo</span>
                </a>
            </nav>
            
            <div class="p-4 border-t border-gray-100 bg-gray-50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-blue-600"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-800 truncate"><?= $_SESSION['nama_lengkap'] ?></p>
                        <p class="text-xs text-gray-500"><?= ucfirst($_SESSION['role']) ?></p>
                    </div>
                    <a href="logout.php" class="text-gray-500 hover:text-blue-600 transition" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </aside>
        
        <div id="overlay" class="fixed inset-0 bg-black/30 z-20 hidden md:hidden" onclick="toggleSidebar()"></div>
        
        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <header class="bg-white shadow-sm sticky top-0 z-10 sticky-header">
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()" class="text-gray-600 hover:text-blue-600 p-2 rounded-lg hover:bg-gray-100 transition" title="Toggle Sidebar">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">Laporan Transaksi</h2>
                            <p class="text-sm text-gray-500">Analisa data transaksi PPOB</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&export=csv" 
                           class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 transition flex items-center gap-2">
                            <i class="fas fa-download"></i>
                            Export CSV
                        </a>
                    </div>
                </div>
            </header>
            
            <div class="p-4 md:p-6 max-w-7xl mx-auto">
                <!-- Filter Section -->
                <div class="card p-6 mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                            <input type="date" name="start_date" value="<?= $start_date ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
                            <input type="date" name="end_date" value="<?= $end_date ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tampilkan</label>
                            <select name="limit" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10 Data</option>
                                <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25 Data</option>
                                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 Data</option>
                                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100 Data</option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="flex-1 py-2 px-4 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition flex items-center justify-center gap-2">
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
                           class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-full hover:bg-blue-200">
                            Bulan Ini
                        </a>
                        <a href="?start_date=<?= date('Y-m-d', strtotime('-7 days')) ?>&end_date=<?= date('Y-m-d') ?>" 
                           class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-full hover:bg-gray-200">
                            7 Hari
                        </a>
                        <a href="?start_date=<?= date('Y-m-d', strtotime('-30 days')) ?>&end_date=<?= date('Y-m-d') ?>" 
                           class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-full hover:bg-gray-200">
                            30 Hari
                        </a>
                        <a href="?start_date=<?= date('Y-01-01') ?>&end_date=<?= date('Y-12-31') ?>" 
                           class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-full hover:bg-gray-200">
                            Tahun Ini
                        </a>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="card p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Transaksi</p>
                                <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_transaksi']) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-blue-50">
                                <i class="fas fa-receipt text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Pendapatan</p>
                                <p class="text-2xl font-bold text-green-600"><?= rupiah($stats['total_pendapatan']) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-green-50">
                                <i class="fas fa-money-bill text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-6 border-l-4 border-purple-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Profit</p>
                                <p class="text-2xl font-bold text-purple-600"><?= rupiah($stats['total_profit']) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-purple-50">
                                <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-6 border-l-4 border-yellow-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Rata-rata Transaksi</p>
                                <p class="text-2xl font-bold text-yellow-600">
                                    <?= $stats['total_transaksi'] > 0 ? rupiah($stats['total_pendapatan'] / $stats['total_transaksi']) : rupiah(0) ?>
                                </p>
                            </div>
                            <div class="p-3 rounded-full bg-yellow-50">
                                <i class="fas fa-calculator text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts and Tables Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Transactions by Type -->
                    <div class="card p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaksi per Jenis</h3>
                        <div class="space-y-3">
                            <?php foreach ($stats['by_type'] as $type => $data): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
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
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaksi per Status</h3>
                        <div class="space-y-3">
                            <?php foreach ($stats['by_status'] as $status => $data): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full <?= $status == 'success' ? 'bg-green-100' : ($status == 'pending' ? 'bg-yellow-100' : 'bg-red-100') ?> flex items-center justify-center">
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
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Top Products -->
                    <div class="card p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Produk Terlaris</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="table-header">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Produk</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Terjual</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Pendapatan</th>
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
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">User Terbanyak Transaksi</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="table-header">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">User</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Transaksi</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Total</th>
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
                
                <!-- Transaction List -->
                <div class="card overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Daftar Transaksi</h3>
                        <p class="text-sm text-gray-500"><?= number_format($totalRecords) ?> total transaksi</p>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="table-header">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Invoice</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Jenis</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Tujuan</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600">Nominal</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600">Total</th>
                                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
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
                                   class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">
                                    Previous
                                </a>
                                <?php endif; ?>
                                <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&limit=<?= $limit ?>" 
                                   class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">
                                    Next
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Toggle Sidebar with localStorage persistence
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const isHidden = sidebar.classList.contains('hidden');
            
            if (isHidden) {
                sidebar.classList.remove('hidden');
                overlay.classList.remove('hidden');
                localStorage.setItem('sidebar_visible', 'true');
            } else {
                sidebar.classList.add('hidden');
                overlay.classList.add('hidden');
                localStorage.setItem('sidebar_visible', 'false');
            }
        }
        
        // Initialize sidebar state from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const savedState = localStorage.getItem('sidebar_visible');
            
            // Default: visible on desktop, hidden on mobile
            const isMobile = window.innerWidth < 768;
            
            if (savedState === 'false' || (isMobile && savedState !== 'true')) {
                sidebar.classList.add('hidden');
                overlay.classList.add('hidden');
            } else {
                sidebar.classList.remove('hidden');
                overlay.classList.remove('hidden');
            }
        });
        
        // Close sidebar on overlay click (mobile)
        document.getElementById('overlay')?.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth < 768 && !sidebar.classList.contains('hidden')) {
                toggleSidebar();
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('hidden');
                overlay.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
