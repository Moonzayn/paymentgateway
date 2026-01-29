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
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar { transition: transform 0.3s ease; }
        .sidebar.closed { transform: translateX(-100%); }
        @media (min-width: 768px) { .sidebar.closed { transform: translateX(0); } }
        .menu-item.active { background: rgba(255,255,255,0.2); }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar fixed md:relative z-30 w-64 h-full bg-gradient-to-b from-indigo-600 to-purple-700 text-white flex flex-col">
            <div class="p-5 border-b border-white/20">
                <h1 class="text-xl font-bold flex items-center gap-2">
                    <i class="fas fa-wallet"></i>
                    PPOB Express
                </h1>
            </div>
            
            <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
                <a href="index.php" class="menu-item active flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-home w-5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="pulsa.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-mobile-alt w-5"></i>
                    <span>Isi Pulsa</span>
                </a>
                <a href="kuota.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-wifi w-5"></i>
                    <span>Paket Data</span>
                </a>
                <a href="listrik.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-bolt w-5"></i>
                    <span>Token Listrik</span>
                </a>
                <a href="transfer.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-money-bill-transfer w-5"></i>
                    <span>Transfer Tunai</span>
                </a>
                <a href="riwayat.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-history w-5"></i>
                    <span>Riwayat Transaksi</span>
                </a>
                <a href="deposit.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-plus-circle w-5"></i>
                    <span>Deposit Saldo</span>
                </a>
                <?php if ($role == 'admin'): ?>
                <div class="pt-4 mt-4 border-t border-white/20">
                    <p class="px-4 text-xs text-white/60 uppercase tracking-wider mb-2">Admin Menu</p>
                    <a href="kelola_user.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                        <i class="fas fa-users w-5"></i>
                        <span>Kelola User</span>
                    </a>
                    <a href="kelola_produk.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                        <i class="fas fa-box w-5"></i>
                        <span>Kelola Produk</span>
                    </a>
                    <a href="laporan.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                        <i class="fas fa-chart-bar w-5"></i>
                        <span>Laporan</span>
                    </a>
                </div>
                <?php endif; ?>
            </nav>
            
            <div class="p-4 border-t border-white/20">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold truncate"><?= $_SESSION['nama_lengkap'] ?></p>
                        <p class="text-xs text-white/70"><?= ucfirst($_SESSION['role']) ?></p>
                    </div>
                    <a href="logout.php" class="text-white/70 hover:text-white" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </aside>
        
        <!-- Overlay Mobile -->
        <div id="overlay" class="fixed inset-0 bg-black/50 z-20 hidden md:hidden" onclick="toggleSidebar()"></div>
        
        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm sticky top-0 z-10">
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()" class="md:hidden text-gray-600 hover:text-gray-800">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <h2 class="text-lg font-semibold text-gray-800">Dashboard</h2>
                    </div>
                    <div class="flex items-center gap-3">
                        <!-- Saldo Card -->
                        <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-2 rounded-lg">
                            <p class="text-xs opacity-80">Saldo</p>
                            <p class="font-bold"><?= rupiah($_SESSION['saldo']) ?></p>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content -->
            <div class="p-4 md:p-6">
                <!-- Welcome -->
                <div class="mb-6">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-800">Selamat Datang, <?= $_SESSION['nama_lengkap'] ?>! 👋</h2>
                    <p class="text-gray-600 text-sm md:text-base">Apa yang ingin Anda lakukan hari ini?</p>
                </div>
                
                <?php if ($role == 'admin'): ?>
                <!-- Admin Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-xs md:text-sm">Total Member</p>
                                <p class="text-xl md:text-2xl font-bold text-gray-800"><?= number_format($totalUser) ?></p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-users text-blue-500"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-xs md:text-sm">Trx Hari Ini</p>
                                <p class="text-xl md:text-2xl font-bold text-gray-800"><?= number_format($transaksiHariIni) ?></p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-receipt text-green-500"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-xs md:text-sm">Omset Bulan Ini</p>
                                <p class="text-lg md:text-xl font-bold text-gray-800"><?= rupiah($pendapatanBulanIni) ?></p>
                            </div>
                            <div class="bg-yellow-100 p-3 rounded-full">
                                <i class="fas fa-coins text-yellow-500"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-xs md:text-sm">Profit Bulan Ini</p>
                                <p class="text-lg md:text-xl font-bold text-gray-800"><?= rupiah($profitBulanIni) ?></p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-chart-line text-purple-500"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quick Menu -->
                <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Menu Layanan</h3>
                    <div class="grid grid-cols-4 gap-3 md:gap-6">
                        <a href="pulsa.php" class="flex flex-col items-center p-3 md:p-4 rounded-xl hover:bg-blue-50 transition group">
                            <div class="w-12 h-12 md:w-16 md:h-16 bg-blue-100 rounded-full flex items-center justify-center mb-2 group-hover:bg-blue-200 transition">
                                <i class="fas fa-mobile-alt text-xl md:text-2xl text-blue-600"></i>
                            </div>
                            <span class="text-xs md:text-sm text-gray-700 text-center font-medium">Pulsa</span>
                        </a>
                        <a href="kuota.php" class="flex flex-col items-center p-3 md:p-4 rounded-xl hover:bg-green-50 transition group">
                            <div class="w-12 h-12 md:w-16 md:h-16 bg-green-100 rounded-full flex items-center justify-center mb-2 group-hover:bg-green-200 transition">
                                <i class="fas fa-wifi text-xl md:text-2xl text-green-600"></i>
                            </div>
                            <span class="text-xs md:text-sm text-gray-700 text-center font-medium">Kuota</span>
                        </a>
                        <a href="listrik.php" class="flex flex-col items-center p-3 md:p-4 rounded-xl hover:bg-yellow-50 transition group">
                            <div class="w-12 h-12 md:w-16 md:h-16 bg-yellow-100 rounded-full flex items-center justify-center mb-2 group-hover:bg-yellow-200 transition">
                                <i class="fas fa-bolt text-xl md:text-2xl text-yellow-600"></i>
                            </div>
                            <span class="text-xs md:text-sm text-gray-700 text-center font-medium">Listrik</span>
                        </a>
                        <a href="transfer.php" class="flex flex-col items-center p-3 md:p-4 rounded-xl hover:bg-purple-50 transition group">
                            <div class="w-12 h-12 md:w-16 md:h-16 bg-purple-100 rounded-full flex items-center justify-center mb-2 group-hover:bg-purple-200 transition">
                                <i class="fas fa-money-bill-transfer text-xl md:text-2xl text-purple-600"></i>
                            </div>
                            <span class="text-xs md:text-sm text-gray-700 text-center font-medium">Transfer</span>
                        </a>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Chart -->
                    <?php if ($role == 'admin'): ?>
                    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-4 md:p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Transaksi 7 Hari Terakhir</h3>
                        <canvas id="transaksiChart" height="200"></canvas>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Transaksi Terakhir -->
                    <div class="<?= $role == 'admin' ? '' : 'lg:col-span-2' ?> bg-white rounded-xl shadow-sm p-4 md:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Transaksi Terakhir</h3>
                            <a href="riwayat.php" class="text-indigo-600 hover:text-indigo-800 text-sm">Lihat Semua</a>
                        </div>
                        <div class="space-y-3">
                            <?php if ($transaksiTerakhir->num_rows > 0): ?>
                                <?php while ($trx = $transaksiTerakhir->fetch_assoc()): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center
                                            <?php 
                                            switch($trx['jenis_transaksi']) {
                                                case 'pulsa': echo 'bg-blue-100'; break;
                                                case 'kuota': echo 'bg-green-100'; break;
                                                case 'listrik': echo 'bg-yellow-100'; break;
                                                case 'transfer': echo 'bg-purple-100'; break;
                                            }
                                            ?>">
                                            <i class="fas 
                                                <?php 
                                                switch($trx['jenis_transaksi']) {
                                                    case 'pulsa': echo 'fa-mobile-alt text-blue-600'; break;
                                                    case 'kuota': echo 'fa-wifi text-green-600'; break;
                                                    case 'listrik': echo 'fa-bolt text-yellow-600'; break;
                                                    case 'transfer': echo 'fa-money-bill-transfer text-purple-600'; break;
                                                }
                                                ?>"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800 text-sm"><?= $trx['nama_produk'] ?? ucfirst($trx['jenis_transaksi']) ?></p>
                                            <p class="text-xs text-gray-500"><?= $trx['no_tujuan'] ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-gray-800 text-sm"><?= rupiah($trx['total_bayar']) ?></p>
                                        <span class="text-xs px-2 py-0.5 rounded-full 
                                            <?php 
                                            switch($trx['status']) {
                                                case 'success': echo 'bg-green-100 text-green-600'; break;
                                                case 'pending': echo 'bg-yellow-100 text-yellow-600'; break;
                                                case 'failed': echo 'bg-red-100 text-red-600'; break;
                                            }
                                            ?>">
                                            <?= ucfirst($trx['status']) ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-4">Belum ada transaksi</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Promo Banner -->
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl shadow-sm p-6 text-white">
                        <h3 class="text-lg font-bold mb-2">🎉 Promo Spesial!</h3>
                        <p class="text-sm text-white/90 mb-4">Dapatkan cashback hingga 10% untuk setiap pembelian pulsa minimal Rp 50.000</p>
                        <a href="pulsa.php" class="inline-block bg-white text-indigo-600 px-4 py-2 rounded-lg font-semibold text-sm hover:bg-gray-100 transition">
                            Beli Sekarang
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <footer class="bg-white border-t mt-6 p-4 text-center text-gray-500 text-sm">
                <p>&copy; <?= date('Y') ?> PPOB Express. All rights reserved.</p>
            </footer>
        </main>
    </div>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('closed');
            document.getElementById('overlay').classList.toggle('hidden');
        }
        
        <?php if ($role == 'admin' && !empty($chartLabels)): ?>
        // Chart Transaksi
        const ctx = document.getElementById('transaksiChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Total Transaksi (Rp)',
                    data: <?= json_encode($chartValues) ?>,
                    backgroundColor: 'rgba(99, 102, 241, 0.8)',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>
