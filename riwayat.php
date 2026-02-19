<?php
require_once 'config.php';
cekLogin();
$conn = koneksi();
$id_user = $_SESSION['id_user'];
$role = $_SESSION['role'];

// Filter
$filterJenis = $_GET['jenis'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterTanggal = $_GET['tanggal'] ?? '';
$filterBulan = $_GET['bulan'] ?? '';

// Query transaksi
$where = [];
$params = [];
$types = '';

if ($role != 'admin') {
    $where[] = "t.id_user = ?";
    $params[] = $id_user;
    $types .= 'i';
}
if ($filterJenis) {
    $where[] = "t.jenis_transaksi = ?";
    $params[] = $filterJenis;
    $types .= 's';
}
if ($filterStatus) {
    $where[] = "t.status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}
if ($filterTanggal) {
    $where[] = "DATE(t.created_at) = ?";
    $params[] = $filterTanggal;
    $types .= 's';
}
if ($filterBulan) {
    $where[] = "DATE_FORMAT(t.created_at, '%Y-%m') = ?";
    $params[] = $filterBulan;
    $types .= 's';
}

$whereClause = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT t.*, p.nama_produk, u.nama_lengkap FROM transaksi t 
        LEFT JOIN produk p ON t.produk_id = p.id 
        LEFT JOIN users u ON t.user_id = u.id" . $whereClause . " 
        ORDER BY t.created_at DESC LIMIT 100";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transaksi = $stmt->get_result();

if ($role == 'admin') {
    $statsQuery = $conn->query("SELECT COUNT(*) as total, 
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as sukses,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'failed'  THEN 1 ELSE 0 END) as gagal,
        SUM(CASE WHEN status = 'success' THEN total_bayar ELSE 0 END) as total_sukses
        FROM transaksi WHERE DATE(created_at) = CURDATE()");
    $stats = $statsQuery->fetch_assoc();

    $totalQuery = $conn->query("SELECT COUNT(*) as total_transaksi, SUM(total_bayar) as total_omset FROM transaksi WHERE status = 'success'");
    $total = $totalQuery->fetch_assoc();
}
$_SESSION['saldo'] = getSaldo($id_user);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - PPOB Express</title>
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

        /* ── Gradient Background ── */
        .bg-gradient-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .status-success { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
        .status-pending { background:#fef9c3; color:#a16207; border:1px solid #fde047; }
        .status-failed  { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; }

        .jenis-pulsa    { background:#dbeafe; color:#1d4ed8; border:1px solid #93c5fd; }
        .jenis-kuota    { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
        .jenis-listrik  { background:#fef9c3; color:#a16207; border:1px solid #fde047; }
        .jenis-transfer { background:#f3e8ff; color:#7e22ce; border:1px solid #d8b4fe; }

        /* ── Stat Card ── */
        .stat-card {
            border-radius: 0.75rem;
            border: 1px solid #e8ecf0;
            background: white;
            padding: 1rem 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* ── Card ── */
        .card {
            background: white;
            border-radius: 0.75rem;
            border: 1px solid #e8ecf0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        /* ── Table ── */
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
        .table-row:hover td {
            background: #f8fafc;
        }
        .table-row:last-child td {
            border-bottom: none;
        }

        /* ── Filter Icon Rotate ── */
        .icon-rotated { transform: rotate(180deg); }
        #filterIcon { transition: transform 0.3s ease; }

        /* ── Toggle Button ── */
        #toggleBtn {
            transition: background 0.2s ease, transform 0.15s ease;
        }
        #toggleBtn:active { transform: scale(0.92); }

        /* ── Mobile card ── */
        .trx-card {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s ease;
        }
        .trx-card:last-child { border-bottom: none; }
        .trx-card:hover { background: #f8fafc; }

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
        <a href="deposit.php"  class="menu-item"><i class="fas fa-plus-circle"></i><span>Deposit Saldo</span></a>
        <a href="riwayat.php"  class="menu-item active"><i class="fas fa-history"></i><span>Riwayat Transaksi</span></a>
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
        <div class="animate-fade-in-up">
            <h2 class="text-xl font-bold text-gray-900">Riwayat Transaksi</h2>
            <p class="text-sm text-gray-500 mt-0.5">Lihat semua transaksi dan aktifitas Anda</p>
        </div>

        <!-- ── Stats (Admin only) ── -->
        <?php if ($role == 'admin'): ?>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 animate-fade-in-up delay-100">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Hari Ini</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar-day text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Sukses</p>
                        <p class="text-2xl font-bold text-green-600"><?= $stats['sukses'] ?></p>
                    </div>
                    <div class="w-10 h-10 bg-green-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Pending</p>
                        <p class="text-2xl font-bold text-amber-600"><?= $stats['pending'] ?></p>
                    </div>
                    <div class="w-10 h-10 bg-amber-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-clock text-amber-600"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Omset Sukses</p>
                        <p class="text-lg font-bold text-blue-600"><?= rupiah($stats['total_sukses'] ?? 0) ?></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center">
                        <i class="fas fa-coins text-blue-600"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Filter Card ── -->
        <div class="card animate-fade-in-up delay-200">
            <!-- Mobile: collapsible -->
            <div class="md:hidden">
                <button onclick="toggleFilter()"
                    class="w-full flex items-center justify-between px-4 py-3.5 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-xl transition">
                    <span class="flex items-center gap-2">
                        <i class="fas fa-filter text-blue-600"></i>
                        Filter Transaksi
                        <?php if ($filterJenis || $filterStatus || $filterTanggal || $filterBulan): ?>
                        <span class="w-2 h-2 bg-blue-600 rounded-full"></span>
                        <?php endif; ?>
                    </span>
                    <i id="filterIcon" class="fas fa-chevron-down text-gray-400 text-xs"></i>
                </button>

                <div id="filterContent" class="hidden px-4 pb-4 space-y-3 border-t border-gray-100">
                    <form method="GET" class="pt-3 space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Jenis</label>
                            <select name="jenis" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Semua Jenis</option>
                                <option value="pulsa"    <?= $filterJenis=='pulsa'    ?'selected':'' ?>>Pulsa</option>
                                <option value="kuota"    <?= $filterJenis=='kuota'    ?'selected':'' ?>>Paket Data</option>
                                <option value="listrik"  <?= $filterJenis=='listrik'  ?'selected':'' ?>>Token Listrik</option>
                                <option value="transfer" <?= $filterJenis=='transfer' ?'selected':'' ?>>Transfer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Semua Status</option>
                                <option value="success" <?= $filterStatus=='success'?'selected':'' ?>>Sukses</option>
                                <option value="pending" <?= $filterStatus=='pending'?'selected':'' ?>>Pending</option>
                                <option value="failed"  <?= $filterStatus=='failed' ?'selected':'' ?>>Gagal</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal</label>
                            <input type="date" name="tanggal" value="<?= $filterTanggal ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Bulan</label>
                            <input type="month" name="bulan" value="<?= $filterBulan ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="flex gap-2 pt-1">
                            <button type="submit"
                                class="flex-1 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium flex items-center justify-center gap-1.5 transition">
                                <i class="fas fa-filter text-xs"></i> Terapkan
                            </button>
                            <a href="riwayat.php"
                                class="flex-1 py-2 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg text-sm font-medium flex items-center justify-center gap-1.5 transition">
                                <i class="fas fa-sync-alt text-xs"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Desktop: full form -->
            <div class="hidden md:block p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-filter text-blue-600 text-sm"></i>
                        Filter Transaksi
                    </h3>
                    <?php if ($filterJenis || $filterStatus || $filterTanggal || $filterBulan): ?>
                    <a href="riwayat.php" class="text-xs text-blue-600 hover:underline flex items-center gap-1">
                        <i class="fas fa-times"></i> Hapus Filter
                    </a>
                    <?php endif; ?>
                </div>
                <form method="GET">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Jenis Transaksi</label>
                            <select name="jenis" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Semua Jenis</option>
                                <option value="pulsa"    <?= $filterJenis=='pulsa'    ?'selected':'' ?>>Pulsa</option>
                                <option value="kuota"    <?= $filterJenis=='kuota'    ?'selected':'' ?>>Paket Data</option>
                                <option value="listrik"  <?= $filterJenis=='listrik'  ?'selected':'' ?>>Token Listrik</option>
                                <option value="transfer" <?= $filterJenis=='transfer' ?'selected':'' ?>>Transfer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Semua Status</option>
                                <option value="success" <?= $filterStatus=='success'?'selected':'' ?>>Sukses</option>
                                <option value="pending" <?= $filterStatus=='pending'?'selected':'' ?>>Pending</option>
                                <option value="failed"  <?= $filterStatus=='failed' ?'selected':'' ?>>Gagal</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Tanggal</label>
                            <input type="date" name="tanggal" value="<?= $filterTanggal ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Bulan</label>
                            <input type="month" name="bulan" value="<?= $filterBulan ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit"
                            class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium flex items-center gap-2 transition">
                            <i class="fas fa-filter text-xs"></i> Terapkan Filter
                        </button>
                        <a href="riwayat.php"
                            class="px-5 py-2 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg text-sm font-medium flex items-center gap-2 transition">
                            <i class="fas fa-sync-alt text-xs"></i> Reset
                        </a>
                        <button type="button" onclick="exportToExcel()"
                            class="ml-auto px-5 py-2 border border-green-300 hover:bg-green-50 text-green-700 rounded-lg text-sm font-medium flex items-center gap-2 transition">
                            <i class="fas fa-file-excel text-xs"></i> Export Excel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Transaction List ── -->
        <div class="card overflow-hidden animate-fade-in-up delay-300">
            <!-- Card Header -->
            <div class="px-4 md:px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h3 class="font-semibold text-gray-900">Daftar Transaksi</h3>
                    <p class="text-xs text-gray-400 mt-0.5"><?= $transaksi->num_rows ?> data ditemukan</p>
                </div>
                <!-- Mobile Export -->
                <button onclick="exportToExcel()"
                    class="md:hidden px-3 py-1.5 border border-green-300 text-green-700 hover:bg-green-50 rounded-lg text-xs font-medium flex items-center gap-1.5 transition">
                    <i class="fas fa-file-excel"></i> Export
                </button>
            </div>

            <?php if ($transaksi->num_rows > 0): ?>

                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="table-header-row">
                                <?php if ($role == 'admin'): ?>
                                <th class="text-left">Invoice</th>
                                <th class="text-left">User</th>
                                <?php endif; ?>
                                <th class="text-left">Jenis</th>
                                <th class="text-left">Produk</th>
                                <th class="text-left">No. Tujuan</th>
                                <th class="text-left">Total</th>
                                <th class="text-left">Status</th>
                                <th class="text-left">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $transaksi->data_seek(0);
                            while ($trx = $transaksi->fetch_assoc()):
                            ?>
                            <tr class="table-row">
                                <?php if ($role == 'admin'): ?>
                                <td class="font-mono text-xs text-gray-600 whitespace-nowrap"><?= htmlspecialchars($trx['no_invoice']) ?></td>
                                <td class="whitespace-nowrap font-medium"><?= htmlspecialchars($trx['nama_lengkap']) ?></td>
                                <?php endif; ?>
                                <td class="whitespace-nowrap">
                                    <span class="badge jenis-<?= $trx['jenis_transaksi'] ?>"><?= ucfirst($trx['jenis_transaksi']) ?></span>
                                </td>
                                <td class="max-w-[160px] truncate"><?= htmlspecialchars($trx['nama_produk'] ?? '-') ?></td>
                                <td class="font-mono text-xs whitespace-nowrap"><?= htmlspecialchars($trx['no_tujuan']) ?></td>
                                <td class="font-semibold whitespace-nowrap"><?= rupiah($trx['total_bayar']) ?></td>
                                <td class="whitespace-nowrap">
                                    <span class="badge status-<?= $trx['status'] ?>"><?= ucfirst($trx['status']) ?></span>
                                </td>
                                <td class="text-xs text-gray-500 whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($trx['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden">
                    <?php
                    $transaksi->data_seek(0);
                    while ($trx = $transaksi->fetch_assoc()):
                    ?>
                    <div class="trx-card">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="badge jenis-<?= $trx['jenis_transaksi'] ?>"><?= ucfirst($trx['jenis_transaksi']) ?></span>
                                <span class="badge status-<?= $trx['status'] ?>"><?= ucfirst($trx['status']) ?></span>
                            </div>
                            <span class="font-bold text-blue-700 text-sm whitespace-nowrap"><?= rupiah($trx['total_bayar']) ?></span>
                        </div>

                        <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                            <?php if ($role == 'admin'): ?>
                            <div>
                                <p class="text-gray-400">Invoice</p>
                                <p class="font-mono text-gray-600 truncate"><?= htmlspecialchars($trx['no_invoice']) ?></p>
                            </div>
                            <div>
                                <p class="text-gray-400">User</p>
                                <p class="text-gray-700 truncate font-medium"><?= htmlspecialchars($trx['nama_lengkap']) ?></p>
                            </div>
                            <?php endif; ?>
                            <div>
                                <p class="text-gray-400">Produk</p>
                                <p class="text-gray-700 truncate"><?= htmlspecialchars($trx['nama_produk'] ?? ucfirst($trx['jenis_transaksi'])) ?></p>
                            </div>
                            <div>
                                <p class="text-gray-400">No. Tujuan</p>
                                <p class="font-mono text-gray-700"><?= htmlspecialchars($trx['no_tujuan']) ?></p>
                            </div>
                        </div>

                        <p class="text-xs text-gray-400 mt-2 pt-2 border-t border-gray-100">
                            <i class="fas fa-clock mr-1"></i><?= date('d M Y, H:i', strtotime($trx['created_at'])) ?>
                        </p>
                    </div>
                    <?php endwhile; ?>
                </div>

            <?php else: ?>
                <!-- Empty State -->
                <div class="text-center py-16 px-4">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-inbox text-gray-300 text-3xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-700 mb-1">Tidak ada transaksi</h3>
                    <p class="text-sm text-gray-400 mb-5">Tidak ada data yang sesuai dengan filter.</p>
                    <a href="riwayat.php"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition">
                        <i class="fas fa-sync-alt"></i> Reset Filter
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /page body -->
</div><!-- /main-content -->

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
//  FILTER TOGGLE (mobile)
// ═══════════════════════════════════════════════════════
function toggleFilter() {
    const content = document.getElementById('filterContent');
    const icon    = document.getElementById('filterIcon');
    const isHidden = content.classList.contains('hidden');

    if (isHidden) {
        content.classList.remove('hidden');
        icon.classList.add('icon-rotated');
    } else {
        content.classList.add('hidden');
        icon.classList.remove('icon-rotated');
    }
}

// ═══════════════════════════════════════════════════════
//  AUTO-SUBMIT FILTER on change
// ═══════════════════════════════════════════════════════
document.querySelectorAll('form[method="GET"] select, form[method="GET"] input[type="date"], form[method="GET"] input[type="month"]')
    .forEach(el => el.addEventListener('change', function () {
        this.closest('form').submit();
    }));

// ═══════════════════════════════════════════════════════
//  EXPORT TO EXCEL
// ═══════════════════════════════════════════════════════
function exportToExcel() {
    const table = document.querySelector('table');
    if (!table) { alert('Tidak ada data untuk diekspor'); return; }

    const html = `<html><head><style>
        table{border-collapse:collapse;width:100%}
        th,td{border:1px solid #ddd;padding:8px;text-align:left}
        th{background:#f2f2f2;font-weight:bold;color:#2f2f2f}
    </style></head><body>
        <h2>Riwayat Transaksi PPOB Express</h2>
        <p>Tanggal Export: ${new Date().toLocaleDateString('id-ID')}</p>
        ${table.outerHTML}
    </body></html>`;

    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `riwayat-transaksi-${new Date().toISOString().split('T')[0]}.xls`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>

</body>
</html>
<?php $conn->close(); ?>