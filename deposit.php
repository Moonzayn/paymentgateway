<?php
// deposit.php
require_once 'config.php';
cekLogin();

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$_SESSION['saldo'] = getSaldo($user_id);

// ═══ Layout Variables ═══
$pageTitle   = 'Deposit Saldo';
$pageIcon    = 'fas fa-plus-circle';
$pageDesc    = 'Top up saldo untuk transaksi';
$currentPage = 'deposit';
$additionalHeadScripts = ''; // Tidak perlu script tambahan

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
    $allDepositStmt = $conn->prepare("SELECT d.*, u.nama_lengkap FROM deposit d JOIN users u ON d.user_id = u.id ORDER BY d.created_at DESC");
    $allDepositStmt->execute();
    $allDeposit = $allDepositStmt->get_result();
}

$alert = getAlert();

// Include layout
include 'layout.php';
?>

<!-- ═══════════════════════════════════════════
     DEPOSIT - Custom Styles
═══════════════════════════════════════════ -->
<style>
:root {
    --primary-green: #10b981;
    --secondary-green: #34d399;
    --light-green: #d1fae5;
}

/* Metode Card */
.metode-card {
    transition: all 0.2s ease;
    border: 2px solid transparent;
}

.metode-card:hover {
    border-color: var(--primary-green);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
}

.metode-card.selected {
    border-color: var(--primary-green) !important;
    background-color: #f0fdf4;
}

/* Bank Card */
.bank-card {
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.2s ease;
}

.bank-card:hover {
    border-color: var(--primary-green);
    transform: translateY(-2px);
}

.bank-card.selected {
    border-color: var(--primary-green);
    background-color: #f0fdf4;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-pending {
    background-color: #fef3c7;
    color: #92400e;
}

.status-success {
    background-color: #d1fae5;
    color: #065f46;
}

.status-rejected {
    background-color: #fee2e2;
    color: #991b1b;
}

/* Input Field */
.input-field {
    transition: all 0.2s ease;
}

