<?php
require_once 'config.php';
cekLogin();

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
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
    // Validasi CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: pulsa.php");
        exit;
    }
    
    $no_hp = preg_replace('/[^0-9]/', '', $_POST['no_hp'] ?? '');
    $produk_id = intval($_POST['produk_id'] ?? 0);
    
    // Validasi input
    if (empty($no_hp) || strlen($no_hp) < 10 || strlen($no_hp) > 15) {
        setAlert('error', 'Nomor HP tidak valid! (10-15 digit)');
    } elseif ($produk_id == 0 || $produk_id > 100000) {
        setAlert('error', 'Pilih nominal pulsa yang valid!');
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
            /* Transisi smooth untuk collapse */
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                        width   0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(0);
        }

        /* State: sidebar tersembunyi (dipakai di mobile DAN desktop saat di-toggle) */
        #sidebar.sidebar-hidden {
            transform: translateX(-100%);
        }

        /* ── Main Content ── */
        #main-content {
            flex: 1;
            min-width: 0;
            margin-left: 256px; /* sama dengan lebar sidebar */
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Saat sidebar hidden → main pakai full width */
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

        /* ── Custom Styles for Pulsa Page ── */
        .product-card {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .product-card:hover {
            border-color: #6366f1;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
        }
        
        .product-card.selected {
            border-color: #6366f1;
            background-color: #eef2ff;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
        }
        
        .input-field {
            transition: all 0.2s ease;
        }
        
        .input-field:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-primary {
            background-color: #6366f1;
            color: white;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: #4f46e5;
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
            background-color: #eff6ff;
            border-color: #2563eb;
        }
        
        .quick-amount.selected {
            background-color: #eff6ff;
            border-color: #2563eb;
            color: #2563eb;
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
        <a href="index.php"    class="menu-item"><i class="fas fa-home"></i><span>Dashboard</span></a>
        <a href="pulsa.php"    class="menu-item active"><i class="fas fa-mobile-alt"></i><span>Isi Pulsa</span></a>
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
    <div class="p-4 md:p-6 max-w-7xl mx-auto">
        
        <!-- Page Title -->
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900">Isi Pulsa</h2>
            <p class="text-sm text-gray-500 mt-0.5">Isi pulsa semua operator dengan harga terbaik</p>
        </div>

        <?php if ($alert): ?>
        <div class="alert mb-6 p-4 rounded-lg flex items-center gap-3 <?= $alert['type'] == 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <i class="fas <?= $alert['type'] == 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> text-lg"></i>
            <span class="font-medium"><?= $alert['message'] ?></span>
        </div>
        <?php endif; ?>
        
        <div class="main-container">
            <!-- Form Section -->
            <div class="form-section space-y-6">
                <form method="POST" action="" id="formPulsa" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
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

/**
 * Cek apakah layar ≥ 768px (desktop)
 */
function isDesktop() {
    return window.innerWidth >= 768;
}

/**
 * Buka sidebar
 */
function openSidebar() {
    if (isDesktop()) {
        // Desktop: geser sidebar masuk, beri margin pada main
        sidebar.classList.remove('sidebar-hidden');
        mainContent.classList.remove('sidebar-hidden');
        localStorage.setItem(STORAGE_KEY, 'true');
    } else {
        // Mobile: slide in + tampilkan overlay
        sidebar.classList.add('sidebar-open');
        overlay.classList.add('show');
    }
}

/**
 * Tutup sidebar
 */
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

/**
 * Toggle sidebar — dipanggil tombol hamburger
 */
function toggleSidebar() {
    if (isDesktop()) {
        // Desktop: cek apakah saat ini hidden
        const isHidden = sidebar.classList.contains('sidebar-hidden');
        isHidden ? openSidebar() : closeSidebar();
    } else {
        // Mobile: cek apakah saat ini open
        const isOpen = sidebar.classList.contains('sidebar-open');
        isOpen ? closeSidebar() : openSidebar();
    }
}

/**
 * Inisialisasi state saat halaman dimuat
 */
function initSidebar() {
    if (isDesktop()) {
        // Desktop: baca dari localStorage (default: terbuka)
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'false') {
            // User sebelumnya menutup → tetap tutup
            sidebar.classList.add('sidebar-hidden');
            mainContent.classList.add('sidebar-hidden');
        } else {
            // Default terbuka
            sidebar.classList.remove('sidebar-hidden');
            sidebar.classList.remove('sidebar-open');
            mainContent.classList.remove('sidebar-hidden');
            overlay.classList.remove('show');
        }
    } else {
        // Mobile: selalu mulai tertutup
        sidebar.classList.remove('sidebar-hidden');
        sidebar.classList.remove('sidebar-open');
        mainContent.classList.remove('sidebar-hidden'); // margin-left di-override CSS
        overlay.classList.remove('show');
    }
}

// Jalankan saat load
document.addEventListener('DOMContentLoaded', initSidebar);

// Re-inisialisasi saat resize (pindah breakpoint)
let resizeTimer;
window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(initSidebar, 100);
});

// Tutup sidebar mobile saat klik menu item
document.querySelectorAll('.menu-item').forEach(item => {
    item.addEventListener('click', () => {
        if (!isDesktop()) closeSidebar();
    });
});

// ═══════════════════════════════════════════════════════
//  PULSA PAGE FUNCTIONS
// ═══════════════════════════════════════════════════════

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