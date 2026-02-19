<?php
require_once 'config.php';
cekLogin();

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$_SESSION['saldo'] = getSaldo($user_id);

// Metode deposit
$metodeDeposit = [
    'bank_transfer' => [
        'name' => 'Transfer Bank',
        'icon' => 'fas fa-university',
        'banks' => [
            'BCA' => [
                'nama' => 'Bank Central Asia',
                'nomor' => '1234567890',
                'atas_nama' => 'PT PPOB Express'
            ],
            'BNI' => [
                'nama' => 'Bank Negara Indonesia',
                'nomor' => '0987654321',
                'atas_nama' => 'PT PPOB Express'
            ],
            'BRI' => [
                'nama' => 'Bank Rakyat Indonesia',
                'nomor' => '1122334455',
                'atas_nama' => 'PT PPOB Express'
            ],
            'Mandiri' => [
                'nama' => 'Bank Mandiri',
                'nomor' => '5566778899',
                'atas_nama' => 'PT PPOB Express'
            ]
        ]
    ],
    'e_wallet' => [
        'name' => 'E-Wallet',
        'icon' => 'fas fa-wallet',
        'wallets' => [
            'DANA' => [
                'nama' => 'DANA',
                'nomor' => '081234567890',
                'atas_nama' => 'PPOB Express'
            ],
            'OVO' => [
                'nama' => 'OVO',
                'nomor' => '081234567891',
                'atas_nama' => 'PPOB Express'
            ],
            'GoPay' => [
                'nama' => 'GoPay',
                'nomor' => '081234567892',
                'atas_nama' => 'PPOB Express'
            ],
            'ShopeePay' => [
                'nama' => 'ShopeePay',
                'nomor' => '081234567893',
                'atas_nama' => 'PPOB Express'
            ]
        ]
    ]
];

// Minimal deposit
$minDeposit = floatval(getPengaturan('minimal_deposit') ?? 10000);

// Proses deposit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validasi CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: deposit.php");
        exit;
    }
    
    $nominal = floatval(preg_replace('/[^0-9]/', '', $_POST['nominal'] ?? 0));
    $metode = $_POST['metode'] ?? '';
    $bank = $_POST['bank'] ?? '';
    
    // Validasi input
    if ($nominal <= 0 || $nominal > 100000000) {
        setAlert('error', 'Nominal deposit tidak valid!');
    } elseif ($nominal < $minDeposit) {
        setAlert('error', 'Minimal deposit ' . rupiah($minDeposit));
    } elseif (!in_array($metode, ['bank_transfer', 'e_wallet'])) {
        setAlert('error', 'Pilih metode pembayaran!');
    } elseif (empty($bank)) {
        setAlert('error', 'Pilih bank/e-wallet tujuan!');
    } elseif (strlen($bank) > 50) {
        setAlert('error', 'Data bank tidak valid!');
    } else {
        // Generate nomor deposit
        $no_deposit = 'DEP' . date('YmdHis') . rand(100, 999);
        
        // Tentukan nama bank/ewallet
        $metode_bayar = $metode;
        if ($metode == 'bank_transfer') {
            $metode_bayar .= '|' . $bank;
        } elseif ($metode == 'e_wallet') {
            $metode_bayar .= '|' . $bank;
        }
        
        $stmt = $conn->prepare("INSERT INTO deposit (user_id, no_deposit, nominal, metode_bayar, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->bind_param("isds", $user_id, $no_deposit, $nominal, $metode_bayar);
        
        if ($stmt->execute()) {
            setAlert('success', 'Deposit berhasil diajukan! Nomor Deposit: ' . $no_deposit . '. Silakan lakukan pembayaran.');
        } else {
            setAlert('error', 'Terjadi kesalahan. Silakan coba lagi.');
        }
    }
    header("Location: deposit.php");
    exit;
}

