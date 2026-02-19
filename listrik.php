<?php
require_once 'config.php';
cekLogin();

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$_SESSION['saldo'] = getSaldo($user_id);

// Ambil produk token listrik
$produkListrik = $conn->query("SELECT * FROM produk WHERE kategori_id = 3 AND status = 'active' ORDER BY nominal");

// Proses pembelian
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validasi CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: listrik.php");
        exit;
    }
    
    $no_meter = preg_replace('/[^0-9]/', '', $_POST['no_meter'] ?? '');
    $produk_id = intval($_POST['produk_id'] ?? 0);
    
    // Validasi input yang lebih ketat
    if (empty($no_meter) || strlen($no_meter) < 11 || strlen($no_meter) > 12) {
        setAlert('error', 'Nomor meter tidak valid! (11-12 digit)');
    } elseif ($produk_id == 0 || $produk_id > 100000) {
        setAlert('error', 'Pilih nominal token yang valid!');
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
                
                // Generate token dummy
                $token = rand(1000,9999).'-'.rand(1000,9999).'-'.rand(1000,9999).'-'.rand(1000,9999).'-'.rand(1000,9999);
                $keterangan = "Token: " . $token;
                
                $stmt = $conn->prepare("INSERT INTO transaksi (user_id, produk_id, no_invoice, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan) VALUES (?, ?, ?, 'listrik', ?, ?, ?, 0, ?, ?, ?, 'success', ?)");
                $stmt->bind_param("iissddddds", $user_id, $produk_id, $invoice, $no_meter, $produk['nominal'], $harga, $harga, $saldo_sebelum, $saldo_sesudah, $keterangan);
                
                if ($stmt->execute()) {
                    updateSaldo($user_id, $harga, 'kurang');
                    $_SESSION['saldo'] = getSaldo($user_id);
                    $_SESSION['token_result'] = ['invoice' => $invoice, 'token' => $token, 'nominal' => $produk['nominal'], 'harga' => $harga];
                    setAlert('success', 'Pembelian token listrik berhasil!');
                } else {
                    setAlert('error', 'Terjadi kesalahan. Silakan coba lagi.');
                }
            }
        }
    }
    header("Location: listrik.php");
    exit;
}

