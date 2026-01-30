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
$stmt = $conn->prepare("SELECT t.*, p.nama_produk FROM transaksi t LEFT JOIN produk p ON t.produk_id = p.id WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT 5");
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
        /* Custom CSS untuk tema minimalis */
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #3b82f6;
            --light-blue: #eff6ff;
            --dark-blue: #1e40af;
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
            transition: box-shadow 0.3s ease;
            border: 1px solid #e2e8f0;
        }
        
        .card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card {
            border-top: 4px solid;
        }
        
        .quick-menu-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
        }
        
        .status-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
        }
        
        .header-shadow {
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .bg-gradient-custom {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
        }
        
        /* Scrollbar styling */
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
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar - Minimalis putih dengan aksen biru -->
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
                <a href="index.php" class="menu-item active flex items-center gap-3 px-4 py-3 text-gray-700">
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
                <a href="riwayat.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
                    <i class="fas fa-history w-5"></i>
                    <span>Riwayat Transaksi</span>
                </a>
                <a href="deposit.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
                    <i class="fas fa-plus-circle w-5"></i>
                    <span>Deposit Saldo</span>
                </a>
                <?php if ($role == 'admin'): ?>
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
                    <a href="laporan.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
                        <i class="fas fa-chart-bar w-5"></i>
                        <span>Laporan</span>
                    </a>
                </div>
                <?php endif; ?>
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
        
        <!-- Overlay Mobile -->
        <div id="overlay" class="fixed inset-0 bg-black/30 z-20 hidden md:hidden" onclick="toggleSidebar()"></div>
        
        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Header -->
            <header class="bg-white header-shadow sticky top-0 z-10">
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()" class="md:hidden text-gray-600 hover:text-blue-600">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                        <h2 class="text-lg font-semibold text-gray-800">Dashboard</h2>
                    </div>
                    <div class="flex items-center gap-3">
                        <!-- Saldo Card -->
                        <div class="bg-blue-50 border border-blue-100 px-4 py-2 rounded-lg">
                            <p class="text-xs text-gray-600">Saldo</p>
                            <p class="font-bold text-blue-700"><?= rupiah($_SESSION['saldo']) ?></p>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content -->
            <div class="p-4 md:p-6">
                <!-- Welcome -->
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900">Selamat Datang, <?= $_SESSION['nama_lengkap'] ?>! 👋</h2>
                    <p class="text-gray-600 mt-1">Kelola layanan PPOB Anda dengan mudah dan cepat</p>
                </div>
                
                <?php if ($role == 'admin'): ?>
                <!-- Admin Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="card stat-card p-5 border-t-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Member</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($totalUser) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-blue-50">
                                <i class="fas fa-users text-blue-600 text-lg"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card stat-card p-5 border-t-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Transaksi Hari Ini</p>
                                <p class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($transaksiHariIni) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-green-50">
                                <i class="fas fa-receipt text-green-600 text-lg"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card stat-card p-5 border-t-amber-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Omset Bulan Ini</p>
                                <p class="text-xl font-bold text-gray-900 mt-1"><?= rupiah($pendapatanBulanIni) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-amber-50">
                                <i class="fas fa-coins text-amber-600 text-lg"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card stat-card p-5 border-t-indigo-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Profit Bulan Ini</p>
                                <p class="text-xl font-bold text-gray-900 mt-1"><?= rupiah($profitBulanIni) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-indigo-50">
                                <i class="fas fa-chart-line text-indigo-600 text-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quick Menu -->
                <div class="card p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6">Layanan Cepat</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="pulsa.php" class="quick-menu-item flex flex-col items-center p-5 bg-white border border-gray-200 rounded-xl hover:border-blue-300 transition-all duration-300">
                            <div class="w-14 h-14 bg-blue-50 rounded-full flex items-center justify-center mb-3">
                                <i class="fas fa-mobile-alt text-xl text-blue-600"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-800">Pulsa</span>
                            <span class="text-xs text-gray-500 mt-1">Isi pulsa cepat</span>
                        </a>
                        <a href="kuota.php" class="quick-menu-item flex flex-col items-center p-5 bg-white border border-gray-200 rounded-xl hover:border-green-300 transition-all duration-300">
                            <div class="w-14 h-14 bg-green-50 rounded-full flex items-center justify-center mb-3">
                                <i class="fas fa-wifi text-xl text-green-600"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-800">Kuota</span>
                            <span class="text-xs text-gray-500 mt-1">Paket data</span>
                        </a>
                        <a href="listrik.php" class="quick-menu-item flex flex-col items-center p-5 bg-white border border-gray-200 rounded-xl hover:border-amber-300 transition-all duration-300">
                            <div class="w-14 h-14 bg-amber-50 rounded-full flex items-center justify-center mb-3">
                                <i class="fas fa-bolt text-xl text-amber-600"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-800">Listrik</span>
                            <span class="text-xs text-gray-500 mt-1">Token PLN</span>
                        </a>
                        <a href="transfer.php" class="quick-menu-item flex flex-col items-center p-5 bg-white border border-gray-200 rounded-xl hover:border-purple-300 transition-all duration-300">
                            <div class="w-14 h-14 bg-purple-50 rounded-full flex items-center justify-center mb-3">
                                <i class="fas fa-money-bill-transfer text-xl text-purple-600"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-800">Transfer</span>
                            <span class="text-xs text-gray-500 mt-1">Kirim uang</span>
                        </a>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Chart untuk Admin -->
                    <?php if ($role == 'admin'): ?>
                    <div class="lg:col-span-2 card p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Statistik Transaksi 7 Hari Terakhir</h3>
                            <span class="text-xs text-gray-500 bg-gray-100 px-3 py-1 rounded-full">Total: <?= rupiah(array_sum($chartValues)) ?></span>
                        </div>
                        <canvas id="transaksiChart" height="250"></canvas>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Transaksi Terakhir -->
                    <div class="<?= $role == 'admin' ? '' : 'lg:col-span-2' ?> card p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Transaksi Terakhir</h3>
                            <a href="riwayat.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center gap-1">
                                Lihat Semua <i class="fas fa-chevron-right text-xs"></i>
                            </a>
                        </div>
                        <div class="space-y-4">
                            <?php if ($transaksiTerakhir->num_rows > 0): ?>
                                <?php while ($trx = $transaksiTerakhir->fetch_assoc()): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 border border-gray-100 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-lg flex items-center justify-center
                                            <?php 
                                            switch($trx['jenis_transaksi']) {
                                                case 'pulsa': echo 'bg-blue-50'; break;
                                                case 'kuota': echo 'bg-green-50'; break;
                                                case 'listrik': echo 'bg-amber-50'; break;
                                                case 'transfer': echo 'bg-purple-50'; break;
                                            }
                                            ?>">
                                            <i class="fas 
                                                <?php 
                                                switch($trx['jenis_transaksi']) {
                                                    case 'pulsa': echo 'fa-mobile-alt text-blue-600'; break;
                                                    case 'kuota': echo 'fa-wifi text-green-600'; break;
                                                    case 'listrik': echo 'fa-bolt text-amber-600'; break;
                                                    case 'transfer': echo 'fa-money-bill-transfer text-purple-600'; break;
                                                }
                                                ?>"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800 text-sm"><?= $trx['nama_produk'] ?? ucfirst($trx['jenis_transaksi']) ?></p>
                                            <p class="text-xs text-gray-500 mt-0.5"><?= $trx['no_tujuan'] ?></p>
                                            <p class="text-xs text-gray-500"><?= date('d M Y H:i', strtotime($trx['created_at'])) ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-gray-900"><?= rupiah($trx['total_bayar']) ?></p>
                                        <span class="status-badge inline-block mt-1
                                            <?php 
                                            switch($trx['status']) {
                                                case 'success': echo 'bg-green-50 text-green-700 border border-green-200'; break;
                                                case 'pending': echo 'bg-amber-50 text-amber-700 border border-amber-200'; break;
                                                case 'failed': echo 'bg-red-50 text-red-700 border border-red-200'; break;
                                            }
                                            ?>">
                                            <?= ucfirst($trx['status']) ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-receipt text-gray-400 text-xl"></i>
                                    </div>
                                    <p class="text-gray-500">Belum ada transaksi</p>
                                    <p class="text-sm text-gray-400 mt-1">Mulai transaksi pertama Anda</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Promo Banner -->
                    <div class="bg-gradient-custom rounded-xl p-6 text-white">
                        <div class="mb-4">
                            <span class="bg-white/20 text-xs px-3 py-1 rounded-full">Promo</span>
                        </div>
                        <h3 class="text-xl font-bold mb-2">🎉 Cashback 10%!</h3>
                        <p class="text-white/90 text-sm mb-5">Khusus pembelian pulsa minimal Rp 50.000. Berlaku hingga 31 Desember.</p>
                        <a href="pulsa.php" class="inline-flex items-center justify-center bg-white text-blue-700 px-4 py-3 rounded-lg font-semibold text-sm hover:bg-gray-50 transition shadow-sm w-full">
                            <i class="fas fa-bolt mr-2"></i> Beli Sekarang
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <footer class="bg-white border-t border-gray-200 mt-8 px-6 py-4">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-600 text-sm">&copy; <?= date('Y') ?> PPOB Express. All rights reserved.</p>
                    <div class="flex gap-4 mt-2 md:mt-0">
                        <a href="#" class="text-gray-500 hover:text-blue-600 text-sm">Kebijakan Privasi</a>
                        <a href="#" class="text-gray-500 hover:text-blue-600 text-sm">Syarat & Ketentuan</a>
                        <a href="#" class="text-gray-500 hover:text-blue-600 text-sm">Bantuan</a>
                    </div>
                </div>
            </footer>
        </main>
    </div>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('closed');
            overlay.classList.toggle('hidden');
        }
        
        // Close sidebar when clicking on menu item on mobile
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    toggleSidebar();
                }
            });
        });
        
        <?php if ($role == 'admin' && !empty($chartLabels)): ?>
        // Chart Transaksi
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
                    legend: { 
                        display: false 
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 13 },
                        bodyFont: { size: 13 }
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
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
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