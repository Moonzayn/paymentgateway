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
    $no_meter = preg_replace('/[^0-9]/', '', $_POST['no_meter'] ?? '');
    $produk_id = intval($_POST['produk_id'] ?? 0);
    
    if (empty($no_meter) || strlen($no_meter) < 11) {
        setAlert('error', 'Nomor meter tidak valid! (minimal 11 digit)');
    } elseif ($produk_id == 0) {
        setAlert('error', 'Pilih nominal token!');
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
        /* Custom CSS konsisten dengan tema */
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #3b82f6;
            --light-blue: #eff6ff;
            --amber-500: #f59e0b;
            --amber-600: #d97706;
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
            border-color: var(--amber-500);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.1);
        }
        
        .product-card.selected {
            border-color: var(--amber-500);
            background-color: #fffbeb;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
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
            background-color: var(--amber-500);
            color: white;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--amber-600);
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
                <a href="listrik.php" class="menu-item active flex items-center gap-3 px-4 py-3 text-gray-700">
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
                            <h2 class="text-lg font-semibold text-gray-800">Token Listrik PLN</h2>
                            <p class="text-sm text-gray-500">Isi token listrik prabayar dengan mudah</p>
                        </div>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 px-4 py-2 rounded-lg">
                        <p class="text-xs text-gray-600">Saldo Tersedia</p>
                        <p class="font-bold text-blue-700"><?= rupiah($_SESSION['saldo']) ?></p>
                    </div>
                </div>
            </header>
            
            <div class="p-4 md:p-6 max-w-4xl mx-auto">
                <?php if ($alert): ?>
                <div class="alert mb-4 p-4 rounded-lg flex items-center gap-3 <?= $alert['type'] == 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
                    <i class="fas <?= $alert['type'] == 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> text-lg"></i>
                    <span class="font-medium"><?= $alert['message'] ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($tokenResult): ?>
                <!-- Token Result Modal -->
                <div class="card p-6 mb-6 border border-amber-200">
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
                    <!-- Input Nomor Meter -->
                    <div class="card p-6">
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
                    <div class="card p-6">
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
                el.querySelector('.w-2\\.5.h-2\\.5').classList.add('hidden');
            });
            
            // Add selection to clicked card
            const card = event.currentTarget;
            card.classList.add('selected');
            card.querySelector('.w-2\\.5.h-2\\.5').classList.remove('hidden');
            
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
                el.querySelector('.w-2\\.5.h-2\\.5').classList.add('hidden');
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
?>