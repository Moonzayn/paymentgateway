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
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - PPOB Express</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar { transition: transform 0.3s ease; }
        .sidebar.closed { transform: translateX(-100%); }
        @media (min-width: 768px) { .sidebar.closed { transform: translateX(0); } }
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
                <a href="index.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-home w-5"></i><span>Dashboard</span>
                </a>
                <a href="pulsa.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-mobile-alt w-5"></i><span>Isi Pulsa</span>
                </a>
                <a href="kuota.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-wifi w-5"></i><span>Paket Data</span>
                </a>
                <a href="listrik.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-bolt w-5"></i><span>Token Listrik</span>
                </a>
                <a href="transfer.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-money-bill-transfer w-5"></i><span>Transfer Tunai</span>
                </a>
                <a href="riwayat.php" class="menu-item active flex items-center gap-3 px-4 py-3 rounded-lg bg-white/20 transition">
                    <i class="fas fa-history w-5"></i><span>Riwayat Transaksi</span>
                </a>
                <a href="deposit.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-plus-circle w-5"></i><span>Deposit Saldo</span>
                </a>
            </nav>
            
            <div class="p-4 border-t border-white/20">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center"><i class="fas fa-user"></i></div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold truncate"><?= $_SESSION['nama_lengkap'] ?></p>
                        <p class="text-xs text-white/70"><?= ucfirst($_SESSION['role']) ?></p>
                    </div>
                    <a href="logout.php" class="text-white/70 hover:text-white"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>
        </aside>
        
        <div id="overlay" class="fixed inset-0 bg-black/50 z-20 hidden md:hidden" onclick="toggleSidebar()"></div>
        
        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <header class="bg-white shadow-sm sticky top-0 z-10">
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()" class="md:hidden text-gray-600"><i class="fas fa-bars text-xl"></i></button>
                        <h2 class="text-lg font-semibold text-gray-800">Riwayat Transaksi</h2>
                    </div>
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-2 rounded-lg">
                        <p class="text-xs opacity-80">Saldo</p>
                        <p class="font-bold"><?= rupiah($_SESSION['saldo']) ?></p>
                    </div>
                </div>
            </header>
            
            <div class="p-4 md:p-6">
                <?php if ($role == 'admin'): ?>
                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <p class="text-gray-500 text-sm">Total Hari Ini</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?></p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4 shadow-sm">
                        <p class="text-green-600 text-sm">Sukses</p>
                        <p class="text-2xl font-bold text-green-600"><?= $stats['sukses'] ?></p>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-4 shadow-sm">
                        <p class="text-yellow-600 text-sm">Pending</p>
                        <p class="text-2xl font-bold text-yellow-600"><?= $stats['pending'] ?></p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4 shadow-sm">
                        <p class="text-blue-600 text-sm">Total Sukses</p>
                        <p class="text-xl font-bold text-blue-600"><?= rupiah($stats['total_sukses']) ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Filter -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <form method="GET" class="flex flex-wrap gap-3">
                        <select name="jenis" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Semua Jenis</option>
                            <option value="pulsa" <?= $filterJenis == 'pulsa' ? 'selected' : '' ?>>Pulsa</option>
                            <option value="kuota" <?= $filterJenis == 'kuota' ? 'selected' : '' ?>>Kuota</option>
                            <option value="listrik" <?= $filterJenis == 'listrik' ? 'selected' : '' ?>>Listrik</option>
                            <option value="transfer" <?= $filterJenis == 'transfer' ? 'selected' : '' ?>>Transfer</option>
                        </select>
                        <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Semua Status</option>
                            <option value="success" <?= $filterStatus == 'success' ? 'selected' : '' ?>>Sukses</option>
                            <option value="pending" <?= $filterStatus == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="failed" <?= $filterStatus == 'failed' ? 'selected' : '' ?>>Gagal</option>
                        </select>
                        <input type="date" name="tanggal" value="<?= $filterTanggal ?>" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="riwayat.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Reset</a>
                    </form>
                </div>
                
                <!-- Transaksi List -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- Mobile View -->
                    <div class="md:hidden divide-y">
                        <?php if ($transaksi->num_rows > 0): ?>
                            <?php while ($trx = $transaksi->fetch_assoc()): ?>
                            <div class="p-4">
                                <div class="flex items-center justify-between mb-2">
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
                                            <p class="font-medium text-gray-800"><?= $trx['nama_produk'] ?? ucfirst($trx['jenis_transaksi']) ?></p>
                                            <p class="text-xs text-gray-500"><?= $trx['no_tujuan'] ?></p>
                                        </div>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full 
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
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500"><?= date('d M Y H:i', strtotime($trx['created_at'])) ?></span>
                                    <span class="font-semibold text-gray-800"><?= rupiah($trx['total_bayar']) ?></span>
                                </div>
                                <p class="text-xs text-gray-400 mt-1"><?= $trx['no_invoice'] ?></p>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2"></i>
                                <p>Tidak ada transaksi</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Desktop View -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Invoice</th>
                                    <?php if ($role == 'admin'): ?>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">User</th>
                                    <?php endif; ?>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Jenis</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Produk</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">No Tujuan</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Total</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php 
                                $transaksi->data_seek(0);
                                while ($trx = $transaksi->fetch_assoc()): 
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-mono"><?= $trx['no_invoice'] ?></td>
                                    <?php if ($role == 'admin'): ?>
                                    <td class="px-4 py-3 text-sm"><?= $trx['nama_lengkap'] ?></td>
                                    <?php endif; ?>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 rounded text-xs font-medium
                                            <?php 
                                            switch($trx['jenis_transaksi']) {
                                                case 'pulsa': echo 'bg-blue-100 text-blue-600'; break;
                                                case 'kuota': echo 'bg-green-100 text-green-600'; break;
                                                case 'listrik': echo 'bg-yellow-100 text-yellow-600'; break;
                                                case 'transfer': echo 'bg-purple-100 text-purple-600'; break;
                                            }
                                            ?>">
                                            <?= ucfirst($trx['jenis_transaksi']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm"><?= $trx['nama_produk'] ?? '-' ?></td>
                                    <td class="px-4 py-3 text-sm font-mono"><?= $trx['no_tujuan'] ?></td>
                                    <td class="px-4 py-3 text-sm font-semibold"><?= rupiah($trx['total_bayar']) ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 rounded-full text-xs 
                                            <?php 
                                            switch($trx['status']) {
                                                case 'success': echo 'bg-green-100 text-green-600'; break;
                                                case 'pending': echo 'bg-yellow-100 text-yellow-600'; break;
                                                case 'failed': echo 'bg-red-100 text-red-600'; break;
                                            }
                                            ?>">
                                            <?= ucfirst($trx['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($trx['created_at'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('closed');
            document.getElementById('overlay').classList.toggle('hidden');
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
