<?php
require_once 'config.php';
cekLogin();

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$_SESSION['saldo'] = getSaldo($user_id);
$role = $_SESSION['role'];

$biayaAdmin = floatval(getPengaturan('biaya_admin_transfer') ?? 2500);
$minTransfer = floatval(getPengaturan('minimal_transfer') ?? 10000);
$maxTransfer = floatval(getPengaturan('maksimal_transfer') ?? 50000000);

// Daftar Bank
$daftarBank = [
    'BCA' => 'Bank Central Asia',
    'BNI' => 'Bank Negara Indonesia',
    'BRI' => 'Bank Rakyat Indonesia',
    'Mandiri' => 'Bank Mandiri',
    'CIMB' => 'CIMB Niaga',
    'Danamon' => 'Bank Danamon',
    'Permata' => 'Bank Permata',
    'BTN' => 'Bank Tabungan Negara',
    'OCBC' => 'OCBC NISP',
    'Maybank' => 'Maybank Indonesia',
    'BSI' => 'Bank Syariah Indonesia',
    'DANA' => 'DANA',
    'OVO' => 'OVO',
    'GoPay' => 'GoPay',
    'ShopeePay' => 'ShopeePay'
];

// Proses transfer
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validasi CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: transfer.php");
        exit;
    }
    
    $bank = $_POST['bank'] ?? '';
    $no_rekening = preg_replace('/[^0-9]/', '', $_POST['no_rekening'] ?? '');
    $nama_penerima = htmlspecialchars(trim($_POST['nama_penerima'] ?? ''), ENT_QUOTES, 'UTF-8');
    $nominal = floatval(preg_replace('/[^0-9]/', '', $_POST['nominal'] ?? 0));
    
    // Validasi input yang lebih ketat
    if (empty($bank) || !isset($daftarBank[$bank])) {
        setAlert('error', 'Pilih bank tujuan yang valid!');
    } elseif (empty($no_rekening) || strlen($no_rekening) < 8 || strlen($no_rekening) > 20) {
        setAlert('error', 'Nomor rekening tidak valid! (8-20 digit)');
    } elseif (empty($nama_penerima) || strlen($nama_penerima) < 3 || strlen($nama_penerima) > 100) {
        setAlert('error', 'Nama penerima harus 3-100 karakter!');
    } elseif ($nominal < $minTransfer || $nominal <= 0) {
        setAlert('error', 'Minimal transfer ' . rupiah($minTransfer));
    } elseif ($nominal > $maxTransfer) {
        setAlert('error', 'Maksimal transfer ' . rupiah($maxTransfer));
    } elseif ($nominal > 100000000) {
        setAlert('error', 'Nominal transfer terlalu besar!');
    } else {
        $saldo = getSaldo($user_id);
        $totalBayar = $nominal + $biayaAdmin;
        
        if ($saldo < $totalBayar) {
            setAlert('error', 'Saldo tidak mencukupi!');
        } else {
            $invoice = generateInvoice();
            $saldo_sebelum = $saldo;
            $saldo_sesudah = $saldo - $totalBayar;
            $keterangan = "Transfer ke " . $daftarBank[$bank] . " - " . $nama_penerima;
            
            $conn->begin_transaction();
            try {
                // Insert transaksi
                $stmt = $conn->prepare("INSERT INTO transaksi (user_id, produk_id, no_invoice, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan) VALUES (?, NULL, ?, 'transfer', ?, ?, ?, ?, ?, ?, ?, 'success', ?)");
                $stmt->bind_param("issddddds", $user_id, $invoice, $no_rekening, $nominal, $nominal, $biayaAdmin, $totalBayar, $saldo_sebelum, $saldo_sesudah, $keterangan);
                $stmt->execute();
                $transaksi_id = $conn->insert_id;
                
                // Insert detail transfer
                $stmt2 = $conn->prepare("INSERT INTO transfer_tunai (transaksi_id, bank_tujuan, no_rekening, nama_penerima) VALUES (?, ?, ?, ?)");
                $stmt2->bind_param("isss", $transaksi_id, $bank, $no_rekening, $nama_penerima);
                $stmt2->execute();
                
                // Update saldo
                updateSaldo($user_id, $totalBayar, 'kurang');
                
                $conn->commit();
                $_SESSION['saldo'] = getSaldo($user_id);
                setAlert('success', 'Transfer berhasil! Invoice: ' . $invoice);
            } catch (Exception $e) {
                $conn->rollback();
                setAlert('error', 'Terjadi kesalahan. Silakan coba lagi.');
            }
        }
    }
    header("Location: transfer.php");
    exit;
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Tunai - PPOB Express</title>
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

        /* ── Custom Styles for Transfer Page ── */
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #3b82f6;
            --light-blue: #eff6ff;
            --purple-500: #8b5cf6;
            --purple-600: #7c3aed;
        }
        
        .bank-card {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .bank-card:hover {
            border-color: var(--purple-500);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.1);
        }
        
        .bank-card.selected {
            border-color: var(--purple-500);
            background-color: #f5f3ff;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.15);
        }
        
        .input-field {
            transition: all 0.2s ease;
        }
        
        .input-field:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-primary {
            background-color: var(--purple-500);
            color: white;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--purple-600);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
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
        
        .bank-icon {
            width: 48px;
            height: 48px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            border: 1px solid #ddd6fe;
        }
        
        .summary-total {
            background: linear-gradient(135deg, var(--purple-500) 0%, var(--purple-600) 100%);
            color: white;
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
        <a href="pulsa.php"    class="menu-item"><i class="fas fa-mobile-alt"></i><span>Isi Pulsa</span></a>
        <a href="kuota.php"    class="menu-item"><i class="fas fa-wifi"></i><span>Paket Data</span></a>
        <a href="listrik.php"  class="menu-item"><i class="fas fa-bolt"></i><span>Token Listrik</span></a>
        <a href="transfer.php" class="menu-item active"><i class="fas fa-money-bill-transfer"></i><span>Transfer Tunai</span></a>
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
            <h2 class="text-xl font-bold text-gray-900">Transfer Tunai</h2>
            <p class="text-sm text-gray-500 mt-0.5">Transfer ke berbagai bank dan e-wallet</p>
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
                <form method="POST" action="" id="formTransfer" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    
                    <!-- Pilih Bank -->
                    <div class="card p-6">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-building-columns text-xl text-purple-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Pilih Bank / E-Wallet Tujuan</h3>
                                <p class="text-sm text-gray-500">Pilih bank atau e-wallet untuk transfer</p>
                            </div>
                        </div>
                        
                        <input type="hidden" name="bank" id="bank" value="">
                        <input type="hidden" id="bankName" value="">
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3">
                            <?php foreach ($daftarBank as $kode => $nama): 
                                $isEWallet = in_array($kode, ['DANA', 'OVO', 'GoPay', 'ShopeePay']);
                                $iconClass = $isEWallet ? 'bg-pink-100 text-pink-600' : 'bg-blue-100 text-blue-600';
                            ?>
                            <div class="bank-card card p-3 text-center"
                                 onclick="selectBank('<?= $kode ?>', '<?= $nama ?>')">
                                <div class="bank-icon <?= $iconClass ?> rounded-full mx-auto mb-2 flex items-center justify-center">
                                    <i class="fas <?= $isEWallet ? 'fa-mobile-screen' : 'fa-landmark' ?>"></i>
                                </div>
                                <p class="text-sm font-medium text-gray-900"><?= $kode ?></p>
                                <p class="text-xs text-gray-500 truncate" title="<?= $nama ?>"><?= $isEWallet ? 'E-Wallet' : substr($nama, 0, 12) . (strlen($nama) > 12 ? '...' : '') ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div id="selectedBankDisplay" class="hidden mt-4 p-3 bg-purple-50 border border-purple-200 rounded-lg">
                            <p class="text-sm text-gray-600">Bank terpilih:</p>
                            <p class="font-semibold text-purple-700" id="bankTujuanText">-</p>
                        </div>
                    </div>
                    
                    <!-- Detail Transfer -->
                    <div class="card p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">Detail Transfer</h3>
                        
                        <div class="space-y-5">
                            <div>
                                <label class="block font-medium text-gray-900 mb-2 flex items-center gap-1">
                                    <i class="fas fa-credit-card text-gray-400 text-sm"></i>
                                    Nomor Rekening / Akun
                                </label>
                                <input type="text" 
                                       name="no_rekening" 
                                       id="no_rekening" 
                                       class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none"
                                       placeholder="Contoh: 1234567890" 
                                       required
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                <p class="text-xs text-gray-500 mt-1">Masukkan nomor rekening atau nomor akun e-wallet</p>
                            </div>
                            
                            <div>
                                <label class="block font-medium text-gray-900 mb-2 flex items-center gap-1">
                                    <i class="fas fa-user text-gray-400 text-sm"></i>
                                    Nama Penerima
                                </label>
                                <input type="text" 
                                       name="nama_penerima" 
                                       id="nama_penerima" 
                                       class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none"
                                       placeholder="Nama sesuai rekening / akun" 
                                       required>
                            </div>
                            
                            <div>
                                <label class="block font-medium text-gray-900 mb-2 flex items-center gap-1">
                                    <i class="fas fa-money-bill-wave text-gray-400 text-sm"></i>
                                    Nominal Transfer
                                </label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-medium">Rp</span>
                                    <input type="text" 
                                           name="nominal" 
                                           id="nominal" 
                                           class="input-field w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none"
                                           placeholder="0" 
                                           required 
                                           oninput="formatNominal(this); calculateTotal();">
                                </div>
                                <div class="mt-3">
                                    <p class="text-xs text-gray-500 mb-2">Pilih nominal cepat:</p>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" onclick="setNominal(50000)" class="quick-amount text-xs px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">50.000</button>
                                        <button type="button" onclick="setNominal(100000)" class="quick-amount text-xs px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">100.000</button>
                                        <button type="button" onclick="setNominal(200000)" class="quick-amount text-xs px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">200.000</button>
                                        <button type="button" onclick="setNominal(500000)" class="quick-amount text-xs px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">500.000</button>
                                        <button type="button" onclick="setNominal(1000000)" class="quick-amount text-xs px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">1.000.000</button>
                                    </div>
                                </div>
                                <div class="flex justify-between mt-3">
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Minimal: <?= rupiah($minTransfer) ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Maksimal: <?= rupiah($maxTransfer) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Summary Section -->
            <div class="summary-section">
                <div class="card p-6 summary-card">
                    <h4 class="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
                        <i class="fas fa-receipt text-purple-600"></i>
                        Ringkasan Transfer
                    </h4>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-center pb-3 border-b border-purple-200">
                            <span class="text-sm text-gray-600">Bank Tujuan</span>
                            <span id="summaryBank" class="font-medium text-gray-900 text-right">Belum dipilih</span>
                        </div>
                        
                        <div class="flex justify-between items-center pb-3 border-b border-purple-200">
                            <span class="text-sm text-gray-600">Nominal Transfer</span>
                            <span id="summaryNominal" class="font-medium text-gray-900">Rp 0</span>
                        </div>
                        
                        <div class="flex justify-between items-center pb-3 border-b border-purple-200">
                            <span class="text-sm text-gray-600">Biaya Admin</span>
                            <span class="font-medium text-gray-900"><?= rupiah($biayaAdmin) ?></span>
                        </div>
                        
                        <div class="rounded-lg p-4 summary-total">
                            <div class="flex justify-between items-center">
                                <span class="font-semibold">Total Bayar</span>
                                <span id="summaryTotal" class="text-xl font-bold">Rp 0</span>
                            </div>
                            <p class="text-xs opacity-90 mt-1">Dipotong dari saldo Anda</p>
                        </div>
                        
                        <div class="pt-4">
                            <div class="flex gap-3">
                                <button type="button" onclick="resetForm()" class="flex-1 py-3 px-4 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                                    <i class="fas fa-redo mr-2"></i> Reset
                                </button>
                                <button type="submit" form="formTransfer" class="btn-primary flex-1 py-3 px-4 rounded-lg font-semibold flex items-center justify-center gap-2">
                                    <i class="fas fa-paper-plane"></i>
                                    Transfer Sekarang
                                </button>
                            </div>
                            
                            <div class="mt-4 p-3 bg-blue-50 border border-blue-100 rounded-lg">
                                <p class="text-xs text-gray-600">
                                    <i class="fas fa-shield-alt text-blue-500 mr-1"></i>
                                    Transfer akan diproses secara instan
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Info Box -->
                <div class="card p-4 mt-4 bg-yellow-50 border border-yellow-200">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-lightbulb text-yellow-600 mt-1"></i>
                        <div>
                            <p class="text-sm font-medium text-yellow-800 mb-1">Tips Transfer Aman</p>
                            <ul class="text-xs text-yellow-700 space-y-1">
                                <li>• Pastikan nomor rekening benar</li>
                                <li>• Nama penerima sesuai dengan rekening</li>
                                <li>• Cek saldo Anda sebelum transfer</li>
                                <li>• Simpan invoice sebagai bukti</li>
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
//  TRANSFER PAGE LOGIC
// ═══════════════════════════════════════════════════════

const biayaAdmin = <?= $biayaAdmin ?>;
const minTransfer = <?= $minTransfer ?>;
const maxTransfer = <?= $maxTransfer ?>;

function selectBank(kode, nama) {
    // Remove all selections
    document.querySelectorAll('.bank-card').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Add selection to clicked card
    const card = event.currentTarget;
    card.classList.add('selected');
    
    // Update form values
    document.getElementById('bank').value = kode;
    document.getElementById('bankName').value = nama;
    
    // Update displays
    document.getElementById('bankTujuanText').textContent = `${nama} (${kode})`;
    document.getElementById('summaryBank').textContent = kode;
    document.getElementById('selectedBankDisplay').classList.remove('hidden');
    
    // Remove any existing bank validation errors
    const bankInput = document.getElementById('bank');
    if (bankInput.parentElement.classList.contains('validation-error')) {
        bankInput.parentElement.classList.remove('validation-error');
    }
}

function setNominal(nominal) {
    // Remove all quick amount selections
    document.querySelectorAll('.quick-amount').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Add selection to clicked button
    event.currentTarget.classList.add('selected');
    
    // Set the nominal
    const input = document.getElementById('nominal');
    input.value = nominal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    
    // Validate and calculate
    formatNominal(input);
    calculateTotal();
}

function formatNominal(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    let num = parseInt(value) || 0;
    
    // Remove existing validation classes
    input.classList.remove('validation-error', 'border-red-500');
    
    // Validate min/max
    if (num > 0) {
        if (num < minTransfer) {
            input.classList.add('validation-error', 'border-red-500');
        } else if (num > maxTransfer) {
            input.classList.add('validation-error', 'border-red-500');
        } else {
            input.classList.add('border-gray-300');
        }
    }
    
    input.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function calculateTotal() {
    let nominalValue = document.getElementById('nominal').value;
    let nominal = parseInt(nominalValue.replace(/\./g, '')) || 0;
    let total = nominal + biayaAdmin;
    
    // Update displays
    document.getElementById('summaryNominal').textContent = formatRupiah(nominal);
    document.getElementById('summaryTotal').textContent = formatRupiah(total);
}

function formatRupiah(num) {
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function resetForm() {
    // Reset bank selection
    document.querySelectorAll('.bank-card').forEach(el => {
        el.classList.remove('selected');
    });
    document.getElementById('bank').value = '';
    document.getElementById('bankName').value = '';
    document.getElementById('selectedBankDisplay').classList.add('hidden');
    document.getElementById('summaryBank').textContent = 'Belum dipilih';
    
    // Reset quick amounts
    document.querySelectorAll('.quick-amount').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Reset form fields
    document.getElementById('no_rekening').value = '';
    document.getElementById('nama_penerima').value = '';
    document.getElementById('nominal').value = '';
    document.getElementById('nominal').classList.remove('validation-error', 'border-red-500');
    document.getElementById('nominal').classList.add('border-gray-300');
    
    // Reset summary
    document.getElementById('summaryNominal').textContent = 'Rp 0';
    document.getElementById('summaryTotal').textContent = 'Rp 0';
    
    // Focus on bank selection
    const firstBank = document.querySelector('.bank-card');
    if (firstBank) {
        firstBank.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// Form validation
document.getElementById('formTransfer').addEventListener('submit', function(e) {
    const bank = document.getElementById('bank').value;
    const noRekening = document.getElementById('no_rekening').value.trim();
    const namaPenerima = document.getElementById('nama_penerima').value.trim();
    const nominalValue = document.getElementById('nominal').value;
    const nominal = parseInt(nominalValue.replace(/\./g, '')) || 0;
    
    let isValid = true;
    let errorMessage = '';
    let errorElement = null;
    
    // Validate bank
    if (!bank) {
        errorMessage = 'Silakan pilih bank tujuan';
        isValid = false;
        errorElement = document.querySelector('.bank-card');
    } 
    // Validate rekening number
    else if (noRekening.length < 8) {
        errorMessage = 'Nomor rekening minimal 8 digit';
        isValid = false;
        errorElement = document.getElementById('no_rekening');
    } 
    // Validate recipient name
    else if (!namaPenerima) {
        errorMessage = 'Masukkan nama penerima';
        isValid = false;
        errorElement = document.getElementById('nama_penerima');
    } 
    // Validate amount
    else if (nominal < minTransfer) {
        errorMessage = `Minimal transfer ${formatRupiah(minTransfer)}`;
        isValid = false;
        errorElement = document.getElementById('nominal');
    } else if (nominal > maxTransfer) {
        errorMessage = `Maksimal transfer ${formatRupiah(maxTransfer)}`;
        isValid = false;
        errorElement = document.getElementById('nominal');
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
            errorElement.focus();
        }
        
        // Show error message
        alert('Error: ' + errorMessage);
    } else {
        // Final confirmation
        const bankName = document.getElementById('bankName').value;
        const total = nominal + biayaAdmin;
        
        if (!confirm(`Konfirmasi Transfer:\n\nBank: ${bankName}\nRekening: ${noRekening}\nPenerima: ${namaPenerima}\nNominal: ${formatRupiah(nominal)}\nBiaya Admin: ${formatRupiah(biayaAdmin)}\nTotal: ${formatRupiah(total)}\n\nLanjutkan?`)) {
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

// Initialize calculations
calculateTotal();

// Auto-select bank on enter key in rekening field
document.getElementById('no_rekening').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('nama_penerima').focus();
    }
});
</script>

</body>
</html>
<?php 
$conn->close();
?>