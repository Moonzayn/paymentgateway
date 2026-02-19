<?php
require_once 'config.php';
cekLogin();

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Update saldo di session
$_SESSION['saldo'] = getSaldo($user_id);

// Statistik untuk Admin
if ($role == 'admin') {
    // Total User
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'member'");
    $totalUser = $result->fetch_assoc()['total'];
    
    // Total Transaksi Hari Ini
    $result = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(total_bayar), 0) as pendapatan FROM transaksi WHERE DATE(created_at) = CURDATE() AND status = 'success'");
    $row = $result->fetch_assoc();
    $transaksiHariIni = $row['total'];
    $pendapatanHariIni = $row['pendapatan'];
    
    // Total Transaksi Bulan Ini
    $result = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(total_bayar), 0) as pendapatan FROM transaksi WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'success'");
    $row = $result->fetch_assoc();
    $transaksiBulanIni = $row['total'];
    $pendapatanBulanIni = $row['pendapatan'];
    
    // Profit Bulan Ini
    $result = $conn->query("SELECT COALESCE(SUM(t.harga - COALESCE(p.harga_modal, 0)), 0) as profit FROM transaksi t LEFT JOIN produk p ON t.produk_id = p.id WHERE MONTH(t.created_at) = MONTH(CURDATE()) AND YEAR(t.created_at) = YEAR(CURDATE()) AND t.status = 'success'");
    $profitBulanIni = $result->fetch_assoc()['profit'];
}

