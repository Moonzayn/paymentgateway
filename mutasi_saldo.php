<?php
require_once 'config.php';
cekLogin();

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$_SESSION['saldo'] = getSaldo($user_id);

$pageTitle   = 'Mutasi Saldo';
$pageIcon    = 'fas fa-wallet';
$pageDesc    = 'Riwayat perubahan saldo Anda';
$currentPage = 'mutasi_saldo';
$additionalHeadScripts = '';

$filterJenis   = $_GET['jenis']   ?? '';
$filterStatus  = $_GET['status']  ?? '';
$filterTanggal = $_GET['tanggal'] ?? '';
$filterBulan   = $_GET['bulan']   ?? '';
$filterUserId  = $_GET['user_id'] ?? '';
$search       = $_GET['search']   ?? '';
$userSearch    = $_GET['user_search'] ?? '';

if ($role == 'admin' && $userSearch && !$filterUserId) {
    $stmtSearch = $conn->prepare("SELECT id FROM users WHERE nama_lengkap LIKE ? OR username LIKE ? LIMIT 1");
    $searchTerm = "%{$userSearch}%";
    $stmtSearch->bind_param("ss", $searchTerm, $searchTerm);
    $stmtSearch->execute();
    $resultSearch = $stmtSearch->get_result();
    if ($rowSearch = $resultSearch->fetch_assoc()) {
        $filterUserId = $rowSearch['id'];
    }
    $stmtSearch->close();
}

$jenisList = [
    'deposit'    => ['label' => 'Deposit', 'icon' => 'fa-money-bill-wave', 'color' => 'blue', 'tipe' => 'masuk'],
    'transfer'   => ['label' => 'Transfer', 'icon' => 'fa-exchange-alt', 'color' => 'purple', 'tipe' => 'both'],
    'refund'    => ['label' => 'Refund', 'icon' => 'fa-undo', 'color' => 'green', 'tipe' => 'masuk'],
    'admin'     => ['label' => 'Bonus/Adjustment', 'icon' => 'fa-gift', 'color' => 'amber', 'tipe' => 'masuk'],
    'transaksi' => ['label' => 'Transaksi', 'icon' => 'fa-shopping-cart', 'color' => 'red', 'tipe' => 'keluar'],
];

$where  = [];
$params = [];
$types  = '';

if ($role != 'admin') {
    $where[]  = "t.user_id = ?";
    $params[] = $user_id;
    $types   .= 'i';
} else {
    if ($filterUserId) {
        $where[]  = "t.user_id = ?";
        $params[] = $filterUserId;
        $types   .= 'i';
    }
}

$where[] = "t.saldo_sebelum IS NOT NULL AND t.saldo_sesudah IS NOT NULL";

if ($filterJenis) {
    if ($filterJenis == 'transaksi') {
        $where[]  = "t.jenis_transaksi IN ('pulsa', 'kuota', 'listrik', 'game')";
    } else {
        $where[]  = "t.jenis_transaksi = ?";
        $params[] = $filterJenis;
        $types   .= 's';
    }
}

if ($filterStatus) {
    $where[]  = "t.status = ?";
    $params[] = $filterStatus;
    $types   .= 's';
}

if ($filterTanggal) {
    $where[]  = "DATE(t.created_at) = ?";
    $params[] = $filterTanggal;
    $types   .= 's';
}

if ($filterBulan) {
    $where[]  = "DATE_FORMAT(t.created_at,'%Y-%m') = ?";
    $params[] = $filterBulan;
    $types   .= 's';
}

$whereClause = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";

$sql = "SELECT t.*, u.nama_lengkap, u.username
        FROM transaksi t
        LEFT JOIN users u ON t.user_id = u.id"
       . $whereClause .
       " ORDER BY t.created_at DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$mutasi = $stmt->get_result();

if ($role == 'admin') {
    $allUsers = $conn->query("SELECT id, nama_lengkap, username FROM users WHERE role = 'member' ORDER BY nama_lengkap");
    $allUsersArr = [];
    while ($u = $allUsers->fetch_assoc()) {
        $allUsersArr[] = $u;
    }
    $allUsers->data_seek(0);
    
    $selectedUserName = '';
    if ($filterUserId) {
        $stmtUser = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
        $stmtUser->bind_param("i", $filterUserId);
        $stmtUser->execute();
        $resultUser = $stmtUser->get_result();
        if ($rowUser = $resultUser->fetch_assoc()) {
            $selectedUserName = $rowUser['nama_lengkap'];
        }
        $stmtUser->close();
    }
    
    $statsQuery = $conn->query(
        "SELECT 
         SUM(CASE WHEN saldo_sesudah > saldo_sebelum THEN saldo_sesudah - saldo_sebelum ELSE 0 END) as total_masuk,
         SUM(CASE WHEN saldo_sesudah < saldo_sebelum THEN saldo_sebelum - saldo_sesudah ELSE 0 END) as total_keluar
         FROM transaksi 
         WHERE saldo_sebelum IS NOT NULL AND saldo_sesudah IS NOT NULL 
         AND DATE(created_at) = CURDATE()"
    );
    $stats = $statsQuery->fetch_assoc();
}