$alert = getAlert();
$tokenResult = isset($_SESSION['token_result']) ? $_SESSION['token_result'] : null;
unset($_SESSION['token_result']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Token Listrik - PPOB Express</title>
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

        /* Product Card Styles */
        .product-card {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .product-card:hover {
            border-color: #f59e0b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.1);
        }
        
        .product-card.selected {
            border-color: #f59e0b;
            background-color: #fffbeb;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
        }

        .input-field {
            transition: all 0.2s ease;
        }
        
        .input-field:focus {
            border-color: #2563eb;
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
            background-color: #f59e0b;
            color: white;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: #d97706;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .token-display {
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Code', monospace;
            letter-spacing: 0.1em;
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

        .pln-badge {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

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

        /* Smooth Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Animation */
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

        /* Gradient Background */
        .bg-gradient-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        <a href="listrik.php"  class="menu-item active"><i class="fas fa-bolt"></i><span>Token Listrik</span></a>
        <a href="transfer.php" class="menu-item"><i class="fas fa-money-bill-transfer"></i><span>Transfer Tunai</span></a>
        <a href="deposit.php"  class="menu-item"><i class="fas fa-plus-circle"></i><span>Deposit Saldo</span></a>
        <a href="riwayat.php"  class="menu-item"><i class="fas fa-history"></i><span>Riwayat Transaksi</span></a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
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
    <div class="p-4 md:p-6 max-w-4xl mx-auto space-y-5">

        <!-- Page Title -->
        <div class="animate-fade-in-up">
            <h2 class="text-xl font-bold text-gray-900">Token Listrik PLN</h2>
            <p class="text-sm text-gray-500 mt-0.5">Isi token listrik prabayar dengan mudah</p>
        </div>

        <?php if ($alert): ?>
        <div class="alert animate-fade-in-up p-4 rounded-lg flex items-center gap-3 <?= $alert['type'] == 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <i class="fas <?= $alert['type'] == 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> text-lg"></i>
            <span class="font-medium"><?= $alert['message'] ?></span>
        </div>
        <?php endif; ?>

        <?php if ($tokenResult): ?>
        <!-- Token Result Modal -->
        <div class="card animate-fade-in-up delay-100 p-6 mb-6 border border-amber-200">
            <div class="text-center mb-6">
                <div class="w-16 h-16 pln-badge rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-bolt text-2xl text-white"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-1">Token Listrik Berhasil Dibeli!</h3>
                <p class="text-gray-500 text-sm">Invoice: <?= $tokenResult['invoice'] ?></p>
            </div>
            
            <div class="space-y-4 mb-6">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-center">
                    <p class="text-xs text-amber-800 uppercase tracking-wider font-semibold mb-2">Token Listrik (20 Digit)</p>
                    <div class="token-display text-2xl md:text-3xl font-bold text-gray-900 bg-white p-4 rounded-lg">
                        <?= $tokenResult['token'] ?>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Token hanya akan muncul sekali, harap dicatat</p>
                </div>
                
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <p class="text-gray-500 mb-1">Nominal Token</p>
                        <p class="font-semibold text-gray-900"><?= rupiah($tokenResult['nominal']) ?></p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <p class="text-gray-500 mb-1">Total Pembayaran</p>
                        <p class="font-semibold text-gray-900"><?= rupiah($tokenResult['harga']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3">
                <button onclick="copyToken('<?= $tokenResult['token'] ?>')" class="btn-primary flex-1 py-3 px-4 rounded-lg font-semibold flex items-center justify-center gap-2">
                    <i class="fas fa-copy"></i>
                    Salin Token
                </button>
                <button onclick="printToken()" class="flex-1 py-3 px-4 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition flex items-center justify-center gap-2">
                    <i class="fas fa-print"></i>
                    Cetak
                </button>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

            <!-- Input Nomor Meter -->
            <div class="card animate-fade-in-up delay-200 p-6">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-14 h-14 pln-badge rounded-full flex items-center justify-center">
                        <i class="fas fa-bolt text-2xl text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">PLN Prabayar</h3>
                        <p class="text-sm text-gray-500">Masukkan nomor meter pelanggan PLN Prabayar</p>
                    </div>
                </div>
                
                <div class="space-y-2">
                    <label class="block font-medium text-gray-900">Nomor Meter / ID Pelanggan</label>
                    <div class="relative">
                        <div class="absolute left-4 top-1/2 -translate-y-1/2">
                            <i class="fas fa-hashtag text-gray-400"></i>
                        </div>
                        <input type="text" 
                               name="no_meter" 
                               id="no_meter" 
                               class="input-field w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none"
                               placeholder="Contoh: 12345678901" 
                               required 
                               maxlength="13"
                               pattern="[0-9]*"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                    <p class="text-xs text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        Masukkan 11-13 digit nomor meter yang tertera pada token listrik sebelumnya
                    </p>
                </div>
            </div>
            
            <input type="hidden" name="produk_id" id="produk_id" value="">
            
            <!-- Pilih Nominal Token -->
            <div class="card animate-fade-in-up delay-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">Pilih Nominal Token Listrik</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <?php while ($p = $produkListrik->fetch_assoc()): 
                        $harga = $p['harga_jual'];
                        $nominal = $p['nominal'];
                        $admin = 2500;
                        $hargaTanpaAdmin = $harga - $admin;
                    ?>
                    <div class="product-card card p-4"
                         onclick="selectProduct(<?= $p['id'] ?>, 'Token <?= rupiah($nominal) ?>', <?= $harga ?>)">
                        <div class="space-y-3">
                            <div class="flex items-start justify-between">
                                <div class="w-3/4">
                                    <p class="font-bold text-gray-900"><?= rupiah($nominal) ?></p>
                                    <p class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                        <i class="fas fa-bolt text-amber-500"></i>
                                        <span>Token Listrik</span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center">
                                        <div class="w-2.5 h-2.5 rounded-full bg-amber-500 hidden"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="pt-3 border-t border-gray-100">
                                <p class="text-lg font-bold text-amber-600"><?= rupiah($harga) ?></p>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Harga: <?= rupiah($hargaTanpaAdmin) ?></span>
                                    <span>Admin: <?= rupiah($admin) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <!-- Summary Sticky -->
            <div id="summarySection" class="sticky-summary hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Token yang dipilih</p>
                            <p class="font-semibold text-gray-900" id="selectedProduct">-</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-500 mb-1">Total Pembayaran</p>
                            <p class="text-2xl font-bold text-amber-600" id="totalBayar">Rp 0</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="resetSelection()" class="flex-1 py-3 px-4 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i> Batal
                        </button>
                        <button type="submit" class="btn-primary flex-1 py-3 px-4 rounded-lg font-semibold flex items-center justify-center gap-2">
                            <i class="fas fa-bolt"></i>
                            Beli Token
                        </button>
                    </div>
                </div>
            </div>
        </form>

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
        mainContent.classList.remove('sidebar-hidden');
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
//  PRODUCT SELECTION LOGIC
// ═══════════════════════════════════════════════════════

function selectProduct(id, nama, harga) {
    // Remove all selections
    document.querySelectorAll('.product-card').forEach(el => {
        el.classList.remove('selected');
        el.querySelector('.w-2\.5.h-2\.5').classList.add('hidden');
    });
    
    // Add selection to clicked card
    const card = event.currentTarget;
    card.classList.add('selected');
    card.querySelector('.w-2\.5.h-2\.5').classList.remove('hidden');
    
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
        el.querySelector('.w-2\.5.h-2\.5').classList.add('hidden');
    });
    
    document.getElementById('produk_id').value = '';
    document.getElementById('selectedProduct').textContent = '-';
    document.getElementById('totalBayar').textContent = 'Rp 0';
    document.getElementById('summarySection').classList.add('hidden');
}

function formatRupiah(num) {
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function copyToken(token) {
    const cleanToken = token.replace(/-/g, '');
    navigator.clipboard.writeText(cleanToken);
    
    // Show success message
    const button = event.target.closest('button');
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check mr-2"></i> Token Disalin!';
    button.style.backgroundColor = '#10b981';
    
    setTimeout(() => {
        button.innerHTML = originalHTML;
        button.style.backgroundColor = '';
    }, 2000);
}

function printToken() {
    const printContent = `
        <div style="padding: 20px; font-family: Arial, sans-serif;">
            <h2 style="text-align: center; color: #f59e0b;">TOKEN LISTRIK PLN</h2>
            <p style="text-align: center; color: #666;">Invoice: <?= $tokenResult['invoice'] ?? '' ?></p>
            <div style="background: #fff; border: 2px dashed #f59e0b; padding: 20px; margin: 20px 0; text-align: center;">
                <p style="color: #999; margin-bottom: 10px;">Token Listrik (20 Digit)</p>
                <p style="font-size: 24px; font-weight: bold; letter-spacing: 2px;"><?= $tokenResult['token'] ?? '' ?></p>
            </div>
            <div style="display: flex; justify-content: space-between; margin: 20px 0;">
                <div>
                    <p style="color: #666;">Nominal:</p>
                    <p style="font-weight: bold;"><?= rupiah($tokenResult['nominal'] ?? 0) ?></p>
                </div>
                <div>
                    <p style="color: #666;">Total Bayar:</p>
                    <p style="font-weight: bold;"><?= rupiah($tokenResult['harga'] ?? 0) ?></p>
                </div>
            </div>
            <p style="text-align: center; color: #999; font-size: 12px; margin-top: 30px;">
                Dicetak dari PPOB Express pada <?= date('d/m/Y H:i:s') ?>
            </p>
        </div>
    `;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Token Listrik</title></head><body>');
    printWindow.document.write(printContent);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}
</script>

</body>
</html>
<?php 
$conn->close();
?>