.input-field:focus {
    border-color: var(--primary-green);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

/* Button */
.btn-primary {
    background-color: var(--primary-green);
    color: white;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: #059669;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

/* Animation */
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

.alert {
    animation: slideIn 0.3s ease;
}

/* Sticky Section */
.sticky-section {
    position: sticky;
    bottom: 20px;
    z-index: 20;
}

/* Card styling */
.card {
    background: white;
    border-radius: 0.75rem;
    border: 1px solid #e8ecf0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}

/* Animation delays */
.delay-100 { animation-delay: 0.1s; }
.delay-200 { animation-delay: 0.2s; }
.delay-300 { animation-delay: 0.3s; }
</style>

<!-- ═══════════════════════════════════════════
     DEPOSIT - Content
═══════════════════════════════════════════ -->


<!-- Alert Message -->
<?php if ($alert): ?>
<div class="alert mb-6 p-4 rounded-lg flex items-center gap-3 <?= $alert['type'] == 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>" style="animation: slideIn 0.3s ease;">
    <i class="fas <?= $alert['type'] == 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> text-lg"></i>
    <span class="font-medium"><?= $alert['message'] ?></span>
</div>
<?php endif; ?>

<?php if ($role == 'admin'): ?>
<!-- Admin Panel - Pending Deposits -->
<div class="card p-6 mb-6 delay-100" style="animation: slideIn 0.3s ease;">
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
                    <td class="px-4 py-3 text-sm"><?= htmlspecialchars($dep['nama_lengkap']) ?></td>
                    <td class="px-4 py-3 text-sm font-semibold"><?= rupiah($dep['nominal']) ?></td>
                    <td class="px-4 py-3 text-sm"><?= htmlspecialchars($dep['metode_bayar']) ?></td>
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
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="deposit_id" value="<?= $dep['id'] ?>">
                                <button type="submit" class="text-xs px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600 transition">
                                    Approve
                                </button>
                            </form>
                            <form method="POST" action="proses_deposit.php" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="deposit_id" value="<?= $dep['id'] ?>">
                                <button type="submit" class="text-xs px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition">
                                    Reject
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <span class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($dep['updated_at'] ?? $dep['created_at'])) ?></span>
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
    <div class="card p-6 delay-100" style="animation: slideIn 0.3s ease;">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Pilih Metode Deposit</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($metodeDeposit as $key => $metode): ?>
            <div class="metode-card card p-5 cursor-pointer border-2 border-transparent hover:border-green-500 transition-all"
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
    <div id="bankSelection" class="card p-6 hidden delay-200" style="animation: slideIn 0.3s ease;">
        <h3 class="text-lg font-semibold text-gray-900 mb-6" id="bankTitle">Pilih Bank Tujuan</h3>
        
        <div id="bankList" class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <!-- Bank list will be filled by JavaScript -->
        </div>
        
        <input type="hidden" name="bank" id="bank" value="">
    </div>
    
    <!-- Informasi Rekening -->
    <div id="accountInfo" class="card p-6 hidden delay-200" style="animation: slideIn 0.3s ease;">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pembayaran</h3>
        
        <div id="accountDetails" class="bg-gray-50 rounded-lg p-4 mb-4">
            <!-- Account details will be filled by JavaScript -->
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
        </div>
    </div>
    
    <!-- Input Nominal -->
    <div class="card p-6 delay-200" style="animation: slideIn 0.3s ease;">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Masukkan Nominal Deposit</h3>
        
        <div class="space-y-4">
            <div>
                <label class="block font-medium text-gray-900 mb-2">Nominal Deposit</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-medium">Rp</span>
                    <input type="text" 
                           name="nominal" 
                           id="nominal" 
                           class="input-field w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
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
                        <button type="button" onclick="setNominal(50000)" class="quick-amount text-xs px-3 py-1 border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition">50.000</button>
                        <button type="button" onclick="setNominal(100000)" class="quick-amount text-xs px-3 py-1 border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition">100.000</button>
                        <button type="button" onclick="setNominal(200000)" class="quick-amount text-xs px-3 py-1 border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition">200.000</button>
                        <button type="button" onclick="setNominal(500000)" class="quick-amount text-xs px-3 py-1 border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition">500.000</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Summary -->
    <div id="summarySection" class="sticky bottom-4 hidden delay-300" style="animation: slideIn 0.3s ease;">
        <div class="card p-6">
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
                <button type="submit" class="btn-primary flex-1 py-3 px-4 bg-green-500 hover:bg-green-600 text-white rounded-lg font-semibold flex items-center justify-center gap-2 transition">
                    <i class="fas fa-credit-card"></i>
                    Ajukan Deposit
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Riwayat Deposit -->
<div class="card p-6 mt-6 delay-300" style="animation: slideIn 0.3s ease;">
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
                    <td class="px-4 py-3 text-sm font-mono"><?= htmlspecialchars($dep['no_deposit']) ?></td>
                    <td class="px-4 py-3 text-sm font-semibold"><?= rupiah($dep['nominal']) ?></td>
                    <td class="px-4 py-3 text-sm"><?= htmlspecialchars($dep['metode_bayar']) ?></td>
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
        <p class="text-xs text-gray-400 mt-1">Mulai deposit pertama Anda</p>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════
     DEPOSIT - JavaScript
═══════════════════════════════════════════ -->
<script>
// Deposit page JavaScript
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
        bankCard.onclick = function() { selectBank(key, value.nama); };
        bankCard.innerHTML = `
            <div class="w-10 h-10 ${isEwallet ? 'bg-pink-100' : 'bg-blue-100'} rounded-full mx-auto mb-2 flex items-center justify-center">
                <i class="fas ${isEwallet ? 'fa-mobile-screen' : 'fa-landmark'} ${isEwallet ? 'text-pink-600' : 'text-blue-600'}"></i>
            </div>
            <p class="text-sm font-medium text-gray-900">${key}</p>
            <p class="text-xs text-gray-500 truncate" title="${value.nama}">${value.nama}</p>
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
            <div class="flex justify-between items-center pb-2 border-b border-gray-200">
                <span class="text-gray-600">Bank/E-Wallet:</span>
                <span class="font-semibold text-gray-900">${nama}</span>
            </div>
            <div class="flex justify-between items-center pb-2 border-b border-gray-200">
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
    const metodeNama = document.getElementById('metode_nama').value;
    document.getElementById('summaryMetode').textContent = `${metodeNama} - ${nama}`;
}

function setNominal(nominal) {
    const input = document.getElementById('nominal');
    input.value = nominal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    formatNominal(input);
    calculateTotal();
}

function formatNominal(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    let num = parseInt(value) || 0;
    
    // Validate min
    if (num > 0 && num < minDeposit) {
        input.classList.add('border-red-500');
    } else {
        input.classList.remove('border-red-500');
    }
    
    input.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function calculateTotal() {
    let nominalValue = document.getElementById('nominal').value;
    let nominal = parseInt(nominalValue.replace(/\./g, '')) || 0;
    document.getElementById('summaryTotal').textContent = 'Rp ' + nominal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function showToast(message, type = 'info') {
    const existing = document.getElementById('toastNotif');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.id = 'toastNotif';
    toast.style.cssText = 'position:fixed;top:80px;right:20px;z-index:9999;min-width:280px;max-width:400px;padding:1rem 1.25rem;border-radius:0.5rem;display:flex;align-items:center;gap:0.75rem;animation:slideIn 0.3s ease;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
    
    if (type === 'success') {
        toast.style.background = '#10b981';
        toast.style.color = 'white';
        toast.innerHTML = '<i class="fas fa-check-circle"></i><span>' + message + '</span>';
    } else if (type === 'error') {
        toast.style.background = '#ef4444';
        toast.style.color = 'white';
        toast.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>' + message + '</span>';
    } else {
        toast.style.background = '#3b82f6';
        toast.style.color = 'white';
        toast.innerHTML = '<i class="fas fa-info-circle"></i><span>' + message + '</span>';
    }
    
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
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
    let errorElement = null;
    
    if (!metode) {
        errorMessage = 'Silakan pilih metode deposit';
        isValid = false;
        errorElement = document.querySelector('.metode-card');
    } else if (!bank) {
        errorMessage = 'Silakan pilih bank/e-wallet tujuan';
        isValid = false;
        errorElement = document.getElementById('bankList');
    } else if (nominal < minDeposit) {
        errorMessage = 'Minimal deposit ' + formatRupiah(minDeposit);
        isValid = false;
        errorElement = document.getElementById('nominal');
    }
    
    if (!isValid) {
        e.preventDefault();
        
        // Scroll to error element
        if (errorElement) {
            errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        showToast('Error: ' + errorMessage, 'error');
    } else {
        // Show confirmation
        if (!confirm('Ajukan deposit sebesar ' + formatRupiah(nominal) + '?')) {
            e.preventDefault();
        }
    }
});

function formatRupiah(num) {
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Initialize
calculateTotal();
</script>

<?php 
include 'layout_footer.php';
$conn->close();
?>