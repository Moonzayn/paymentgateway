<?php
require_once 'config.php';
cekLogin();

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$_SESSION['saldo'] = getSaldo($user_id);

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
    $bank = $_POST['bank'] ?? '';
    $no_rekening = preg_replace('/[^0-9]/', '', $_POST['no_rekening'] ?? '');
    $nama_penerima = htmlspecialchars($_POST['nama_penerima'] ?? '');
    $nominal = floatval(preg_replace('/[^0-9]/', '', $_POST['nominal'] ?? 0));
    
    if (empty($bank) || !isset($daftarBank[$bank])) {
        setAlert('error', 'Pilih bank tujuan!');
    } elseif (empty($no_rekening) || strlen($no_rekening) < 8) {
        setAlert('error', 'Nomor rekening tidak valid!');
    } elseif (empty($nama_penerima)) {
        setAlert('error', 'Masukkan nama penerima!');
    } elseif ($nominal < $minTransfer) {
        setAlert('error', 'Minimal transfer ' . rupiah($minTransfer));
    } elseif ($nominal > $maxTransfer) {
        setAlert('error', 'Maksimal transfer ' . rupiah($maxTransfer));
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
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar { transition: transform 0.3s ease; }
        .sidebar.closed { transform: translateX(-100%); }
        @media (min-width: 768px) { .sidebar.closed { transform: translateX(0); } }
        .bank-option.selected { border-color: #8b5cf6; background-color: #f5f3ff; }
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
                <a href="transfer.php" class="menu-item active flex items-center gap-3 px-4 py-3 rounded-lg bg-white/20 transition">
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
                        <h2 class="text-lg font-semibold text-gray-800">Transfer Tunai</h2>
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
                
                <form method="POST" action="" id="formTransfer">
                    <!-- Pilih Bank -->
                    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Pilih Bank / E-Wallet Tujuan</h3>
                        <input type="hidden" name="bank" id="bank" value="">
                        
                        <div class="grid grid-cols-3 md:grid-cols-5 gap-3">
                            <?php foreach ($daftarBank as $kode => $nama): ?>
                            <div class="bank-option border-2 border-gray-200 rounded-lg p-3 cursor-pointer hover:border-purple-400 transition text-center"
                                 onclick="selectBank('<?= $kode ?>', '<?= $nama ?>')">
                                <div class="w-10 h-10 bg-gray-100 rounded-full mx-auto mb-2 flex items-center justify-center">
                                    <span class="font-bold text-xs text-gray-700"><?= substr($kode, 0, 3) ?></span>
                                </div>
                                <p class="text-xs text-gray-600 truncate"><?= $kode ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Detail Transfer -->
                    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Detail Transfer</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Nomor Rekening</label>
                                <div class="relative">
                                    <i class="fas fa-credit-card absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                    <input type="text" name="no_rekening" id="no_rekening" 
                                           class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                           placeholder="Masukkan nomor rekening" required>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Nama Penerima</label>
                                <div class="relative">
                                    <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                    <input type="text" name="nama_penerima" id="nama_penerima" 
                                           class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                           placeholder="Nama sesuai rekening" required>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Nominal Transfer</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-medium">Rp</span>
                                    <input type="text" name="nominal" id="nominal" 
                                           class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                           placeholder="0" required oninput="formatNominal(this); calculateTotal();">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Min: <?= rupiah($minTransfer) ?> | Max: <?= rupiah($maxTransfer) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Summary -->
                    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 sticky bottom-4">
                        <div class="space-y-2 mb-4">
                            <div class="flex justify-between text-gray-600">
                                <span>Bank Tujuan:</span>
                                <span id="bankTujuan" class="font-medium">-</span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Nominal Transfer:</span>
                                <span id="nominalDisplay" class="font-medium">Rp 0</span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Biaya Admin:</span>
                                <span class="font-medium"><?= rupiah($biayaAdmin) ?></span>
                            </div>
                            <hr>
                            <div class="flex justify-between text-lg font-bold">
                                <span>Total Bayar:</span>
                                <span id="totalBayar" class="text-purple-600">Rp 0</span>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 rounded-lg font-semibold hover:from-purple-700 hover:to-indigo-700 transition flex items-center justify-center gap-2">
                            <i class="fas fa-paper-plane"></i>
                            Transfer Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        const biayaAdmin = <?= $biayaAdmin ?>;
        
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('closed');
            document.getElementById('overlay').classList.toggle('hidden');
        }
        
        function selectBank(kode, nama) {
            document.querySelectorAll('.bank-option').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            document.getElementById('bank').value = kode;
            document.getElementById('bankTujuan').textContent = nama;
        }
        
        function formatNominal(input) {
            let value = input.value.replace(/[^0-9]/g, '');
            input.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        function calculateTotal() {
            let nominal = parseInt(document.getElementById('nominal').value.replace(/\./g, '')) || 0;
            let total = nominal + biayaAdmin;
            
            document.getElementById('nominalDisplay').textContent = formatRupiah(nominal);
            document.getElementById('totalBayar').textContent = formatRupiah(total);
        }
        
        function formatRupiah(num) {
            return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