$alert = getAlert();

include 'layout.php';
?>

<style>
.badge {
    display: inline-block;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 600;
    white-space: nowrap;
}

.badge-masuk { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.badge-keluar { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.badge-pending { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

.badge-deposit { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
.badge-transfer { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
.badge-refund { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.badge-admin { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.badge-transaksi { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

.stat-card {
    border-radius: 0.75rem;
    border: 1px solid #e8ecf0;
    background: white;
    padding: 1rem 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}

.card {
    background: white;
    border: 1px solid #e8ecf0;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}

.table-header-row th {
    background: #f8fafc;
    color: #374151;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.75rem 1.25rem;
    border-bottom: 2px solid #e5e7eb;
}
.table-row td {
    padding: 0.875rem 1.25rem;
    font-size: 0.875rem;
    color: #374151;
    border-bottom: 1px solid #f1f5f9;
}
.table-row:hover td { background: #f8fafc; }

.mutation-card {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s ease;
}
.mutation-card:hover { background: #f8fafc; }
.mutation-card:last-child { border-bottom: none; }

.saldo-masuk { color: #16a34a; font-weight: 700; }
.saldo-keluar { color: #dc2626; font-weight: 700; }

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-slide-in { animation: slideIn 0.3s ease forwards; }
.delay-100 { animation-delay: 0.1s; }
.delay-200 { animation-delay: 0.2s; }
.delay-300 { animation-delay: 0.3s; }

@media (max-width: 767px) {
    .hide-mobile { display: none !important; }
}
@media (min-width: 768px) {
    .hide-desktop { display: none !important; }
}

.select-search {
    position: relative;
}
.select-search__input {
    width: 100%;
    padding-right: 36px;
}
.select-search__dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #d1d5db;
    border-top: none;
    border-radius: 0 0 0.5rem 0.5rem;
    max-height: 200px;
    overflow-y: auto;
    z-index: 50;
    display: none;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
.select-search__dropdown.open {
    display: block;
}
.select-search__option {
    padding: 0.625rem 0.75rem;
    cursor: pointer;
    font-size: 0.875rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.select-search__option:hover {
    background: #f3f4f6;
}
.select-search__option.selected {
    background: #eff6ff;
    color: #2563eb;
}
.select-search__option-name {
    font-weight: 500;
}
.select-search__option-username {
    color: #9ca3af;
    font-size: 0.75rem;
}
.select-search__clear {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #9ca3af;
    padding: 4px;
    display: none;
}
.select-search__clear:hover {
    color: #6b7280;
}
.select-search.has-value .select-search__clear {
    display: block;
}
</style>

<div class="space-y-5">

    <?php if ($role == 'admin'): ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 animate-slide-in delay-100">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Masuk Hari Ini</p>
                    <p class="text-xl font-bold text-green-600"><?= rupiah($stats['total_masuk'] ?? 0) ?></p>
                </div>
                <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center">
                    <i class="fas fa-arrow-down text-green-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Keluar Hari Ini</p>
                    <p class="text-xl font-bold text-red-600"><?= rupiah($stats['total_keluar'] ?? 0) ?></p>
                </div>
                <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center">
                    <i class="fas fa-arrow-up text-red-600"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card animate-slide-in delay-200">
        <div class="hide-desktop">
            <button onclick="toggleFilter()" class="w-full flex items-center justify-between px-4 py-3.5 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-xl transition">
                <span class="flex items-center gap-2">
                    <i class="fas fa-filter text-blue-600"></i>
                    Filter Mutasi
                    <?php if ($filterJenis || $filterStatus || $filterTanggal || $filterBulan || $filterUserId): ?>
                    <span class="w-2 h-2 bg-blue-600 rounded-full"></span>
                    <?php endif; ?>
                </span>
                <i id="filterIcon" class="fas fa-chevron-down text-gray-400 text-xs"></i>
            </button>
            <div id="filterContent" class="hidden px-4 pb-4 border-t border-gray-100 space-y-3">
                <form method="GET" class="pt-3 space-y-3">
                    <?php if ($role == 'admin'): ?>
                    <div class="select-search <?= $filterUserId ? 'has-value' : '' ?>" id="selectSearchMobile">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Pilih User</label>
                        <input type="text" class="select-search__input w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                            placeholder="Cari user..."
                            value="<?= htmlspecialchars($selectedUserName ?: ($userSearch && !$filterUserId ? $userSearch : '')) ?>"
                            autocomplete="off">
                        <i class="select-search__clear fas fa-times-circle"></i>
                        <input type="hidden" name="user_id" class="select-search__value" value="<?= $filterUserId ?>">
                        <div class="select-search__dropdown">
                            <div class="select-search__option" data-value="">
                                <span class="select-search__option-name">Semua User</span>
                            </div>
                            <?php foreach ($allUsersArr as $u): ?>
                            <div class="select-search__option <?= $filterUserId == $u['id'] ? 'selected' : '' ?>" data-value="<?= $u['id'] ?>" data-text="<?= htmlspecialchars($u['nama_lengkap']) ?>">
                                <span class="select-search__option-name"><?= htmlspecialchars($u['nama_lengkap']) ?></span>
                                <span class="select-search__option-username">@<?= htmlspecialchars($u['username']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Jenis</label>
                        <select name="jenis" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">Semua Jenis</option>
                            <option value="deposit" <?= $filterJenis=='deposit'?'selected':'' ?>>Deposit</option>
                            <option value="transfer" <?= $filterJenis=='transfer'?'selected':'' ?>>Transfer</option>
                            <option value="refund" <?= $filterJenis=='refund'?'selected':'' ?>>Refund</option>
                            <option value="admin" <?= $filterJenis=='admin'?'selected':'' ?>>Bonus/Adjustment</option>
                            <option value="transaksi" <?= $filterJenis=='transaksi'?'selected':'' ?>>Transaksi</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">Semua Status</option>
                            <option value="success" <?= $filterStatus=='success'?'selected':'' ?>>Sukses</option>
                            <option value="pending" <?= $filterStatus=='pending'?'selected':'' ?>>Pending</option>
                            <option value="failed" <?= $filterStatus=='failed'?'selected':'' ?>>Gagal</option>
                            <option value="refund" <?= $filterStatus=='refund'?'selected':'' ?>>Refund</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal</label>
                        <input type="date" name="tanggal" value="<?= $filterTanggal ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Bulan</label>
                        <input type="month" name="bulan" value="<?= $filterBulan ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">Terapkan</button>
                        <a href="mutasi_saldo.php" class="px-5 py-2 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg text-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="hide-mobile p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-filter text-blue-600 text-sm"></i>
                    Filter Mutasi Saldo
                </h3>
                <?php if ($filterJenis || $filterStatus || $filterTanggal || $filterBulan || $filterUserId): ?>
                <a href="mutasi_saldo.php" class="text-xs text-blue-600 hover:underline">
                    <i class="fas fa-times"></i> Hapus Filter
                </a>
                <?php endif; ?>
            </div>
            <form method="GET">
                <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-4">
                    <?php if ($role == 'admin'): ?>
                    <div class="lg:col-span-2 select-search <?= $filterUserId ? 'has-value' : '' ?>" id="selectSearchDesktop">
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Pilih User</label>
                        <input type="text" class="select-search__input w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                            placeholder="Cari user..."
                            value="<?= htmlspecialchars($selectedUserName ?: ($userSearch && !$filterUserId ? $userSearch : '')) ?>"
                            autocomplete="off">
                        <i class="select-search__clear fas fa-times-circle"></i>
                        <input type="hidden" name="user_id" class="select-search__value" value="<?= $filterUserId ?>">
                        <div class="select-search__dropdown">
                            <div class="select-search__option" data-value="">
                                <span class="select-search__option-name">Semua User</span>
                            </div>
                            <?php foreach ($allUsersArr as $u): ?>
                            <div class="select-search__option <?= $filterUserId == $u['id'] ? 'selected' : '' ?>" data-value="<?= $u['id'] ?>" data-text="<?= htmlspecialchars($u['nama_lengkap']) ?>">
                                <span class="select-search__option-name"><?= htmlspecialchars($u['nama_lengkap']) ?></span>
                                <span class="select-search__option-username">@<?= htmlspecialchars($u['username']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Jenis</label>
                        <select name="jenis" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">Semua Jenis</option>
                            <option value="deposit" <?= $filterJenis=='deposit'?'selected':'' ?>>Deposit</option>
                            <option value="transfer" <?= $filterJenis=='transfer'?'selected':'' ?>>Transfer</option>
                            <option value="refund" <?= $filterJenis=='refund'?'selected':'' ?>>Refund</option>
                            <option value="admin" <?= $filterJenis=='admin'?'selected':'' ?>>Bonus/Adjustment</option>
                            <option value="transaksi" <?= $filterJenis=='transaksi'?'selected':'' ?>>Transaksi</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">Semua Status</option>
                            <option value="success" <?= $filterStatus=='success'?'selected':'' ?>>Sukses</option>
                            <option value="pending" <?= $filterStatus=='pending'?'selected':'' ?>>Pending</option>
                            <option value="failed" <?= $filterStatus=='failed'?'selected':'' ?>>Gagal</option>
                            <option value="refund" <?= $filterStatus=='refund'?'selected':'' ?>>Refund</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Tanggal</label>
                        <input type="date" name="tanggal" value="<?= $filterTanggal ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Bulan</label>
                        <input type="month" name="bulan" value="<?= $filterBulan ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                        <i class="fas fa-filter mr-1"></i> Terapkan
                    </button>
                    <a href="mutasi_saldo.php" class="px-5 py-2 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg text-sm font-medium">
                        <i class="fas fa-sync-alt mr-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card animate-slide-in delay-300 overflow-hidden">
        <div class="px-4 md:px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900">Riwayat Mutasi Saldo</h3>
            <p class="text-xs text-gray-400 mt-0.5"><?= $mutasi->num_rows ?> data ditemukan</p>
        </div>

        <?php if ($mutasi->num_rows > 0): ?>
        <div class="hide-mobile overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-header-row">
                        <?php if ($role == 'admin'): ?>
                        <th class="text-left">User</th>
                        <?php endif; ?>
                        <th class="text-left">Tanggal</th>
                        <th class="text-left">Jenis</th>
                        <th class="text-left">No. Invoice</th>
                        <th class="text-right">Masuk</th>
                        <th class="text-right">Keluar</th>
                        <th class="text-right">Saldo Sebelum</th>
                        <th class="text-right">Saldo Sesudah</th>
                        <th class="text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $mutasi->data_seek(0);
                while ($m = $mutasi->fetch_assoc()):
                    $diff = $m['saldo_sesudah'] - $m['saldo_sebelum'];
                    $isMasuk = $diff > 0;
                    $isKeluar = $diff < 0;
                    
                    $jenisBadge = 'transaksi';
                    if (in_array($m['jenis_transaksi'], ['deposit', 'transfer', 'refund', 'admin'])) {
                        $jenisBadge = $m['jenis_transaksi'];
                    }
                ?>
                    <tr class="table-row">
                        <?php if ($role == 'admin'): ?>
                        <td class="whitespace-nowrap">
                            <span class="font-medium"><?= htmlspecialchars($m['nama_lengkap']) ?></span>
                            <span class="text-xs text-gray-400">@<?= htmlspecialchars($m['username']) ?></span>
                        </td>
                        <?php endif; ?>
                        <td class="whitespace-nowrap text-xs"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                        <td class="whitespace-nowrap">
                            <span class="badge badge-<?= $jenisBadge ?>"><?= ucfirst($m['jenis_transaksi']) ?></span>
                        </td>
                        <td class="font-mono text-xs"><?= htmlspecialchars($m['no_invoice']) ?></td>
                        <td class="text-right whitespace-nowrap <?= $isMasuk ? 'saldo-masuk' : 'text-gray-300' ?>">
                            <?= $isMasuk ? '+' . rupiah($diff) : '-' ?>
                        </td>
                        <td class="text-right whitespace-nowrap <?= $isKeluar ? 'saldo-keluar' : 'text-gray-300' ?>">
                            <?= $isKeluar ? '-' . rupiah(abs($diff)) : '-' ?>
                        </td>
                        <td class="text-right whitespace-nowrap"><?= rupiah($m['saldo_sebelum']) ?></td>
                        <td class="text-right whitespace-nowrap font-semibold"><?= rupiah($m['saldo_sesudah']) ?></td>
                        <td class="whitespace-nowrap">
                            <?php if ($m['status'] == 'success'): ?>
                            <span class="badge badge-masuk">Sukses</span>
                            <?php elseif ($m['status'] == 'pending'): ?>
                            <span class="badge badge-pending">Pending</span>
                            <?php elseif ($m['status'] == 'failed'): ?>
                            <span class="badge badge-keluar">Gagal</span>
                            <?php elseif ($m['status'] == 'refund'): ?>
                            <span class="badge badge-masuk">Refund</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="hide-desktop">
        <?php
        $mutasi->data_seek(0);
        while ($m = $mutasi->fetch_assoc()):
            $diff = $m['saldo_sesudah'] - $m['saldo_sebelum'];
            $isMasuk = $diff > 0;
            $isKeluar = $diff < 0;
            
            $jenisBadge = 'transaksi';
            if (in_array($m['jenis_transaksi'], ['deposit', 'transfer', 'refund', 'admin'])) {
                $jenisBadge = $m['jenis_transaksi'];
            }
        ?>
            <div class="mutation-card">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <span class="badge badge-<?= $jenisBadge ?>"><?= ucfirst($m['jenis_transaksi']) ?></span>
                        <?php if ($m['status'] == 'success'): ?>
                        <span class="badge badge-masuk">Sukses</span>
                        <?php elseif ($m['status'] == 'pending'): ?>
                        <span class="badge badge-pending">Pending</span>
                        <?php elseif ($m['status'] == 'failed'): ?>
                        <span class="badge badge-keluar">Gagal</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <?php if ($isMasuk): ?>
                        <span class="saldo-masuk">+<?= rupiah($diff) ?></span>
                        <?php elseif ($isKeluar): ?>
                        <span class="saldo-keluar">-<?= rupiah(abs($diff)) ?></span>
                        <?php else: ?>
                        <span class="text-gray-400">Rp 0</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <?php if ($role == 'admin'): ?>
                    <div>
                        <p class="text-gray-400">User</p>
                        <p class="font-medium"><?= htmlspecialchars($m['nama_lengkap']) ?></p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <p class="text-gray-400">Invoice</p>
                        <p class="font-mono"><?= htmlspecialchars($m['no_invoice']) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400">Saldo Sebelum</p>
                        <p class="font-medium"><?= rupiah($m['saldo_sebelum']) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400">Saldo Sesudah</p>
                        <p class="font-semibold"><?= rupiah($m['saldo_sesudah']) ?></p>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2 pt-2 border-t border-gray-100">
                    <i class="fas fa-clock mr-1"></i><?= date('d M Y, H:i', strtotime($m['created_at'])) ?>
                </p>
            </div>
        <?php endwhile; ?>
        </div>

        <?php else: ?>
        <div class="text-center py-16 px-4">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-wallet text-gray-300 text-3xl"></i>
            </div>
            <h3 class="font-semibold text-gray-700 mb-1">Tidak ada mutasi saldo</h3>
            <p class="text-sm text-gray-400">Belum ada perubahan saldo yang tercatat.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleFilter() {
    const content = document.getElementById('filterContent');
    const icon = document.getElementById('filterIcon');
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        icon.classList.add('icon-rotated');
    } else {
        content.classList.add('hidden');
        icon.classList.remove('icon-rotated');
    }
}

document.querySelectorAll('.select-search').forEach(function(container) {
    const input = container.querySelector('.select-search__input');
    const dropdown = container.querySelector('.select-search__dropdown');
    const hiddenInput = container.querySelector('.select-search__value');
    const clearBtn = container.querySelector('.select-search__clear');

    input.addEventListener('focus', function() {
        dropdown.classList.add('open');
        filterOptions('');
    });

    input.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        filterOptions(query);
        dropdown.classList.add('open');
        container.classList.toggle('has-value', this.value !== '');
    });

    input.addEventListener('blur', function() {
        setTimeout(() => dropdown.classList.remove('open'), 200);
    });

    clearBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        input.value = '';
        hiddenInput.value = '';
        container.classList.remove('has-value');
        filterOptions('');
    });

    function filterOptions(query) {
        const options = dropdown.querySelectorAll('.select-search__option');
        options.forEach(function(option) {
            const text = (option.dataset.text || option.textContent).toLowerCase();
            option.style.display = text.includes(query) || option.dataset.value === '' ? '' : 'none';
        });
    }

    dropdown.querySelectorAll('.select-search__option').forEach(function(option) {
        option.addEventListener('mousedown', function(e) {
            e.preventDefault();
            const value = this.dataset.value;
            const text = this.dataset.text || this.querySelector('.select-search__option-name').textContent;
            input.value = text;
            hiddenInput.value = value;
            container.classList.toggle('has-value', value !== '');
            dropdown.classList.remove('open');
        });
    });
});
</script>

<?php 
include 'layout_footer.php';
$conn->close();
?>
