<?php
require_once 'config.php';
cekLogin();

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$_SESSION['saldo'] = getSaldo($user_id);

// Filter
$filterJenis = $_GET['jenis'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterTanggal = $_GET['tanggal'] ?? '';
$filterBulan = $_GET['bulan'] ?? '';

// Query transaksi
$where = [];
$params = [];
$types = '';

if ($role != 'admin') {
    $where[] = "t.user_id = ?";
    $params[] = $user_id;
    $types .= 'i';
}

if ($filterJenis) {
    $where[] = "t.jenis_transaksi = ?";
    $params[] = $filterJenis;
    $types .= 's';
}

if ($filterStatus) {
    $where[] = "t.status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}

if ($filterTanggal) {
    $where[] = "DATE(t.created_at) = ?";
    $params[] = $filterTanggal;
    $types .= 's';
}

if ($filterBulan) {
    $where[] = "DATE_FORMAT(t.created_at, '%Y-%m') = ?";
    $params[] = $filterBulan;
    $types .= 's';
}

$whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

$sql = "SELECT t.*, p.nama_produk, u.nama_lengkap 
        FROM transaksi t 
        LEFT JOIN produk p ON t.produk_id = p.id 
        LEFT JOIN users u ON t.user_id = u.id 
        $whereClause 
        ORDER BY t.created_at DESC 
        LIMIT 100";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transaksi = $stmt->get_result();

// Statistik
if ($role == 'admin') {
    $statsQuery = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as sukses,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as gagal,
        SUM(CASE WHEN status = 'success' THEN total_bayar ELSE 0 END) as total_sukses
        FROM transaksi WHERE DATE(created_at) = CURDATE()");
    $stats = $statsQuery->fetch_assoc();
    
    // Total transaksi
    $totalQuery = $conn->query("SELECT COUNT(*) as total_transaksi, SUM(total_bayar) as total_omset FROM transaksi WHERE status = 'success'");
    $total = $totalQuery->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - PPOB Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom CSS konsisten dengan tema */
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #3b82f6;
            --light-blue: #eff6ff;
            --gray-100: #f3f4f6;
            --gray-800: #1f2937;
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
        
        .stat-card {
            transition: all 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
        
        .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-weight: 500;
        }
        
        .status-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        .status-failed {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .jenis-pulsa {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        
        .jenis-kuota {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .jenis-listrik {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        .jenis-transfer {
            background-color: #f3e8ff;
            color: #6b21a8;
            border: 1px solid #e9d5ff;
        }
        
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
        
        .sticky-header {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar Minimalis -->
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
                <a href="riwayat.php" class="menu-item active flex items-center gap-3 px-4 py-3 text-gray-700">
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
                        <button onclick="toggleSidebar()" class="md:hidden text-gray-600 hover:text-blue-600">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">Riwayat Transaksi</h2>
                            <p class="text-sm text-gray-500">Lihat dan kelola semua transaksi Anda</p>
                        </div>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 px-4 py-2 rounded-lg">
                        <p class="text-xs text-gray-600">Saldo Tersedia</p>
                        <p class="font-bold text-blue-700"><?= rupiah($_SESSION['saldo']) ?></p>
                    </div>
                </div>
            </header>
            
            <div class="p-4 md:p-6 max-w-7xl mx-auto">
                <?php if ($role == 'admin'): ?>
                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="stat-card card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Transaksi Hari Ini</p>
                                <p class="text-2xl font-bold text-gray-900"><?= $stats['total'] ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-blue-50">
                                <i class="fas fa-calendar-day text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Sukses</p>
                                <p class="text-2xl font-bold text-green-600"><?= $stats['sukses'] ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-green-50">
                                <i class="fas fa-check-circle text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Pending</p>
                                <p class="text-2xl font-bold text-amber-600"><?= $stats['pending'] ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-amber-50">
                                <i class="fas fa-clock text-amber-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Omset Hari Ini</p>
                                <p class="text-xl font-bold text-blue-600"><?= rupiah($stats['total_sukses']??0) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-blue-50">
                                <i class="fas fa-coins text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Stats -->
                <div class="card p-5 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-gray-500 text-sm">Total Transaksi Sukses</p>
                            <p class="text-2xl font-bold text-gray-900"><?= number_format($total['total_transaksi'] ?? 0) ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-gray-500 text-sm">Total Omset</p>
                            <p class="text-2xl font-bold text-blue-600"><?= rupiah($total['total_omset'] ?? 0) ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Filter -->
                <div class="card p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter Transaksi</h3>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Transaksi</label>
                            <select name="jenis" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Semua Jenis</option>
                                <option value="pulsa" <?= $filterJenis == 'pulsa' ? 'selected' : '' ?>>Pulsa</option>
                                <option value="kuota" <?= $filterJenis == 'kuota' ? 'selected' : '' ?>>Paket Data</option>
                                <option value="listrik" <?= $filterJenis == 'listrik' ? 'selected' : '' ?>>Token Listrik</option>
                                <option value="transfer" <?= $filterJenis == 'transfer' ? 'selected' : '' ?>>Transfer</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Semua Status</option>
                                <option value="success" <?= $filterStatus == 'success' ? 'selected' : '' ?>>Sukses</option>
                                <option value="pending" <?= $filterStatus == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="failed" <?= $filterStatus == 'failed' ? 'selected' : '' ?>>Gagal</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                            <input type="date" name="tanggal" value="<?= $filterTanggal ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
                            <input type="month" name="bulan" value="<?= $filterBulan ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-4 flex gap-2 pt-2">
                            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition flex items-center gap-2">
                                <i class="fas fa-filter"></i>
                                Terapkan Filter
                            </button>
                            <a href="riwayat.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                                Reset
                            </a>
                            <button type="button" onclick="exportToExcel()" class="px-6 py-2 border border-green-300 text-green-700 rounded-lg font-medium hover:bg-green-50 transition flex items-center gap-2 ml-auto">
                                <i class="fas fa-file-excel"></i>
                                Export Excel
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Transaksi List -->
                <div class="card overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Daftar Transaksi
                            <span class="text-sm font-normal text-gray-500">(<?= $transaksi->num_rows ?> transaksi)</span>
                        </h3>
                    </div>
                    
                    <?php if ($transaksi->num_rows > 0): ?>
                        <!-- Mobile View -->
                        <div class="md:hidden divide-y divide-gray-100">
                            <?php while ($trx = $transaksi->fetch_assoc()): ?>
                            <div class="p-4 table-row">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <p class="font-semibold text-gray-900"><?= $trx['nama_produk'] ?? ucfirst($trx['jenis_transaksi']) ?></p>
                                        <p class="text-xs text-gray-500 mt-1 font-mono"><?= $trx['no_invoice'] ?></p>
                                    </div>
                                    <span class="badge status-<?= $trx['status'] ?>">
                                        <?= ucfirst($trx['status']) ?>
                                    </span>
                                </div>
                                
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Nomor Tujuan:</span>
                                        <span class="font-mono text-gray-900"><?= $trx['no_tujuan'] ?></span>
                                    </div>
                                    <?php if ($role == 'admin'): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">User:</span>
                                        <span class="text-gray-900"><?= $trx['nama_lengkap'] ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Jenis:</span>
                                        <span class="badge jenis-<?= $trx['jenis_transaksi'] ?>">
                                            <?= ucfirst($trx['jenis_transaksi']) ?>
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Tanggal:</span>
                                        <span class="text-gray-900"><?= date('d M Y H:i', strtotime($trx['created_at'])) ?></span>
                                    </div>
                                    <div class="flex justify-between pt-2 border-t border-gray-100">
                                        <span class="font-medium text-gray-700">Total Bayar:</span>
                                        <span class="font-bold text-gray-900"><?= rupiah($trx['total_bayar']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <!-- Desktop View -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="w-full">
                                <thead class="table-header">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Invoice</th>
                                        <?php if ($role == 'admin'): ?>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">User</th>
                                        <?php endif; ?>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Jenis</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Produk</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Tujuan</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php 
                                    $transaksi->data_seek(0);
                                    $no = 1;
                                    while ($trx = $transaksi->fetch_assoc()): 
                                    ?>
                                    <tr class="table-row">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900"><?= $trx['no_invoice'] ?></td>
                                        <?php if ($role == 'admin'): ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $trx['nama_lengkap'] ?></td>
                                        <?php endif; ?>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="badge jenis-<?= $trx['jenis_transaksi'] ?>">
                                                <?= ucfirst($trx['jenis_transaksi']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900"><?= $trx['nama_produk'] ?? '-' ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900"><?= $trx['no_tujuan'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900"><?= rupiah($trx['total_bayar']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="badge status-<?= $trx['status'] ?>">
                                                <?= ucfirst($trx['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($trx['created_at'])) ?></td>
                                    </tr>
                                    <?php 
                                    $no++;
                                    endwhile; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination Info -->
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 text-sm text-gray-500">
                            Menampilkan <?= $transaksi->num_rows ?> transaksi terbaru
                        </div>
                    <?php else: ?>
                        <div class="p-12 text-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-inbox text-gray-400 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada transaksi</h3>
                            <p class="text-gray-500 mb-4">Tidak ada transaksi yang sesuai dengan filter yang dipilih</p>
                            <a href="riwayat.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-sync-alt mr-2"></i>
                                Reset Filter
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('closed');
            document.getElementById('overlay').classList.toggle('hidden');
        }
        
        function exportToExcel() {
            // Create a simple HTML table for export
            const table = document.querySelector('table');
            if (!table) {
                alert('Tidak ada data untuk diexport');
                return;
            }
            
            let html = `
                <html>
                <head>
                    <style>
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <h2>Riwayat Transaksi PPOB Express</h2>
                    <p>Tanggal Export: ${new Date().toLocaleDateString('id-ID')}</p>
                    ${table.outerHTML}
                </body>
                </html>
            `;
            
            // Create a blob and download
            const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `riwayat-transaksi-${new Date().toISOString().split('T')[0]}.xls`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        // Close sidebar on menu click (mobile)
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    toggleSidebar();
                }
            });
        });
        
        // Real-time filter update
        const filterForm = document.querySelector('form[method="GET"]');
        const inputs = filterForm.querySelectorAll('select, input[type="date"], input[type="month"]');
        
        inputs.forEach(input => {
            input.addEventListener('change', () => {
                filterForm.submit();
            });
        });
    </script>
</body>
</html>
<?php 
$conn->close();
?>