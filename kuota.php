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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom CSS konsisten dengan dashboard */
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
            border: 1px solid #e2e8f0;
        }
        
        .product-card {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .product-card:hover {
            border-color: #93c5fd;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
        }
        
        .product-card.selected {
            border-color: var(--primary-blue);
            background-color: var(--light-blue);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }
        
        .provider-badge {
            width: 32px;
            height: 32px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .input-field {
            transition: all 0.2s ease;
        }
        
        .input-field:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .sticky-summary {
            position: sticky;
            bottom: 20px;
            background: white;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            z-index: 10;
        }
        
        .btn-primary {
            background-color: var(--primary-blue);
            color: white;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--dark-blue);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
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
        
        /* Provider colors */
        .provider-telkomsel {
            background: linear-gradient(135deg, #e2001a 0%, #b00000 100%);
            color: white;
        }
        
        .provider-indosat {
            background: linear-gradient(135deg, #ff6900 0%, #cc5500 100%);
            color: white;
        }
        
        .provider-xl {
            background: linear-gradient(135deg, #00a9e0 0%, #0088b3 100%);
            color: white;
        }
        
        .provider-tri {
            background: linear-gradient(135deg, #ed1c24 0%, #b8141a 100%);
            color: white;
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
                <a href="kuota.php" class="menu-item active flex items-center gap-3 px-4 py-3 text-gray-700">
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
            <header class="bg-white shadow-sm sticky top-0 z-10">
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()" class="md:hidden text-gray-600 hover:text-blue-600">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">Paket Data Internet</h2>
                            <p class="text-sm text-gray-500">Pilih paket data sesuai kebutuhan Anda</p>
                        </div>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 px-4 py-2 rounded-lg">
                        <p class="text-xs text-gray-600">Saldo Tersedia</p>
                        <p class="font-bold text-blue-700"><?= rupiah($_SESSION['saldo']) ?></p>
                    </div>
                </div>
            </header>
            
            <div class="p-4 md:p-6 max-w-6xl mx-auto">
                <?php if ($alert): ?>
                <div class="alert mb-4 p-4 rounded-lg flex items-center gap-3 <?= $alert['type'] == 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
                    <i class="fas <?= $alert['type'] == 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> text-lg"></i>
                    <span class="font-medium"><?= $alert['message'] ?></span>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="space-y-6">
                    <!-- Input Nomor HP -->
                    <div class="card p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Masukkan Nomor Handphone</h3>
                        <div class="relative">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2">
                                <i class="fas fa-phone text-gray-400"></i>
                            </div>
                            <input type="tel" 
                                   name="no_hp" 
                                   id="no_hp" 
                                   class="input-field w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none"
                                   placeholder="Contoh: 081234567890" 
                                   required 
                                   maxlength="15"
                                   pattern="[0-9]*"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <p class="text-sm text-gray-500 mt-2 flex items-center gap-2" id="providerInfo">
                            <i class="fas fa-info-circle"></i>
                            <span id="providerText">Masukkan nomor HP untuk melihat provider</span>
                        </p>
                    </div>
                    
                    <input type="hidden" name="produk_id" id="produk_id" value="">
                    
                    <!-- Daftar Paket Data -->
                    <div class="space-y-6">
                        <?php foreach ($produkByProvider as $provider => $produkList): 
                            $providerClass = '';
                            switch(strtolower($provider)) {
                                case 'telkomsel': $providerClass = 'provider-telkomsel'; break;
                                case 'indosat': $providerClass = 'provider-indosat'; break;
                                case 'xl': $providerClass = 'provider-xl'; break;
                                case 'tri': $providerClass = 'provider-tri'; break;
                                default: $providerClass = 'bg-blue-600';
                            }
                        ?>
                        <div class="card p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="provider-badge rounded-full flex items-center justify-center <?= $providerClass ?>">
                                    <?= substr($provider, 0, 1) ?>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900"><?= $provider ?></h3>
                                    <p class="text-sm text-gray-500">Pilih paket <?= $provider ?> terbaik</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($produkList as $p): ?>
                                <div class="product-card card p-4"
                                     onclick="selectProduct(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nama_produk'], ENT_QUOTES) ?>', <?= $p['harga_jual'] ?>)">
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <div class="w-3/4">
                                                <p class="font-bold text-gray-900 truncate"><?= $p['nama_produk'] ?></p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    <i class="fas fa-calendar-day mr-1"></i>
                                                    Masa aktif: 30 hari
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center">
                                                    <div class="w-3 h-3 rounded-full bg-blue-600 hidden"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                                            <div>
                                                <p class="text-xs text-gray-500">Harga</p>
                                                <p class="text-lg font-bold text-blue-600"><?= rupiah($p['harga_jual']) ?></p>
                                            </div>
                                            <span class="text-xs text-gray-500"><?= formatBytes($p['nominal']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Summary Sticky -->
                    <div id="summarySection" class="sticky-summary hidden">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-6">
                                <div>
                                    <p class="text-sm text-gray-500 mb-1">Produk yang dipilih</p>
                                    <p class="font-semibold text-gray-900" id="selectedProduct">-</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500 mb-1">Total Pembayaran</p>
                                    <p class="text-2xl font-bold text-blue-600" id="totalBayar">Rp 0</p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <button type="button" onclick="resetSelection()" class="flex-1 py-3 px-4 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                                    <i class="fas fa-times mr-2"></i> Batal
                                </button>
                                <button type="submit" class="btn-primary flex-1 py-3 px-4 rounded-lg font-semibold flex items-center justify-center gap-2">
                                    <i class="fas fa-wifi"></i>
                                    Beli Paket Data
                                </button>
                            </div>
                        </div>
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
            // Remove all selections
            document.querySelectorAll('.product-card').forEach(el => {
                el.classList.remove('selected');
                el.querySelector('.w-3.h-3').classList.add('hidden');
            });
            
            // Add selection to clicked card
            const card = event.currentTarget;
            card.classList.add('selected');
            card.querySelector('.w-3.h-3').classList.remove('hidden');
            
            // Update form values
            document.getElementById('produk_id').value = id;
            document.getElementById('selectedProduct').textContent = nama;
            document.getElementById('totalBayar').textContent = formatRupiah(harga);
            document.getElementById('summarySection').classList.remove('hidden');
            
            // Scroll to summary
            document.getElementById('summarySection').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        function resetSelection() {
            document.querySelectorAll('.product-card').forEach(el => {
                el.classList.remove('selected');
                el.querySelector('.w-3.h-3').classList.add('hidden');
            });
            
            document.getElementById('produk_id').value = '';
            document.getElementById('selectedProduct').textContent = '-';
            document.getElementById('totalBayar').textContent = 'Rp 0';
            document.getElementById('summarySection').classList.add('hidden');
        }
        
        function formatRupiah(num) {
            return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Deteksi provider
        document.getElementById('no_hp').addEventListener('input', function() {
            const prefix = this.value.substring(0, 4);
            let provider = '';
            let providerColor = '';
            
            // Telkomsel
            if (['0811', '0812', '0813', '0821', '0822', '0823', '0851', '0852', '0853'].some(p => prefix.startsWith(p))) {
                provider = 'Telkomsel';
                providerColor = 'text-red-600';
            } 
            // Indosat
            else if (['0814', '0815', '0816', '0855', '0856', '0857', '0858'].some(p => prefix.startsWith(p))) {
                provider = 'Indosat';
                providerColor = 'text-orange-600';
            } 
            // XL
            else if (['0817', '0818', '0819', '0859', '0877', '0878'].some(p => prefix.startsWith(p))) {
                provider = 'XL';
                providerColor = 'text-blue-500';
            } 
            // Tri
            else if (['0895', '0896', '0897', '0898', '0899'].some(p => prefix.startsWith(p))) {
                provider = 'Tri';
                providerColor = 'text-red-500';
            }
            
            const providerText = document.getElementById('providerText');
            const providerInfo = document.getElementById('providerInfo');
            
            if (provider) {
                providerText.innerHTML = `<span class="font-medium ${providerColor}">${provider}</span> terdeteksi. Pilih paket ${provider} untuk nomor ini.`;
                providerInfo.classList.remove('text-gray-500');
            } else {
                providerText.textContent = 'Masukkan nomor HP untuk melihat provider';
                providerInfo.classList.add('text-gray-500');
            }
        });
        
        // Close sidebar on menu click (mobile)
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    toggleSidebar();
                }
            });
        });
        
        // Auto-hide summary when scrolling up
        let lastScrollTop = 0;
        window.addEventListener('scroll', function() {
            const summary = document.getElementById('summarySection');
            if (!summary.classList.contains('hidden')) {
                const st = window.pageYOffset || document.documentElement.scrollTop;
                if (st > lastScrollTop) {
                    // Scrolling down
                    summary.style.transform = 'translateY(0)';
                } else {
                    // Scrolling up
                    summary.style.transform = 'translateY(100px)';
                }
                lastScrollTop = st <= 0 ? 0 : st;
            }
        }, false);
    </script>
</body>
</html>
<?php 
$conn->close();

// Helper function untuk format bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>