// Ambil riwayat deposit user
$stmt = $conn->prepare("SELECT * FROM deposit WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$riwayatDeposit = $stmt->get_result();

// Ambil semua deposit untuk admin
if ($role == 'admin') {
    // Gunakan prepared statement untuk keamanan
    $allDepositStmt = $conn->prepare("SELECT d.*, u.nama_lengkap FROM deposit d JOIN users u ON d.user_id = u.id ORDER BY d.created_at DESC");
    $allDepositStmt->execute();
    $allDeposit = $allDepositStmt->get_result();
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Saldo - PPOB Express</title>
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

        /* State: sidebar tersembunyi (dipakai di mobile DAN desktop saat di-toggle) */
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
        .status-rejected { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; }

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

        /* Custom CSS untuk deposit */
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #3b82f6;
            --light-blue: #eff6ff;
            --green-500: #10b981;
            --green-600: #059669;
        }
        
        .metode-card {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .metode-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .metode-card.selected {
            border-color: var(--green-500);
            background-color: #f0fdf4;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }
        
        .bank-card {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .bank-card:hover {
            border-color: var(--green-500);
        }
        
        .bank-card.selected {
            border-color: var(--green-500);
            background-color: #f0fdf4;
        }
        
        .input-field {
            transition: all 0.2s ease;
        }
        
        .input-field:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-primary {
            background-color: var(--green-500);
            color: white;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--green-600);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
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
        
        .status-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-weight: 500;
        }
        
        .sticky-section {
            position: sticky;
            bottom: 20px;
            background: white;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            z-index: 10;
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
        <a href="pulsa.php"    class="menu-item"><i class="fas fa-mobile-alt"></i><span>Isi Pulsa</span></a>
        <a href="kuota.php"    class="menu-item"><i class="fas fa-wifi"></i><span>Paket Data</span></a>
        <a href="listrik.php"  class="menu-item"><i class="fas fa-bolt"></i><span>Token Listrik</span></a>
        <a href="transfer.php" class="menu-item"><i class="fas fa-money-bill-transfer"></i><span>Transfer Tunai</span></a>
        <a href="deposit.php"  class="menu-item active"><i class="fas fa-plus-circle"></i><span>Deposit Saldo</span></a>
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

        <!-- Page Title -->
        <div>
            <h2 class="text-xl font-bold text-gray-900">Deposit Saldo</h2>
            <p class="text-sm text-gray-500 mt-0.5">Tambah saldo untuk transaksi Anda</p>
        </div>

        <?php if ($alert): ?>
        <div class="alert p-4 rounded-lg flex items-center gap-3 <?= $alert['type'] == 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <i class="fas <?= $alert['type'] == 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> text-lg"></i>
            <span class="font-medium"><?= $alert['message'] ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($role == 'admin'): ?>
        <!-- Admin Panel - Pending Deposits -->
        <div class="card p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Deposit Menunggu Verifikasi</h3>
                <span class="text-sm text-gray-500">Admin Panel</span>
            </div>
            
            <?php if ($allDeposit && $allDeposit->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">No Deposit</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">User</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Nominal</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Metode</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Status</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Tanggal</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php while ($dep = $allDeposit->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-mono"><?= $dep['no_deposit'] ?></td>
                            <td class="px-4 py-3 text-sm"><?= $dep['nama_lengkap'] ?></td>
                            <td class="px-4 py-3 text-sm font-semibold"><?= rupiah($dep['nominal']) ?></td>
                            <td class="px-4 py-3 text-sm"><?= $dep['metode_bayar'] ?></td>
                            <td class="px-4 py-3">
                                <span class="status-badge status-<?= $dep['status'] ?>">
                                    <?= ucfirst($dep['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($dep['created_at'])) ?></td>
                            <td class="px-4 py-3">
                                <?php if ($dep['status'] == 'pending'): ?>
                                <div class="flex gap-2">
                                    <form method="POST" action="proses_deposit.php" style="display: inline;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="deposit_id" value="<?= $dep['id'] ?>">
                                        <button type="submit" class="text-xs px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600">
                                            Approve
                                        </button>
                                    </form>
                                    <form method="POST" action="proses_deposit.php" style="display: inline;">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="deposit_id" value="<?= $dep['id'] ?>">
                                        <button type="submit" class="text-xs px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                                <?php else: ?>
                                <span class="text-xs text-gray-500"><?= $dep['approved_by'] ?> - <?= date('d/m/Y H:i', strtotime($dep['approved_at'])) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-gray-500 text-center py-4">Tidak ada deposit menunggu verifikasi</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Form Deposit -->
        <form method="POST" action="" id="formDeposit" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            
            <!-- Pilih Metode Deposit -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">Pilih Metode Deposit</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($metodeDeposit as $key => $metode): ?>
                    <div class="metode-card card p-5"
                            onclick="selectMetode('<?= $key ?>', '<?= $metode['name'] ?>')">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="<?= $metode['icon'] ?> text-xl text-green-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900"><?= $metode['name'] ?></p>
                                <p class="text-sm text-gray-500 mt-1">Deposit via <?= $metode['name'] ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <input type="hidden" name="metode" id="metode" value="">
                <input type="hidden" name="metode_nama" id="metode_nama" value="">
            </div>
            
            <!-- Pilih Bank/E-Wallet -->
            <div id="bankSelection" class="card p-6 hidden">
                <h3 class="text-lg font-semibold text-gray-900 mb-6" id="bankTitle">Pilih Bank Tujuan</h3>
                
                <div id="bankList" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <!-- Bank list akan diisi oleh JavaScript -->
                </div>
                
                <input type="hidden" name="bank" id="bank" value="">
            </div>
            
            <!-- Informasi Rekening -->
            <div id="accountInfo" class="card p-6 hidden">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pembayaran</h3>
                
                <div id="accountDetails" class="bg-gray-50 rounded-lg p-4 mb-4">
                    <!-- Informasi rekening akan diisi oleh JavaScript -->
                </div>
                
                <div class="space-y-2">
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        Silakan transfer sesuai nominal deposit ke rekening di atas
                    </p>
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-clock mr-1"></i>
                        Deposit akan diproses dalam 1x24 jam setelah pembayaran
                    </p>
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-upload mr-1"></i>
                        Upload bukti transfer di halaman ini setelah melakukan pembayaran
                    </p>
                </div>
            </div>
            
            <!-- Input Nominal -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Masukkan Nominal Deposit</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block font-medium text-gray-900 mb-2">Nominal Deposit</label>
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
                        <div class="flex justify-between mt-2">
                            <p class="text-xs text-gray-500">
                                <i class="fas fa-info-circle mr-1"></i>
                                Minimal: <?= rupiah($minDeposit) ?>
                            </p>
                            <div class="flex gap-2">
                                <button type="button" onclick="setNominal(50000)" class="text-xs px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">50.000</button>
                                <button type="button" onclick="setNominal(100000)" class="text-xs px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">100.000</button>
                                <button type="button" onclick="setNominal(200000)" class="text-xs px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">200.000</button>
                                <button type="button" onclick="setNominal(500000)" class="text-xs px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">500.000</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Summary -->
            <div id="summarySection" class="sticky-section hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Metode</p>
                            <p class="font-semibold text-gray-900" id="summaryMetode">-</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-500 mb-1">Total Deposit</p>
                            <p class="text-2xl font-bold text-green-600" id="summaryTotal">Rp 0</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="resetForm()" class="flex-1 py-3 px-4 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i> Batal
                        </button>
                        <button type="submit" class="btn-primary flex-1 py-3 px-4 rounded-lg font-semibold flex items-center justify-center gap-2">
                            <i class="fas fa-credit-card"></i>
                            Ajukan Deposit
                        </button>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Riwayat Deposit -->
        <div class="card p-6 mt-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Riwayat Deposit</h3>
                <span class="text-sm text-gray-500">10 transaksi terakhir</span>
            </div>
            
            <?php if ($riwayatDeposit && $riwayatDeposit->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">No Deposit</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Nominal</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Metode</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Status</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php while ($dep = $riwayatDeposit->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-mono"><?= $dep['no_deposit'] ?></td>
                            <td class="px-4 py-3 text-sm font-semibold"><?= rupiah($dep['nominal']) ?></td>
                            <td class="px-4 py-3 text-sm"><?= $dep['metode_bayar'] ?></td>
                            <td class="px-4 py-3">
                                <span class="status-badge status-<?= $dep['status'] ?>">
                                    <?= ucfirst($dep['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($dep['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-8">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-history text-gray-400 text-xl"></i>
                </div>
                <p class="text-gray-500">Belum ada riwayat deposit</p>
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
//  DEPOSIT LOGIC
// ═══════════════════════════════════════════════════════

// Data metode deposit dari PHP
const metodeDeposit = <?= json_encode($metodeDeposit) ?>;
const minDeposit = <?= $minDeposit ?>;

function selectMetode(kode, nama) {
    // Remove all selections
    document.querySelectorAll('.metode-card').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Add selection to clicked card
    const card = event.currentTarget;
    card.classList.add('selected');
    
    // Update form values
    document.getElementById('metode').value = kode;
    document.getElementById('metode_nama').value = nama;
    document.getElementById('summaryMetode').textContent = nama;
    
    // Show bank selection
    const bankSelection = document.getElementById('bankSelection');
    const bankList = document.getElementById('bankList');
    const bankTitle = document.getElementById('bankTitle');
    
    bankSelection.classList.remove('hidden');
    bankList.innerHTML = '';
    
    // Update title
    bankTitle.textContent = kode === 'bank_transfer' ? 'Pilih Bank Tujuan' : 'Pilih E-Wallet';
    
    // Populate banks/wallets
    const items = kode === 'bank_transfer' ? metodeDeposit[kode].banks : metodeDeposit[kode].wallets;
    
    for (const [key, value] of Object.entries(items)) {
        const isEwallet = kode === 'e_wallet';
        const bankCard = document.createElement('div');
        bankCard.className = 'bank-card card p-4 text-center';
        bankCard.onclick = () => selectBank(key, value.nama);
        bankCard.innerHTML = `
            <div class="w-10 h-10 ${isEwallet ? 'bg-pink-100' : 'bg-blue-100'} rounded-full mx-auto mb-2 flex items-center justify-center">
                <i class="fas ${isEwallet ? 'fa-mobile-screen' : 'fa-landmark'} ${isEwallet ? 'text-pink-600' : 'text-blue-600'}"></i>
            </div>
            <p class="text-sm font-medium text-gray-900">${key}</p>
            <p class="text-xs text-gray-500 truncate">${value.nama}</p>
        `;
        bankList.appendChild(bankCard);
    }
    
    // Show summary
    document.getElementById('summarySection').classList.remove('hidden');
}

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
    
    // Show account info
    const metode = document.getElementById('metode').value;
    const items = metode === 'bank_transfer' ? metodeDeposit[metode].banks : metodeDeposit[metode].wallets;
    const account = items[kode];
    
    const accountInfo = document.getElementById('accountInfo');
    const accountDetails = document.getElementById('accountDetails');
    
    accountInfo.classList.remove('hidden');
    accountDetails.innerHTML = `
        <div class="space-y-3">
            <div class="flex justify-between items-center">
                <span class="text-gray-600">Bank/E-Wallet:</span>
                <span class="font-semibold text-gray-900">${nama}</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-gray-600">Nomor Rekening/Akun:</span>
                <span class="font-mono font-bold text-gray-900">${account.nomor}</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-gray-600">Atas Nama:</span>
                <span class="font-semibold text-gray-900">${account.atas_nama}</span>
            </div>
        </div>
    `;
    
    // Update summary
    document.getElementById('summaryMetode').textContent = `${document.getElementById('metode_nama').value} - ${nama}`;
}

function setNominal(nominal) {
    const input = document.getElementById('nominal');
    input.value = nominal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    calculateTotal();
}

function formatNominal(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    let num = parseInt(value) || 0;
    
    // Validate min
    if (num > 0 && num < minDeposit) {
        input.classList.add('border-red-500');
        input.classList.remove('border-gray-300');
    } else {
        input.classList.remove('border-red-500');
        input.classList.add('border-gray-300');
    }
    
    input.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function calculateTotal() {
    let nominalValue = document.getElementById('nominal').value;
    let nominal = parseInt(nominalValue.replace(/\./g, '')) || 0;
    
    // Update summary
    document.getElementById('summaryTotal').textContent = formatRupiah(nominal);
}

function formatRupiah(num) {
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function resetForm() {
    // Reset all selections
    document.querySelectorAll('.metode-card, .bank-card').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Clear form values
    document.getElementById('metode').value = '';
    document.getElementById('metode_nama').value = '';
    document.getElementById('bank').value = '';
    document.getElementById('nominal').value = '';
    
    // Hide sections
    document.getElementById('bankSelection').classList.add('hidden');
    document.getElementById('accountInfo').classList.add('hidden');
    document.getElementById('summarySection').classList.add('hidden');
    
    // Reset summary
    document.getElementById('summaryMetode').textContent = '-';
    document.getElementById('summaryTotal').textContent = 'Rp 0';
}

// Form validation
document.getElementById('formDeposit').addEventListener('submit', function(e) {
    const metode = document.getElementById('metode').value;
    const bank = document.getElementById('bank').value;
    const nominalValue = document.getElementById('nominal').value;
    const nominal = parseInt(nominalValue.replace(/\./g, '')) || 0;
    
    let isValid = true;
    let errorMessage = '';
    
    if (!metode) {
        errorMessage = 'Silakan pilih metode deposit';
        isValid = false;
    } else if (!bank) {
        errorMessage = 'Silakan pilih bank/e-wallet tujuan';
        isValid = false;
    } else if (nominal < minDeposit) {
        errorMessage = `Minimal deposit ${formatRupiah(minDeposit)}`;
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Error: ' + errorMessage);
    } else {
        // Show confirmation
        if (!confirm(`Ajukan deposit sebesar ${formatRupiah(nominal)}?`)) {
            e.preventDefault();
        }
    }
});

// Auto-hide summary when scrolling up
let lastScrollTop = 0;
const summary = document.getElementById('summarySection');
window.addEventListener('scroll', function() {
    if (summary && !summary.classList.contains('hidden')) {
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

// Initialize
calculateTotal();
</script>

</body>
</html>
<?php 
$conn->close();
?>