// Transaksi Terakhir User
$stmt = $conn->prepare("SELECT t.*, p.nama_produk FROM transaksi t LEFT JOIN produk p ON t.produk_id = p.id WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transaksiTerakhir = $stmt->get_result();

// Transaksi Terakhir untuk Admin (semua user)
if ($role == 'admin') {
    $transaksiAdmin = $conn->query("SELECT t.*, p.nama_produk, u.nama_lengkap FROM transaksi t LEFT JOIN produk p ON t.produk_id = p.id LEFT JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 10");
}

// Kategori Produk
$kategori = $conn->query("SELECT * FROM kategori_produk WHERE status = 'active'");

// Data untuk Chart (Transaksi 7 hari terakhir)
$chartData = $conn->query("SELECT DATE(created_at) as tanggal, COUNT(*) as jumlah, SUM(total_bayar) as total FROM transaksi WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 'success' GROUP BY DATE(created_at) ORDER BY tanggal");
$chartLabels = [];
$chartValues = [];
while ($row = $chartData->fetch_assoc()) {
    $chartLabels[] = date('d M', strtotime($row['tanggal']));
    $chartValues[] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PPOB Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ── Layout ── */
        body {
            display: flex;
            min-height: 100vh;
            background: #f8fafc;
        }

        /* ── Sidebar ── */
        #sidebar {
            position: fixed;
            top: 0; left: 0;
            width: 256px;
            height: 100vh;
            background: white;
            border-right: 1px solid #e5e7eb;
            z-index: 40;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                        width   0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(0);
        }

        #sidebar.sidebar-hidden {
            transform: translateX(-100%);
        }

        /* ── Main Content ── */
        #main-content {
            flex: 1;
            min-width: 0;
            margin-left: 256px;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #main-content.sidebar-hidden {
            margin-left: 0;
        }

        /* ── Mobile: sidebar default hidden ── */
        @media (max-width: 767px) {
            #sidebar {
                transform: translateX(-100%);
            }
            #sidebar.sidebar-open {
                transform: translateX(0);
            }
            #main-content {
                margin-left: 0 !important;
            }
        }

        /* ── Overlay (mobile only) ── */
        #overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            z-index: 30;
            backdrop-filter: blur(2px);
        }
        #overlay.show {
            display: block;
        }

        /* ── Sticky Header ── */
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 10;
            background: white;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }

        /* ── Menu Items ── */
        .menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            color: #4b5563;
            text-decoration: none;
            transition: all 0.18s ease;
            white-space: nowrap;
            overflow: hidden;
        }
        .menu-item:hover {
            background: #f0f4ff;
            color: #2563be;
        }
        .menu-item.active {
            background: #eff6ff;
            color: #2563be;
            border-left: 3px solid #2563be;
            font-weight: 600;
        }
        .menu-item i {
            width: 1.25rem;
            text-align: center;
            flex-shrink: 0;
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
        .badge-blue   { background:#dbeafe; color:#1d4ed8; }
        .badge-green  { background:#dcfce7; color:#15803d; }
        .badge-yellow { background:#fef9c3; color:#a16207; }
        .badge-purple { background:#f3e8ff; color:#7e22ce; }
        .badge-indigo { background:#e0e7ff; color:#4338ca; }

        .status-success { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
        .status-pending { background:#fef9c3; color:#a16207; border:1px solid #fde047; }
        .status-failed  { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; }

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
            border-radius: 0.75rem;
            border: 1px solid #e8ecf0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        /* ── Toggle Button ── */
        #toggleBtn {
            transition: background 0.2s ease, transform 0.15s ease;
        }
        #toggleBtn:active { transform: scale(0.92); }

        /* ── Quick Menu Items ── */
        .quick-menu-item {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .quick-menu-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.15);
        }
        .quick-menu-item:active {
            transform: translateY(-2px);
        }

        /* ── Transaction Card ── */
        .trx-card {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s ease;
        }
        .trx-card:last-child { border-bottom: none; }
        .trx-card:hover { background: #f8fafc; }

        /* ── Gradient Background ── */
        .bg-gradient-custom {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        }

        /* ── Smooth Scrollbar ── */
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* ── Animation ── */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
    </style>
</head>

<body>

<!-- ═══════════════════════════════════════════
     SIDEBAR
════════════════════════════════════════════ -->
<aside id="sidebar">
    <!-- Logo -->
    <div class="p-5 border-b border-gray-100 flex items-center gap-3 flex-shrink-0">
        <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-wallet text-white"></i>
        </div>
        <span class="text-lg font-bold leading-tight">
            <span class="text-blue-600">PPOB</span> Express
        </span>
    </div>

    <!-- Nav -->
    <nav class="flex-1 p-3 space-y-0.5 overflow-y-auto">
        <a href="index.php"    class="menu-item active"><i class="fas fa-home"></i><span>Dashboard</span></a>
        <a href="pulsa.php"    class="menu-item"><i class="fas fa-mobile-alt"></i><span>Isi Pulsa</span></a>
        <a href="kuota.php"    class="menu-item"><i class="fas fa-wifi"></i><span>Paket Data</span></a>
        <a href="listrik.php"  class="menu-item"><i class="fas fa-bolt"></i><span>Token Listrik</span></a>
        <a href="transfer.php" class="menu-item"><i class="fas fa-money-bill-transfer"></i><span>Transfer Tunai</span></a>
        <a href="deposit.php"  class="menu-item"><i class="fas fa-plus-circle"></i><span>Deposit Saldo</span></a>
        <a href="riwayat.php"  class="menu-item"><i class="fas fa-history"></i><span>Riwayat Transaksi</span></a>
        
        <?php if ($role == 'admin'): ?>
        <div class="pt-4 mt-2 border-t border-gray-100">
            <p class="px-4 text-xs text-gray-400 uppercase tracking-wider mb-2 font-semibold">Admin Menu</p>
            <a href="kelola_user.php"    class="menu-item"><i class="fas fa-users"></i><span>Kelola User</span></a>
            <a href="kelola_produk.php" class="menu-item"><i class="fas fa-box"></i><span>Kelola Produk</span></a>
            <a href="laporan.php"       class="menu-item"><i class="fas fa-chart-bar"></i><span>Laporan</span></a>
        </div>
        <?php endif; ?>
    </nav>

    <!-- Footer -->
    <div class="p-3 border-t border-gray-100 flex-shrink-0">
        <a href="logout.php" class="menu-item text-red-400 hover:text-red-600 hover:bg-red-50">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </a>
    </div>
</aside>

<!-- Overlay (mobile) -->
<div id="overlay" onclick="closeSidebar()"></div>

<!-- ═══════════════════════════════════════════
     MAIN CONTENT
════════════════════════════════════════════ -->
<div id="main-content">

    <!-- ── Sticky Header ── -->
    <header class="sticky-header px-4 py-3 flex items-center justify-between gap-3">
        <!-- Kiri: Toggle + User Info -->
        <div class="flex items-center gap-3 min-w-0">
            <button id="toggleBtn" onclick="toggleSidebar()"
                class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-600 hover:bg-gray-100 hover:text-blue-600 flex-shrink-0"
                title="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>

            <div class="flex items-center gap-2 min-w-0">
                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user text-white text-xs"></i>
                </div>
                <div class="min-w-0 hidden sm:block">
                    <p class="font-semibold text-gray-800 text-sm truncate"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></p>
                    <p class="text-xs text-gray-500"><?= ucfirst($_SESSION['role']) ?></p>
                </div>
            </div>
        </div>

        <!-- Kanan: Saldo -->
        <div class="flex items-center gap-2 flex-shrink-0">
            <div class="bg-blue-50 border border-blue-100 px-3 py-1.5 rounded-lg text-right">
                <p class="text-xs text-gray-500 leading-none">Saldo</p>
                <p class="font-bold text-blue-700 text-sm leading-tight"><?= rupiah($_SESSION['saldo']) ?></p>
            </div>
        </div>
    </header>

    <!-- ── Page Body ── -->
    <div class="p-4 md:p-6 max-w-7xl mx-auto space-y-5">

        <!-- Welcome Section -->
        <div class="animate-fade-in-up">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900">Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>! 👋</h2>
            <p class="text-sm text-gray-500 mt-1">Kelola layanan PPOB Anda dengan mudah dan cepat</p>
        </div>

        <!-- ── Stats (Admin only) ── -->
        <?php if ($role == 'admin'): ?>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 animate-fade-in-up delay-100">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Total Member</p>
                        <p class="text-2xl font-bold text-gray-800"><?= number_format($totalUser) ?></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Transaksi Hari Ini</p>
                        <p class="text-2xl font-bold text-green-600"><?= number_format($transaksiHariIni) ?></p>
                    </div>
                    <div class="w-10 h-10 bg-green-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-receipt text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Omset Bulan Ini</p>
                        <p class="text-lg font-bold text-amber-600"><?= rupiah($pendapatanBulanIni) ?></p>
                    </div>
                    <div class="w-10 h-10 bg-amber-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-coins text-amber-600"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Profit Bulan Ini</p>
                        <p class="text-lg font-bold text-indigo-600"><?= rupiah($profitBulanIni) ?></p>
                    </div>
                    <div class="w-10 h-10 bg-indigo-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-indigo-600"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Quick Menu ── -->
        <div class="card p-5 animate-fade-in-up delay-200">
            <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <i class="fas fa-bolt text-blue-600"></i>
                Layanan Cepat
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <a href="pulsa.php" class="quick-menu-item flex flex-col items-center p-4 md:p-5 bg-white border border-gray-200 rounded-xl hover:border-blue-300">
                    <div class="w-12 h-12 md:w-14 md:h-14 bg-blue-50 rounded-full flex items-center justify-center mb-3 transition-transform duration-300 group-hover:scale-110">
                        <i class="fas fa-mobile-alt text-xl text-blue-600"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-800">Pulsa</span>
                    <span class="text-xs text-gray-500 mt-0.5 hidden md:block">Isi pulsa cepat</span>
                </a>
                <a href="kuota.php" class="quick-menu-item flex flex-col items-center p-4 md:p-5 bg-white border border-gray-200 rounded-xl hover:border-green-300">
                    <div class="w-12 h-12 md:w-14 md:h-14 bg-green-50 rounded-full flex items-center justify-center mb-3">
                        <i class="fas fa-wifi text-xl text-green-600"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-800">Kuota</span>
                    <span class="text-xs text-gray-500 mt-0.5 hidden md:block">Paket data</span>
                </a>
                <a href="listrik.php" class="quick-menu-item flex flex-col items-center p-4 md:p-5 bg-white border border-gray-200 rounded-xl hover:border-amber-300">
                    <div class="w-12 h-12 md:w-14 md:h-14 bg-amber-50 rounded-full flex items-center justify-center mb-3">
                        <i class="fas fa-bolt text-xl text-amber-600"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-800">Listrik</span>
                    <span class="text-xs text-gray-500 mt-0.5 hidden md:block">Token PLN</span>
                </a>
                <a href="transfer.php" class="quick-menu-item flex flex-col items-center p-4 md:p-5 bg-white border border-gray-200 rounded-xl hover:border-purple-300">
                    <div class="w-12 h-12 md:w-14 md:h-14 bg-purple-50 rounded-full flex items-center justify-center mb-3">
                        <i class="fas fa-money-bill-transfer text-xl text-purple-600"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-800">Transfer</span>
                    <span class="text-xs text-gray-500 mt-0.5 hidden md:block">Kirim uang</span>
                </a>
            </div>
        </div>

        <!-- ── Main Content Grid ── -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            
            <!-- Chart Section (Admin Only) -->
            <?php if ($role == 'admin'): ?>
            <div class="lg:col-span-2 card p-5 animate-fade-in-up delay-300">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-chart-bar text-blue-600 text-sm"></i>
                        Statistik Transaksi 7 Hari Terakhir
                    </h3>
                    <span class="text-xs text-gray-500 bg-gray-100 px-3 py-1 rounded-full">
                        Total: <?= rupiah(array_sum($chartValues)) ?>
                    </span>
                </div>
                <div class="relative h-64 md:h-72">
                    <canvas id="transaksiChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Transaksi Terakhir -->
            <div class="<?= $role == 'admin' ? 'lg:col-span-1' : 'lg:col-span-2' ?> card overflow-hidden animate-fade-in-up delay-300">
                <!-- Card Header -->
                <div class="px-4 md:px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-900">Transaksi Terakhir</h3>
                        <p class="text-xs text-gray-400 mt-0.5"><?= $transaksiTerakhir->num_rows ?> transaksi terbaru</p>
                    </div>
                    <a href="riwayat.php" class="text-xs text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1 transition-colors">
                        Lihat Semua <i class="fas fa-chevron-right text-xs"></i>
                    </a>
                </div>

                <?php if ($transaksiTerakhir->num_rows > 0): ?>
                    <!-- Desktop List -->
                    <div class="hidden md:block">
                        <?php 
                        $transaksiTerakhir->data_seek(0);
                        while ($trx = $transaksiTerakhir->fetch_assoc()): 
                        ?>
                        <div class="trx-card flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0
                                    <?php 
                                    switch($trx['jenis_transaksi']) {
                                        case 'pulsa': echo 'bg-blue-50'; break;
                                        case 'kuota': echo 'bg-green-50'; break;
                                        case 'listrik': echo 'bg-amber-50'; break;
                                        case 'transfer': echo 'bg-purple-50'; break;
                                        default: echo 'bg-gray-50';
                                    }
                                    ?>">
                                    <i class="fas 
                                        <?php 
                                        switch($trx['jenis_transaksi']) {
                                            case 'pulsa': echo 'fa-mobile-alt text-blue-600'; break;
                                            case 'kuota': echo 'fa-wifi text-green-600'; break;
                                            case 'listrik': echo 'fa-bolt text-amber-600'; break;
                                            case 'transfer': echo 'fa-money-bill-transfer text-purple-600'; break;
                                            default: echo 'fa-receipt text-gray-600';
                                        }
                                        ?> text-sm"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-800 text-sm truncate"><?= htmlspecialchars($trx['nama_produk'] ?? ucfirst($trx['jenis_transaksi'])) ?></p>
                                    <p class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($trx['no_tujuan']) ?></p>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="font-bold text-gray-900 text-sm"><?= rupiah($trx['total_bayar']) ?></p>
                                <span class="badge mt-0.5
                                    <?php 
                                    switch($trx['status']) {
                                        case 'success': echo 'status-success'; break;
                                        case 'pending': echo 'status-pending'; break;
                                        case 'failed': echo 'status-failed'; break;
                                    }
                                    ?>">
                                    <?= ucfirst($trx['status']) ?>
                                </span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="md:hidden">
                        <?php 
                        $transaksiTerakhir->data_seek(0);
                        while ($trx = $transaksiTerakhir->fetch_assoc()): 
                        ?>
                        <div class="trx-card">
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0
                                        <?php 
                                        switch($trx['jenis_transaksi']) {
                                            case 'pulsa': echo 'bg-blue-50'; break;
                                            case 'kuota': echo 'bg-green-50'; break;
                                            case 'listrik': echo 'bg-amber-50'; break;
                                            case 'transfer': echo 'bg-purple-50'; break;
                                            default: echo 'bg-gray-50';
                                        }
                                        ?>">
                                        <i class="fas 
                                            <?php 
                                            switch($trx['jenis_transaksi']) {
                                                case 'pulsa': echo 'fa-mobile-alt text-blue-600'; break;
                                                case 'kuota': echo 'fa-wifi text-green-600'; break;
                                                case 'listrik': echo 'fa-bolt text-amber-600'; break;
                                                case 'transfer': echo 'fa-money-bill-transfer text-purple-600'; break;
                                                default: echo 'fa-receipt text-gray-600';
                                            }
                                            ?> text-xs"></i>
                                    </div>
                                    <span class="badge 
                                        <?php 
                                        switch($trx['jenis_transaksi']) {
                                            case 'pulsa': echo 'badge-blue'; break;
                                            case 'kuota': echo 'badge-green'; break;
                                            case 'listrik': echo 'badge-yellow'; break;
                                            case 'transfer': echo 'badge-purple'; break;
                                        }
                                        ?>">
                                        <?= ucfirst($trx['jenis_transaksi']) ?>
                                    </span>
                                </div>
                                <span class="font-bold text-blue-700 text-sm whitespace-nowrap"><?= rupiah($trx['total_bayar']) ?></span>
                            </div>

                            <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                                <div>
                                    <p class="text-gray-400">Produk</p>
                                    <p class="text-gray-700 truncate"><?= htmlspecialchars($trx['nama_produk'] ?? ucfirst($trx['jenis_transaksi'])) ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-400">No. Tujuan</p>
                                    <p class="font-mono text-gray-700"><?= htmlspecialchars($trx['no_tujuan']) ?></p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                                <span class="badge 
                                    <?php 
                                    switch($trx['status']) {
                                        case 'success': echo 'status-success'; break;
                                        case 'pending': echo 'status-pending'; break;
                                        case 'failed': echo 'status-failed'; break;
                                    }
                                    ?>">
                                    <?= ucfirst($trx['status']) ?>
                                </span>
                                <p class="text-xs text-gray-400">
                                    <i class="fas fa-clock mr-1"></i><?= date('d M Y, H:i', strtotime($trx['created_at'])) ?>
                                </p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>

                <?php else: ?>
                    <!-- Empty State -->
                    <div class="text-center py-12 px-4">
                        <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-receipt text-gray-300 text-2xl"></i>
                        </div>
                        <h3 class="font-semibold text-gray-700 mb-1 text-sm">Belum ada transaksi</h3>
                        <p class="text-xs text-gray-400 mb-4">Mulai transaksi pertama Anda</p>
                        <a href="pulsa.php" class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition">
                            <i class="fas fa-plus"></i> Transaksi Baru
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Promo Banner (untuk member) -->
            <?php if ($role != 'admin'): ?>
            <div class="lg:col-span-1 animate-fade-in-up delay-300">
                <div class="bg-gradient-custom rounded-xl p-5 text-white h-full flex flex-col justify-between">
                    <div>
                        <span class="bg-white/20 text-xs px-3 py-1 rounded-full inline-block mb-3">Promo</span>
                        <h3 class="text-lg font-bold mb-2">🎉 Cashback 10%!</h3>
                        <p class="text-white/90 text-sm mb-4">Khusus pembelian pulsa minimal Rp 50.000. Berlaku hingga 31 Desember.</p>
                    </div>
                    <a href="pulsa.php" class="inline-flex items-center justify-center bg-white text-blue-700 px-4 py-2.5 rounded-lg font-semibold text-sm hover:bg-gray-50 transition shadow-sm">
                        <i class="fas fa-bolt mr-2"></i> Beli Sekarang
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /page body -->

    <!-- Footer -->
    <footer class="border-t border-gray-200 mt-8 px-6 py-4 bg-white">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-2">
            <p class="text-gray-500 text-xs">&copy; <?= date('Y') ?> PPOB Express. All rights reserved.</p>
            <div class="flex gap-4">
                <a href="#" class="text-gray-400 hover:text-blue-600 text-xs transition">Kebijakan Privasi</a>
                <a href="#" class="text-gray-400 hover:text-blue-600 text-xs transition">Syarat & Ketentuan</a>
            </div>
        </div>
    </footer>
</div><!-- /main-content -->

<script>
// ═══════════════════════════════════════════════════════
//  SIDEBAR LOGIC — bekerja di mobile DAN desktop
// ═══════════════════════════════════════════════════════

const sidebar     = document.getElementById('sidebar');
const mainContent = document.getElementById('main-content');
const overlay     = document.getElementById('overlay');

// Key localStorage
const STORAGE_KEY = 'sidebar_open';

function isDesktop() {
    return window.innerWidth >= 768;
}

function openSidebar() {
    if (isDesktop()) {
        sidebar.classList.remove('sidebar-hidden');
        mainContent.classList.remove('sidebar-hidden');
        localStorage.setItem(STORAGE_KEY, 'true');
    } else {
        sidebar.classList.add('sidebar-open');
        overlay.classList.add('show');
    }
}

function closeSidebar() {
    if (isDesktop()) {
        sidebar.classList.add('sidebar-hidden');
        mainContent.classList.add('sidebar-hidden');
        localStorage.setItem(STORAGE_KEY, 'false');
    } else {
        sidebar.classList.remove('sidebar-open');
        overlay.classList.remove('show');
    }
}

function toggleSidebar() {
    if (isDesktop()) {
        const isHidden = sidebar.classList.contains('sidebar-hidden');
        isHidden ? openSidebar() : closeSidebar();
    } else {
        const isOpen = sidebar.classList.contains('sidebar-open');
        isOpen ? closeSidebar() : openSidebar();
    }
}

function initSidebar() {
    if (isDesktop()) {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'false') {
            sidebar.classList.add('sidebar-hidden');
            mainContent.classList.add('sidebar-hidden');
        } else {
            sidebar.classList.remove('sidebar-hidden');
            sidebar.classList.remove('sidebar-open');
            mainContent.classList.remove('sidebar-hidden');
            overlay.classList.remove('show');
        }
    } else {
        sidebar.classList.remove('sidebar-hidden');
        sidebar.classList.remove('sidebar-open');
        mainContent.classList.remove('sidebar-hidden');
        overlay.classList.remove('show');
    }
}

document.addEventListener('DOMContentLoaded', initSidebar);

let resizeTimer;
window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(initSidebar, 100);
});

document.querySelectorAll('.menu-item').forEach(item => {
    item.addEventListener('click', () => {
        if (!isDesktop()) closeSidebar();
    });
});

<?php if ($role == 'admin' && !empty($chartLabels)): ?>
// ═══════════════════════════════════════════════════════
//  CHART
// ═══════════════════════════════════════════════════════
const ctx = document.getElementById('transaksiChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Total Transaksi (Rp)',
            data: <?= json_encode($chartValues) ?>,
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            borderColor: 'rgb(37, 99, 235)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: 'rgb(37, 99, 235)',
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
                    color: 'rgba(0, 0, 0, 0.05)'
                },
                ticks: {
                    callback: function(value) {
                        if (value >= 1000000) {
                            return 'Rp ' + (value / 1000000).toFixed(1) + 'jt';
                        } else if (value >= 1000) {
                            return 'Rp ' + (value / 1000).toFixed(0) + 'rb';
                        }
                        return 'Rp ' + value;
                    },
                    font: { size: 11 }
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
<?php endif; ?>
</script>

</body>
</html>
<?php $conn->close(); ?>
