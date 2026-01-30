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
    $nominal = floatval(preg_replace('/[^0-9]/', '', $_POST['nominal'] ?? 0));
    $metode = $_POST['metode'] ?? '';
    $bank = $_POST['bank'] ?? '';
    
    if ($nominal < $minDeposit) {
        setAlert('error', 'Minimal deposit ' . rupiah($minDeposit));
    } elseif (!in_array($metode, ['bank_transfer', 'e_wallet'])) {
        setAlert('error', 'Pilih metode pembayaran!');
    } elseif ($metode == 'bank_transfer' && empty($bank)) {
        setAlert('error', 'Pilih bank tujuan!');
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
    $allDeposit = $conn->query("SELECT d.*, u.nama_lengkap FROM deposit d JOIN users u ON d.user_id = u.id ORDER BY d.created_at DESC");
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
        /* Custom CSS konsisten dengan tema */
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #3b82f6;
            --light-blue: #eff6ff;
            --green-500: #10b981;
            --green-600: #059669;
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
        
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        .status-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .sticky-section {
            position: sticky;
            bottom: 20px;
            background: white;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            z-index: 10;
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
                <a href="deposit.php" class="menu-item active flex items-center gap-3 px-4 py-3 text-gray-700">
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
                            <h2 class="text-lg font-semibold text-gray-800">Deposit Saldo</h2>
                            <p class="text-sm text-gray-500">Tambah saldo untuk transaksi Anda</p>
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
                
                <?php if ($role == 'admin'): ?>
                <!-- Admin Panel - Pending Deposits -->
                <div class="card p-6 mb-6">
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
            </div>
        </main>
    </div>
    
    <script>
        // Data metode deposit dari PHP
        const metodeDeposit = <?= json_encode($metodeDeposit) ?>;
        const minDeposit = <?= $minDeposit ?>;
        
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('closed');
            document.getElementById('overlay').classList.toggle('hidden');
        }
        
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