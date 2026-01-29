<?php
require_once 'config.php';
cekLogin();

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$_SESSION['saldo'] = getSaldo($user_id);

// Ambil produk kuota
$produkKuota = $conn->query("SELECT * FROM produk WHERE kategori_id = 2 AND status = 'active' ORDER BY provider, harga_jual");

// Grup berdasarkan provider
$produkByProvider = [];
while ($row = $produkKuota->fetch_assoc()) {
    $produkByProvider[$row['provider']][] = $row;
}

// Proses pembelian
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_hp = preg_replace('/[^0-9]/', '', $_POST['no_hp'] ?? '');
    $produk_id = intval($_POST['produk_id'] ?? 0);
    
    if (empty($no_hp) || strlen($no_hp) < 10) {
        setAlert('error', 'Nomor HP tidak valid!');
    } elseif ($produk_id == 0) {
        setAlert('error', 'Pilih paket data!');
    } else {
        $stmt = $conn->prepare("SELECT * FROM produk WHERE id = ?");
        $stmt->bind_param("i", $produk_id);
        $stmt->execute();
        $produk = $stmt->get_result()->fetch_assoc();
        
        if (!$produk) {
            setAlert('error', 'Produk tidak ditemukan!');
        } else {
            $saldo = getSaldo($user_id);
            $harga = $produk['harga_jual'];
            
            if ($saldo < $harga) {
                setAlert('error', 'Saldo tidak mencukupi! Silakan deposit terlebih dahulu.');
            } else {
                $invoice = generateInvoice();
                $saldo_sebelum = $saldo;
                $saldo_sesudah = $saldo - $harga;
                
                $stmt = $conn->prepare("INSERT INTO transaksi (user_id, produk_id, no_invoice, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan) VALUES (?, ?, ?, 'kuota', ?, ?, ?, 0, ?, ?, ?, 'success', 'Pembelian paket data berhasil')");
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
    header("Location: kuota.php");
    exit;
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paket Data - PPOB Express</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar { transition: transform 0.3s ease; }
        .sidebar.closed { transform: translateX(-100%); }
        @media (min-width: 768px) { .sidebar.closed { transform: translateX(0); } }
        .product-card.selected { border-color: #22c55e; background-color: #f0fdf4; }
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
                <a href="kuota.php" class="menu-item active flex items-center gap-3 px-4 py-3 rounded-lg bg-white/20 transition">
                    <i class="fas fa-wifi w-5"></i><span>Paket Data</span>
                </a>
                <a href="listrik.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-bolt w-5"></i><span>Token Listrik</span>
                </a>
                <a href="transfer.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-money-bill-transfer w-5"></i><span>Transfer Tunai</span>
                </a>
                <a href="riwayat.php" class="menu-item flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/20 transition">
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
                        <h2 class="text-lg font-semibold text-gray-800">Paket Data Internet</h2>
                    </div>
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-2 rounded-lg">
                        <p class="text-xs opacity-80">Saldo</p>
                        <p class="font-bold"><?= rupiah($_SESSION['saldo']) ?></p>
                    </div>
                </div>
            </header>
            
            <div class="p-4 md:p-6">
                <?php if ($alert): ?>
                <div class="mb-4 p-4 rounded-lg flex items-center gap-3 <?= $alert['type'] == 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <i class="fas <?= $alert['type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                    <span><?= $alert['message'] ?></span>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <!-- Input Nomor HP -->
                    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 mb-6">
                        <label class="block text-gray-700 font-semibold mb-2">Nomor Handphone</label>
                        <div class="relative">
                            <i class="fas fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="tel" name="no_hp" id="no_hp" 
                                   class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                   placeholder="Contoh: 081234567890" required maxlength="15">
                        </div>
                        <p class="text-sm text-gray-500 mt-2" id="providerInfo"></p>
                    </div>
                    
                    <input type="hidden" name="produk_id" id="produk_id" value="">
                    
                    <!-- Pilih Paket -->
                    <?php foreach ($produkByProvider as $provider => $produkList): ?>
                    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                            <span class="w-8 h-8 rounded-full bg-gradient-to-r from-green-500 to-emerald-500 text-white text-xs flex items-center justify-center font-bold">
                                <?= substr($provider, 0, 1) ?>
                            </span>
                            <?= $provider ?>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php foreach ($produkList as $p): ?>
                            <div class="product-card border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-green-400 transition"
                                 onclick="selectProduct(<?= $p['id'] ?>, '<?= $p['nama_produk'] ?>', <?= $p['harga_jual'] ?>)">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-bold text-gray-800"><?= $p['nama_produk'] ?></p>
                                        <p class="text-xs text-gray-500">Masa aktif 30 hari</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg text-green-600 font-bold"><?= rupiah($p['harga_jual']) ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Summary -->
                    <div id="summarySection" class="hidden bg-white rounded-xl shadow-sm p-4 md:p-6 sticky bottom-4">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-gray-500 text-sm">Produk dipilih:</p>
                                <p class="font-semibold text-gray-800" id="selectedProduct">-</p>
                            </div>
                            <div class="text-right">
                                <p class="text-gray-500 text-sm">Total Bayar:</p>
                                <p class="text-xl font-bold text-green-600" id="totalBayar">Rp 0</p>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3 rounded-lg font-semibold hover:from-green-600 hover:to-emerald-700 transition flex items-center justify-center gap-2">
                            <i class="fas fa-wifi"></i>
                            Beli Paket Data
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('closed');
            document.getElementById('overlay').classList.toggle('hidden');
        }
        
        function selectProduct(id, nama, harga) {
            document.querySelectorAll('.product-card').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            
            document.getElementById('produk_id').value = id;
            document.getElementById('selectedProduct').textContent = nama;
            document.getElementById('totalBayar').textContent = formatRupiah(harga);
            document.getElementById('summarySection').classList.remove('hidden');
        }
        
        function formatRupiah(num) {
            return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Deteksi provider
        document.getElementById('no_hp').addEventListener('input', function() {
            const prefix = this.value.substring(0, 4);
            let provider = '';
            
            if (['0811', '0812', '0813', '0821', '0822', '0823', '0851', '0852', '0853'].some(p => prefix.startsWith(p))) {
                provider = 'Telkomsel';
            } else if (['0814', '0815', '0816', '0855', '0856', '0857', '0858'].some(p => prefix.startsWith(p))) {
                provider = 'Indosat';
            } else if (['0817', '0818', '0819', '0859', '0877', '0878'].some(p => prefix.startsWith(p))) {
                provider = 'XL';
            } else if (['0895', '0896', '0897', '0898', '0899'].some(p => prefix.startsWith(p))) {
                provider = 'Tri';
            }
            
            document.getElementById('providerInfo').textContent = provider ? 'Provider: ' + provider : '';
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
