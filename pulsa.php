<?php
require_once 'config.php';
cekLogin();

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$_SESSION['saldo'] = getSaldo($user_id);

// Ambil produk pulsa
$produkPulsa = $conn->query("SELECT * FROM produk WHERE kategori_id = 1 AND status = 'active' ORDER BY provider, nominal");

// Grup berdasarkan provider
$produkByProvider = [];
while ($row = $produkPulsa->fetch_assoc()) {
    $produkByProvider[$row['provider']][] = $row;
}

// Proses pembelian
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_hp = preg_replace('/[^0-9]/', '', $_POST['no_hp'] ?? '');
    $produk_id = intval($_POST['produk_id'] ?? 0);
    
    if (empty($no_hp) || strlen($no_hp) < 10) {
        setAlert('error', 'Nomor HP tidak valid!');
    } elseif ($produk_id == 0) {
        setAlert('error', 'Pilih nominal pulsa!');
    } else {
        // Ambil data produk
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
                // Proses transaksi
                $invoice = generateInvoice();
                $saldo_sebelum = $saldo;
                $saldo_sesudah = $saldo - $harga;
                
                $stmt = $conn->prepare("INSERT INTO transaksi (user_id, produk_id, no_invoice, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan) VALUES (?, ?, ?, 'pulsa', ?, ?, ?, 0, ?, ?, ?, 'success', 'Pembelian pulsa berhasil')");
                $stmt->bind_param("iissddddd", $user_id, $produk_id, $invoice, $no_hp, $produk['nominal'], $harga, $harga, $saldo_sebelum, $saldo_sesudah);
                
                if ($stmt->execute()) {
                    updateSaldo($user_id, $harga, 'kurang');
                    $_SESSION['saldo'] = getSaldo($user_id);
                    setAlert('success', 'Pembelian pulsa berhasil! Invoice: ' . $invoice);
                } else {
                    setAlert('error', 'Terjadi kesalahan. Silakan coba lagi.');
                }
            }
        }
    }
    header("Location: pulsa.php");
    exit;
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Isi Pulsa - PPOB Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom CSS konsisten dengan tema */
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #3b82f6;
            --light-blue: #eff6ff;
            --indigo-500: #6366f1;
            --indigo-600: #4f46e5;
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
            border-color: var(--indigo-500);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
        }
        
        .product-card.selected {
            border-color: var(--indigo-500);
            background-color: #eef2ff;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
        }
        
        .input-field {
            transition: all 0.2s ease;
        }
        
        .input-field:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-primary {
            background-color: var(--indigo-500);
            color: white;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--indigo-600);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
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
        
        .provider-badge {
            width: 40px;
            height: 40px;
            font-size: 16px;
            font-weight: 600;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            border: 1px solid #c7d2fe;
        }
        
        .provider-section {
            display: none;
        }
        
        .provider-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .quick-amount {
            transition: all 0.2s ease;
        }
        
        .quick-amount:hover {
            background-color: var(--light-blue);
            border-color: var(--primary-blue);
        }
        
        .quick-amount.selected {
            background-color: var(--light-blue);
            border-color: var(--primary-blue);
            color: var(--primary-blue);
            font-weight: 600;
        }
        
        /* New layout for better UX */
        .main-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        @media (min-width: 1024px) {
            .main-container {
                grid-template-columns: 2fr 1fr;
            }
        }
        
        .form-section {
            order: 1;
        }
        
        .summary-section {
            order: 2;
        }
        
        @media (min-width: 1024px) {
            .form-section {
                order: 1;
            }
            .summary-section {
                order: 2;
                position: sticky;
                top: 1rem;
                align-self: start;
            }
        }
        
        .validation-error {
            border-color: #ef4444;
            background-color: #fef2f2;
        }
        
        .validation-error:focus {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
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
        
        .provider-smartfren {
            background: linear-gradient(135deg, #ffcc00 0%, #e6b800 100%);
            color: #333;
        }
        
        .provider-axis {
            background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
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
                <a href="pulsa.php" class="menu-item active flex items-center gap-3 px-4 py-3 text-gray-700">
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
            <header class="bg-white shadow-sm sticky top-0 z-10">
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()" class="md:hidden text-gray-600 hover:text-blue-600">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">Isi Pulsa</h2>
                            <p class="text-sm text-gray-500">Isi pulsa semua operator dengan harga terbaik</p>
                        </div>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 px-4 py-2 rounded-lg">
                        <p class="text-xs text-gray-600">Saldo Tersedia</p>
                        <p class="font-bold text-blue-700"><?= rupiah($_SESSION['saldo']) ?></p>
                    </div>
                </div>
            </header>
            
            <div class="p-4 md:p-6 max-w-7xl mx-auto">
                <?php if ($alert): ?>
                <div class="alert mb-4 p-4 rounded-lg flex items-center gap-3 <?= $alert['type'] == 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
                    <i class="fas <?= $alert['type'] == 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> text-lg"></i>
                    <span class="font-medium"><?= $alert['message'] ?></span>
                </div>
                <?php endif; ?>
                
                <div class="main-container">
                    <!-- Form Section -->
                    <div class="form-section space-y-6">
                        <form method="POST" action="" id="formPulsa" class="space-y-6">
                            <!-- Input Nomor HP -->
                            <div class="card p-6">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-mobile-alt text-xl text-indigo-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Masukkan Nomor Handphone</h3>
                                        <p class="text-sm text-gray-500">Masukkan nomor HP yang akan diisi pulsa</p>
                                    </div>
                                </div>
                                
                                <div class="space-y-3">
                                    <div>
                                        <label class="block font-medium text-gray-900 mb-2 flex items-center gap-1">
                                            <i class="fas fa-phone text-gray-400 text-sm"></i>
                                            Nomor Handphone
                                        </label>
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
                                                   oninput="detectProvider(this.value)">
                                        </div>
                                    </div>
                                    
                                    <div id="providerDisplay" class="hidden p-3 rounded-lg bg-indigo-50 border border-indigo-200">
                                        <p class="text-sm text-gray-600">Operator terdeteksi:</p>
                                        <p class="font-semibold text-indigo-700" id="providerText">-</p>
                                    </div>
                                    
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Pulsa akan dikirim ke nomor ini dalam waktu 1-5 menit
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Pilih Nominal -->
                            <input type="hidden" name="produk_id" id="produk_id" value="">
                            
                            <!-- Quick Amount -->
                            <div class="card p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Pilih Nominal Cepat</h3>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <button type="button" onclick="setQuickAmount(5000)" class="quick-amount py-3 px-4 border border-gray-300 rounded-lg text-center hover:bg-gray-50">
                                        <p class="font-bold text-gray-900">5.000</p>
                                        <p class="text-sm text-gray-500">Rp 6.000</p>
                                    </button>
                                    <button type="button" onclick="setQuickAmount(10000)" class="quick-amount py-3 px-4 border border-gray-300 rounded-lg text-center hover:bg-gray-50">
                                        <p class="font-bold text-gray-900">10.000</p>
                                        <p class="text-sm text-gray-500">Rp 11.000</p>
                                    </button>
                                    <button type="button" onclick="setQuickAmount(25000)" class="quick-amount py-3 px-4 border border-gray-300 rounded-lg text-center hover:bg-gray-50">
                                        <p class="font-bold text-gray-900">25.000</p>
                                        <p class="text-sm text-gray-500">Rp 26.500</p>
                                    </button>
                                    <button type="button" onclick="setQuickAmount(50000)" class="quick-amount py-3 px-4 border border-gray-300 rounded-lg text-center hover:bg-gray-50">
                                        <p class="font-bold text-gray-900">50.000</p>
                                        <p class="text-sm text-gray-500">Rp 51.500</p>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Provider Sections -->
                            <?php foreach ($produkByProvider as $provider => $produkList): 
                                $providerClass = '';
                                switch(strtolower($provider)) {
                                    case 'telkomsel': $providerClass = 'provider-telkomsel'; break;
                                    case 'indosat': $providerClass = 'provider-indosat'; break;
                                    case 'xl': $providerClass = 'provider-xl'; break;
                                    case 'tri': $providerClass = 'provider-tri'; break;
                                    case 'smartfren': $providerClass = 'provider-smartfren'; break;
                                    case 'axis': $providerClass = 'provider-axis'; break;
                                    default: $providerClass = 'bg-blue-600 text-white';
                                }
                            ?>
                            <div class="provider-section card p-6" data-provider="<?= strtolower($provider) ?>">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="provider-badge rounded-full flex items-center justify-center <?= $providerClass ?>">
                                        <?= substr($provider, 0, 1) ?>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Pulsa <?= $provider ?></h3>
                                        <p class="text-sm text-gray-500">Pilih nominal pulsa <?= $provider ?></p>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                    <?php foreach ($produkList as $p): ?>
                                    <div class="product-card card p-4 text-center"
                                         onclick="selectProduct(<?= $p['id'] ?>, 'Pulsa <?= rupiah($p['nominal']) ?> <?= $provider ?>', <?= $p['harga_jual'] ?>)">
                                        <div class="space-y-2">
                                            <div>
                                                <p class="text-xl font-bold text-gray-900"><?= rupiah($p['nominal']) ?></p>
                                                <p class="text-xs text-gray-500 mt-1"><?= $provider ?></p>
                                            </div>
                                            <div>
                                                <p class="text-lg font-bold text-indigo-600"><?= rupiah($p['harga_jual']) ?></p>
                                                <div class="w-4 h-4 rounded-full border-2 border-gray-300 mx-auto mt-2 flex items-center justify-center">
                                                    <div class="w-2 h-2 rounded-full bg-indigo-500 hidden"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </form>
                    </div>
                    
                    <!-- Summary Section -->
                    <div class="summary-section">
                        <div class="card p-6 summary-card">
                            <h4 class="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                <i class="fas fa-shopping-cart text-indigo-600"></i>
                                Ringkasan Pembelian
                            </h4>
                            
                            <div class="space-y-4">
                                <div class="flex justify-between items-center pb-3 border-b border-indigo-200">
                                    <span class="text-sm text-gray-600">Nomor HP</span>
                                    <span id="summaryNoHP" class="font-medium text-gray-900 font-mono">-</span>
                                </div>
                                
                                <div class="flex justify-between items-center pb-3 border-b border-indigo-200">
                                    <span class="text-sm text-gray-600">Provider</span>
                                    <span id="summaryProvider" class="font-medium text-gray-900">-</span>
                                </div>
                                
                                <div class="flex justify-between items-center pb-3 border-b border-indigo-200">
                                    <span class="text-sm text-gray-600">Produk</span>
                                    <span id="summaryProduct" class="font-medium text-gray-900 text-right">Belum dipilih</span>
                                </div>
                                
                                <div class="rounded-lg p-4 bg-white border border-indigo-200">
                                    <div class="flex justify-between items-center">
                                        <span class="font-semibold text-gray-900">Total Bayar</span>
                                        <span id="summaryTotal" class="text-2xl font-bold text-indigo-600">Rp 0</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Dipotong dari saldo Anda</p>
                                </div>
                                
                                <div class="pt-4">
                                    <button type="submit" form="formPulsa" class="btn-primary w-full py-3 px-4 rounded-lg font-semibold flex items-center justify-center gap-2">
                                        <i class="fas fa-bolt"></i>
                                        Beli Pulsa Sekarang
                                    </button>
                                    
                                    <div class="mt-4 p-3 bg-blue-50 border border-blue-100 rounded-lg">
                                        <p class="text-xs text-gray-600">
                                            <i class="fas fa-clock text-blue-500 mr-1"></i>
                                            Pulsa akan dikirim dalam 1-5 menit
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Info Box -->
                        <div class="card p-4 mt-4 bg-green-50 border border-green-200">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-percentage text-green-600 mt-1"></i>
                                <div>
                                    <p class="text-sm font-medium text-green-800 mb-1">Promo Spesial!</p>
                                    <ul class="text-xs text-green-700 space-y-1">
                                        <li>• Cashback 5% untuk pembelian pulsa min. Rp 50.000</li>
                                        <li>• Bonus SMS untuk pembelian pulsa Rp 100.000</li>
                                        <li>• Cashback langsung ke saldo Anda</li>
                                        <li>• Berlaku untuk semua operator</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
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
        
        function detectProvider(phoneNumber) {
            const prefix = phoneNumber.substring(0, 4);
            let provider = '';
            let providerClass = '';
            
            // Telkomsel
            if (['0811', '0812', '0813', '0821', '0822', '0823', '0851', '0852', '0853'].some(p => prefix.startsWith(p))) {
                provider = 'Telkomsel';
                providerClass = 'provider-telkomsel';
            } 
            // Indosat
            else if (['0814', '0815', '0816', '0855', '0856', '0857', '0858'].some(p => prefix.startsWith(p))) {
                provider = 'Indosat';
                providerClass = 'provider-indosat';
            } 
            // XL
            else if (['0817', '0818', '0819', '0859', '0877', '0878'].some(p => prefix.startsWith(p))) {
                provider = 'XL';
                providerClass = 'provider-xl';
            } 
            // Tri
            else if (['0895', '0896', '0897', '0898', '0899'].some(p => prefix.startsWith(p))) {
                provider = 'Tri';
                providerClass = 'provider-tri';
            }
            
            const providerDisplay = document.getElementById('providerDisplay');
            const providerText = document.getElementById('providerText');
            const summaryNoHP = document.getElementById('summaryNoHP');
            const summaryProvider = document.getElementById('summaryProvider');
            
            if (provider) {
                providerDisplay.classList.remove('hidden');
                providerText.textContent = provider;
                providerText.className = `font-semibold ${providerClass.replace('provider-', 'text-')}`;
                
                // Show only relevant provider section
                document.querySelectorAll('.provider-section').forEach(section => {
                    section.classList.remove('active');
                });
                const providerSection = document.querySelector(`.provider-section[data-provider="${provider.toLowerCase()}"]`);
                if (providerSection) {
                    providerSection.classList.add('active');
                }
                
                // Update summary
                summaryNoHP.textContent = phoneNumber;
                summaryProvider.textContent = provider;
            } else {
                providerDisplay.classList.add('hidden');
                summaryNoHP.textContent = '-';
                summaryProvider.textContent = '-';
                
                // Hide all provider sections
                document.querySelectorAll('.provider-section').forEach(section => {
                    section.classList.remove('active');
                });
            }
        }
        
        function setQuickAmount(nominal) {
            // Remove all quick amount selections
            document.querySelectorAll('.quick-amount').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Add selection to clicked button
            event.currentTarget.classList.add('selected');
            
            // Simulate selecting a product (you would need to match this with actual product IDs)
            const price = calculatePriceFromNominal(nominal);
            selectProduct(0, `Pulsa ${formatRupiah(nominal)}`, price);
        }
        
        function calculatePriceFromNominal(nominal) {
            // This is a simplified calculation - in real app, you would get this from database
            const priceMap = {
                5000: 6000,
                10000: 11000,
                25000: 26500,
                50000: 51500,
                100000: 101500,
                200000: 202000,
                500000: 502500,
                1000000: 1003000
            };
            return priceMap[nominal] || nominal + Math.ceil(nominal * 0.03);
        }
        
        function selectProduct(id, nama, harga) {
            // Remove all selections
            document.querySelectorAll('.product-card').forEach(el => {
                el.classList.remove('selected');
                el.querySelector('.w-2.h-2').classList.add('hidden');
            });
            
            // Add selection to clicked card
            const card = event.currentTarget;
            card.classList.add('selected');
            card.querySelector('.w-2.h-2').classList.remove('hidden');
            
            // Update form values
            document.getElementById('produk_id').value = id;
            
            // Update summary
            document.getElementById('summaryProduct').textContent = nama;
            document.getElementById('summaryTotal').textContent = formatRupiah(harga);
        }
        
        function formatRupiah(num) {
            return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Form validation
        document.getElementById('formPulsa').addEventListener('submit', function(e) {
            const noHP = document.getElementById('no_hp').value.trim();
            const productId = document.getElementById('produk_id').value;
            const summaryTotal = document.getElementById('summaryTotal').textContent;
            
            let isValid = true;
            let errorMessage = '';
            let errorElement = null;
            
            // Validate phone number
            if (!noHP || noHP.length < 10) {
                errorMessage = 'Nomor HP tidak valid (minimal 10 digit)';
                isValid = false;
                errorElement = document.getElementById('no_hp');
            } 
            // Validate product selection
            else if (!productId || productId == 0) {
                errorMessage = 'Silakan pilih nominal pulsa';
                isValid = false;
                errorElement = document.querySelector('.product-card') || document.querySelector('.quick-amount');
            }
            
            if (!isValid) {
                e.preventDefault();
                
                // Add visual error to the element
                if (errorElement) {
                    errorElement.classList.add('validation-error');
                    if (errorElement.tagName === 'INPUT') {
                        errorElement.classList.add('border-red-500');
                    }
                    errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (errorElement.tagName === 'INPUT') {
                        errorElement.focus();
                    }
                }
                
                // Show error message
                alert('Error: ' + errorMessage);
            } else {
                // Final confirmation
                const summaryProduct = document.getElementById('summaryProduct').textContent;
                const summaryProvider = document.getElementById('summaryProvider').textContent;
                
                if (!confirm(`Konfirmasi Pembelian Pulsa:\n\nNomor HP: ${noHP}\nProvider: ${summaryProvider}\nProduk: ${summaryProduct}\nTotal: ${summaryTotal}\n\nLanjutkan?`)) {
                    e.preventDefault();
                }
            }
        });
        
        // Remove validation error on input
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('validation-error', 'border-red-500');
            });
        });
        
        // Close sidebar on menu click (mobile)
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    toggleSidebar();
                }
            });
        });
        
        // Auto-format phone number
        document.getElementById('no_hp').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                value = value.substring(0, 15);
                e.target.value = value;
            }
        });
        
        // Show first provider section by default on page load
        document.addEventListener('DOMContentLoaded', function() {
            const firstProvider = document.querySelector('.provider-section');
            if (firstProvider) {
                firstProvider.classList.add('active');
            }
        });
    </script>
</body>
</html>
<?php 
$conn->close();
?>