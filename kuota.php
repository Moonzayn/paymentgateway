<?php
require_once 'config.php';
cekLogin();
$conn = koneksi();
$id_user = $_SESSION['id_user'];
$_SESSION['saldo'] = getSaldo($id_user);

// Ambil produk kuota
$produkKuota = $conn->query("SELECT * FROM produk WHERE kategori_id = 2 AND status = 'active' ORDER BY provider, harga_jual");
$produkByProvider = [];
while ($row = $produkKuota->fetch_assoc()) {
    $produkByProvider[$row['provider']][] = $row;
}

// Proses pembelian
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: kuota.php");
        exit;
    }

    $no_hp = preg_replace('/[^0-9]/', '', $_POST['no_hp'] ?? '');
    $id_produk = intval($_POST['id_produk'] ?? 0);

    if (empty($no_hp) || strlen($no_hp) < 10 || strlen($no_hp) > 15) {
        setAlert('error', 'Nomor HP tidak valid! (10-15 digit)');
    } elseif ($id_produk == 0 || $id_produk > 100000) {
        setAlert('error', 'Pilih paket data yang valid!');
    } else {
        $stmt = $conn->prepare("SELECT * FROM produk WHERE id = ?");
        $stmt->bind_param("i", $id_produk);
        $stmt->execute();
        $produk = $stmt->get_result()->fetch_assoc();

        if (!$produk) {
            setAlert('error', 'Produk tidak ditemukan!');
        } else {
            $harga = $produk['harga_jual'];
            $saldo = getSaldo($id_user);

            if ($saldo < $harga) {
                setAlert('error', 'Saldo tidak mencukupi! Silakan deposit terlebih dahulu.');
            } else {
                $invoice = generateInvoice();
                $saldo_sebelum = $saldo;
                $saldo_sesudah = $saldo - $harga;

                $stmt = $conn->prepare("INSERT INTO transaksi (id_user, id_produk, no_invoice, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan) VALUES (?, ?, ?, 'kuota', ?, ?, ?, 0, ?, ?, ?, 'success', 'Pembelian paket data berhasil')");
                $stmt->bind_param("iissddddd", $id_user, $id_produk, $invoice, $no_hp, $produk['nominal'], $harga, $harga, $saldo_sebelum, $saldo_sesudah);

                if ($stmt->execute()) {
                    updateSaldo($id_user, $harga, 'kurang');
                    $_SESSION['saldo'] = getSaldo($id_user);
                    setAlert('success', 'Pembelian paket data berhasil! Invoice: ' . $invoice);
                } else {
                    setAlert('error', 'Terjadi kesalahan. Silakan coba lagi.');
                }
            }
        }
    }
    header("Location: kuota.php");
    exit;
}

$alert = getAlert();

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max(0, $bytes);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paket Data - PPOB Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
        }

        /* ========== SIDEBAR ========== */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            z-index: 50;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }

        .sidebar.closed {
            transform: translateX(-100%);
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .sidebar-brand {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
        }

        .sidebar-brand span {
            color: #2563eb;
        }

        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 10px;
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
            transition: all 0.2s ease;
        }

        .nav-item:hover {
            background: #f1f5f9;
            color: #2563eb;
        }

        .nav-item.active {
            background: #eff6ff;
            color: #2563eb;
            font-weight: 600;
        }

        .nav-item i {
            width: 20px;
            text-align: center;
        }

        .nav-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 16px 0;
        }

        .nav-label {
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 16px;
            margin-bottom: 8px;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid #e2e8f0;
        }

        /* ========== OVERLAY ========== */
        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 40;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /* ========== MAIN CONTENT ========== */
        .main-wrapper {
            margin-left: 260px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .main-wrapper.expanded {
            margin-left: 0;
        }

        /* ========== TOP HEADER ========== */
        .top-header {
            position: sticky;
            top: 0;
            z-index: 30;
            background: #ffffff;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .toggle-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: #f1f5f9;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 18px;
            transition: all 0.2s ease;
        }

        .toggle-btn:hover {
            background: #e2e8f0;
            color: #2563eb;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
        }

        .user-details {
            display: none;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        .user-role {
            font-size: 12px;
            color: #64748b;
        }

        .saldo-box {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 1px solid #bfdbfe;
            padding: 8px 16px;
            border-radius: 10px;
            text-align: right;
        }

        .saldo-label {
            font-size: 11px;
            color: #64748b;
        }

        .saldo-value {
            font-size: 14px;
            font-weight: 700;
            color: #2563eb;
        }

        /* ========== PAGE CONTENT ========== */
        .page-content {
            padding: 24px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-title {
            margin-bottom: 24px;
        }

        .page-title h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .page-title p {
            font-size: 14px;
            color: #64748b;
        }

        /* ========== ALERT ========== */
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert i {
            font-size: 18px;
            margin-top: 2px;
        }

        .alert-message {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
        }

        /* ========== CARD ========== */
        .card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card-body {
            padding: 20px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
        }

        /* ========== INPUT FIELD ========== */
        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
        }

        .input-field {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            color: #1e293b;
            transition: all 0.2s ease;
            outline: none;
        }

        .input-field:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .input-field::placeholder {
            color: #94a3b8;
        }

        .input-hint {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            font-size: 13px;
            color: #64748b;
        }

        .provider-detected {
            color: #2563eb;
            font-weight: 600;
        }

        /* ========== PROVIDER SECTION ========== */
        .provider-section {
            margin-bottom: 24px;
        }

        .provider-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .provider-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            color: white;
        }

        .provider-icon.telkomsel { background: linear-gradient(135deg, #dc2626, #991b1b); }
        .provider-icon.indosat { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .provider-icon.xl { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
        .provider-icon.tri { background: linear-gradient(135deg, #ec4899, #be185d); }
        .provider-icon.axis { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .provider-icon.smartfren { background: linear-gradient(135deg, #ef4444, #dc2626); }

        .provider-name {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
        }

        .provider-subtitle {
            font-size: 13px;
            color: #64748b;
        }

        /* ========== PRODUCT GRID ========== */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 12px;
        }

        /* ========== PRODUCT CARD ========== */
        .product-card {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .product-card:hover {
            border-color: #93c5fd;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
            transform: translateY(-2px);
        }

        .product-card.selected {
            border-color: #2563eb;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.2);
        }

        .product-content {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .product-info {
            flex: 1;
            min-width: 0;
        }

        .product-name {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 6px;
            line-height: 1.4;
        }

        .product-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .product-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            background: #f1f5f9;
            border-radius: 6px;
            font-size: 11px;
            color: #64748b;
        }

        .product-price-section {
            text-align: right;
            flex-shrink: 0;
        }

        .product-price {
            font-size: 16px;
            font-weight: 700;
            color: #2563eb;
        }

        .product-quota {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }

        .product-radio {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 22px;
            height: 22px;
            border: 2px solid #cbd5e1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .product-card.selected .product-radio {
            border-color: #2563eb;
            background: #2563eb;
        }

        .product-radio-dot {
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
            opacity: 0;
            transform: scale(0);
            transition: all 0.2s ease;
        }

        .product-card.selected .product-radio-dot {
            opacity: 1;
            transform: scale(1);
        }

        /* ========== STICKY SUMMARY ========== */
        .sticky-summary {
            position: fixed;
            bottom: 0;
            left: 260px;
            right: 0;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            padding: 16px 24px;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
            z-index: 35;
            transform: translateY(100%);
            transition: all 0.3s ease;
        }

        .sticky-summary.show {
            transform: translateY(0);
        }

        .sticky-summary.expanded {
            left: 0;
        }

        .summary-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .summary-info {
            flex: 1;
            min-width: 0;
        }

        .summary-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 2px;
        }

        .summary-product {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .summary-price-section {
            text-align: right;
        }

        .summary-price {
            font-size: 20px;
            font-weight: 700;
            color: #2563eb;
        }

        .summary-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            min-width: 160px;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            transform: translateY(-1px);
        }

        /* ========== FOOTER ========== */
        .footer {
            padding: 20px 24px;
            border-top: 1px solid #e2e8f0;
            background: #ffffff;
            margin-top: 40px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .footer-text {
            font-size: 13px;
            color: #64748b;
        }

        .footer-links {
            display: flex;
            gap: 24px;
        }

        .footer-link {
            font-size: 13px;
            color: #64748b;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer-link:hover {
            color: #2563eb;
        }

        /* ========== RESPONSIVE ========== */
        @media (min-width: 640px) {
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .user-details {
                display: block;
            }
        }

        @media (min-width: 1024px) {
            .product-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-wrapper {
                margin-left: 0;
            }

            .top-header {
                padding: 12px 16px;
            }

            .page-content {
                padding: 16px;
            }

            .page-title h1 {
                font-size: 20px;
            }

            .card-body {
                padding: 16px;
            }

            .sticky-summary {
                left: 0;
                padding: 12px 16px;
            }

            .summary-content {
                flex-wrap: wrap;
            }

            .summary-info {
                flex: 1 1 100%;
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
            }

            .summary-price-section {
                text-align: right;
            }

            .summary-actions {
                width: 100%;
            }

            .btn {
                flex: 1;
                padding: 14px 16px;
            }

            .btn-primary {
                flex: 2;
                min-width: auto;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }

            .footer-links {
                justify-content: center;
            }
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .empty-icon i {
            font-size: 32px;
            color: #94a3b8;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .empty-text {
            font-size: 14px;
            color: #64748b;
        }

        /* ========== LOADING ========== */
        .btn-loading {
            position: relative;
            color: transparent !important;
            pointer-events: none;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="sidebar-brand">
            <span>PPOB</span> Express
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="pulsa.php" class="nav-item">
            <i class="fas fa-mobile-alt"></i>
            <span>Isi Pulsa</span>
        </a>
        <a href="kuota.php" class="nav-item active">
            <i class="fas fa-wifi"></i>
            <span>Paket Data</span>
        </a>
        <a href="listrik.php" class="nav-item">
            <i class="fas fa-bolt"></i>
            <span>Token Listrik</span>
        </a>
        <a href="transfer.php" class="nav-item">
            <i class="fas fa-exchange-alt"></i>
            <span>Transfer</span>
        </a>
        <a href="deposit.php" class="nav-item">
            <i class="fas fa-plus-circle"></i>
            <span>Deposit</span>
        </a>
        <a href="riwayat.php" class="nav-item">
            <i class="fas fa-history"></i>
            <span>Riwayat</span>
        </a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
        <div class="nav-divider"></div>
        <div class="nav-label">Admin Menu</div>
        <a href="kelola_user.php" class="nav-item">
            <i class="fas fa-users"></i>
            <span>Kelola User</span>
        </a>
        <a href="kelola_produk.php" class="nav-item">
            <i class="fas fa-box"></i>
            <span>Kelola Produk</span>
        </a>
        <a href="laporan.php" class="nav-item">
            <i class="fas fa-chart-bar"></i>
            <span>Laporan</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="nav-item" style="color: #ef4444;">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Overlay -->
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<!-- Main Wrapper -->
<div class="main-wrapper" id="mainWrapper">
    
    <!-- Top Header -->
    <header class="top-header">
        <div class="header-left">
            <button class="toggle-btn" id="toggleBtn" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></div>
                    <div class="user-role"><?= ucfirst($_SESSION['role']) ?></div>
                </div>
            </div>
        </div>
        <div class="saldo-box">
            <div class="saldo-label">Saldo</div>
            <div class="saldo-value"><?= rupiah($_SESSION['saldo']) ?></div>
        </div>
    </header>

    <!-- Page Content -->
    <main class="page-content">
        
        <!-- Page Title -->
        <div class="page-title">
            <h1>Paket Data Internet</h1>
            <p>Pilih paket data sesuai kebutuhan Anda</p>
        </div>

        <!-- Alert -->
        <?php if ($alert): ?>
        <div class="alert alert-<?= $alert['type'] == 'success' ? 'success' : 'error' ?>">
            <i class="fas fa-<?= $alert['type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <span class="alert-message"><?= $alert['message'] ?></span>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="" id="formKuota">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="id_produk" id="idProduk" value="">

            <!-- Input Nomor HP -->
            <div class="card">
                <div class="card-body">
                    <div class="card-title">
                        <i class="fas fa-phone-alt" style="color: #2563eb; margin-right: 8px;"></i>
                        Nomor Handphone
                    </div>
                    <div class="input-group">
                        <i class="fas fa-mobile-alt input-icon"></i>
                        <input type="tel" 
                               name="no_hp" 
                               id="noHp"
                               class="input-field" 
                               placeholder="Contoh: 08123456789"
                               maxlength="15"
                               required
                               oninput="this.value = this.value.replace(/[^0-9]/g, ''); detectProvider();">
                    </div>
                    <div class="input-hint" id="providerHint">
                        <i class="fas fa-info-circle"></i>
                        <span id="providerText">Masukkan nomor untuk mendeteksi provider</span>
                    </div>
                </div>
            </div>

            <!-- Daftar Paket Data -->
            <?php if (empty($produkByProvider)): ?>
            <div class="card">
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-wifi"></i>
                    </div>
                    <div class="empty-title">Tidak Ada Paket Tersedia</div>
                    <div class="empty-text">Paket data belum tersedia saat ini</div>
                </div>
            </div>
            <?php else: ?>
            
            <?php foreach ($produkByProvider as $provider => $listProduk): 
                $providerLower = strtolower($provider);
                $providerClass = in_array($providerLower, ['telkomsel', 'indosat', 'xl', 'tri', 'axis', 'smartfren']) 
                    ? $providerLower : 'telkomsel';
            ?>
            <div class="card provider-section" data-provider="<?= strtolower($provider) ?>">
                <div class="card-body">
                    <div class="provider-header">
                        <div class="provider-icon <?= $providerClass ?>">
                            <?= strtoupper(substr($provider, 0, 1)) ?>
                        </div>
                        <div>
                            <div class="provider-name"><?= htmlspecialchars($provider) ?></div>
                            <div class="provider-subtitle"><?= count($listProduk) ?> paket tersedia</div>
                        </div>
                    </div>

                    <div class="product-grid">
                        <?php foreach ($listProduk as $p): ?>
                        <div class="product-card" 
                             data-id="<?= $p['id'] ?>"
                             data-name="<?= htmlspecialchars($p['nama_produk'], ENT_QUOTES) ?>"
                             data-price="<?= $p['harga_jual'] ?>"
                             onclick="selectProduct(this)">
                            
                            <div class="product-content">
                                <div class="product-info">
                                    <div class="product-name"><?= htmlspecialchars($p['nama_produk']) ?></div>
                                    <div class="product-meta">
                                        <span class="product-badge">
                                            <i class="fas fa-calendar"></i>
                                            30 Hari
                                        </span>
                                        <span class="product-badge">
                                            <i class="fas fa-signal"></i>
                                            4G/LTE
                                        </span>
                                    </div>
                                </div>
                                <div class="product-price-section">
                                    <div class="product-price"><?= rupiah($p['harga_jual']) ?></div>
                                    <div class="product-quota"><?= formatBytes($p['nominal']) ?></div>
                                </div>
                            </div>
                            
                            <div class="product-radio">
                                <div class="product-radio-dot"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php endif; ?>

            <!-- Sticky Summary -->
            <div class="sticky-summary" id="stickySummary">
                <div class="summary-content">
                    <div class="summary-info">
                        <div>
                            <div class="summary-label">Produk dipilih</div>
                            <div class="summary-product" id="summaryProduct">-</div>
                        </div>
                        <div class="summary-price-section">
                            <div class="summary-price" id="summaryPrice">Rp 0</div>
                        </div>
                    </div>
                    <div class="summary-actions">
                        <button type="button" class="btn btn-secondary" onclick="resetSelection()">
                            <i class="fas fa-times"></i>
                            <span class="btn-text-full">Batal</span>
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnSubmit">
                            <i class="fas fa-wifi"></i>
                            <span>Beli Paket</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Footer -->
        <footer class="footer">
            <div class="footer-content">
                <div class="footer-text">
                    &copy; <?= date('Y') ?> PPOB Express. All rights reserved.
                </div>
                <div class="footer-links">
                    <a href="#" class="footer-link">Kebijakan Privasi</a>
                    <a href="#" class="footer-link">Syarat & Ketentuan</a>
                </div>
            </div>
        </footer>
    </main>
</div>

<script>
// ==================== SIDEBAR ====================
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const mainWrapper = document.getElementById('mainWrapper');
const stickySummary = document.getElementById('stickySummary');

function isMobile() {
    return window.innerWidth <= 768;
}

function toggleSidebar() {
    if (isMobile()) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    } else {
        sidebar.classList.toggle('closed');
        mainWrapper.classList.toggle('expanded');
        stickySummary.classList.toggle('expanded');
        
        // Save state
        const isExpanded = mainWrapper.classList.contains('expanded');
        localStorage.setItem('sidebarClosed', isExpanded ? 'true' : 'false');
    }
}

function initSidebar() {
    if (isMobile()) {
        sidebar.classList.remove('open', 'closed');
        mainWrapper.classList.remove('expanded');
        stickySummary.classList.remove('expanded');
        overlay.classList.remove('show');
    } else {
        const savedState = localStorage.getItem('sidebarClosed');
        if (savedState === 'true') {
            sidebar.classList.add('closed');
            mainWrapper.classList.add('expanded');
            stickySummary.classList.add('expanded');
        } else {
            sidebar.classList.remove('closed');
            mainWrapper.classList.remove('expanded');
            stickySummary.classList.remove('expanded');
        }
    }
}

// Init on load
document.addEventListener('DOMContentLoaded', initSidebar);

// Re-init on resize
let resizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(initSidebar, 150);
});

// Close sidebar when clicking nav item on mobile
document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', function(e) {
        if (isMobile() && !e.target.closest('.sidebar-footer')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        }
    });
});

// ==================== PRODUCT SELECTION ====================
let selectedProduct = null;

function selectProduct(card) {
    // Remove previous selection
    document.querySelectorAll('.product-card').forEach(c => {
        c.classList.remove('selected');
    });
    
    // Add selection to clicked card
    card.classList.add('selected');
    selectedProduct = {
        id: card.dataset.id,
        name: card.dataset.name,
        price: parseInt(card.dataset.price)
    };
    
    // Update form
    document.getElementById('idProduk').value = selectedProduct.id;
    
    // Update summary
    document.getElementById('summaryProduct').textContent = selectedProduct.name;
    document.getElementById('summaryPrice').textContent = formatRupiah(selectedProduct.price);
    
    // Show sticky summary
    stickySummary.classList.add('show');
    
    // Scroll to summary on mobile
    if (isMobile()) {
        setTimeout(() => {
            stickySummary.scrollIntoView({ behavior: 'smooth', block: 'end' });
        }, 100);
    }
}

function resetSelection() {
    document.querySelectorAll('.product-card').forEach(c => {
        c.classList.remove('selected');
    });
    
    selectedProduct = null;
    document.getElementById('idProduk').value = '';
    document.getElementById('summaryProduct').textContent = '-';
    document.getElementById('summaryPrice').textContent = 'Rp 0';
    stickySummary.classList.remove('show');
}

function formatRupiah(num) {
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// ==================== PROVIDER DETECTION ====================
const providerPrefixes = {
    'Telkomsel': ['0811', '0812', '0813', '0821', '0822', '0823', '0851', '0852', '0853'],
    'Indosat': ['0814', '0815', '0816', '0855', '0856', '0857', '0858'],
    'XL': ['0817', '0818', '0819', '0859', '0877', '0878'],
    'Tri': ['0895', '0896', '0897', '0898', '0899'],
    'Axis': ['0831', '0832', '0833', '0838'],
    'Smartfren': ['0881', '0882', '0883', '0884', '0885', '0886', '0887', '0888', '0889']
};

function detectProvider() {
    const noHp = document.getElementById('noHp').value;
    const hint = document.getElementById('providerHint');
    const text = document.getElementById('providerText');
    
    if (noHp.length < 4) {
        text.innerHTML = 'Masukkan nomor untuk mendeteksi provider';
        text.classList.remove('provider-detected');
        return;
    }
    
    const prefix = noHp.substring(0, 4);
    let detectedProvider = null;
    
    for (const [provider, prefixes] of Object.entries(providerPrefixes)) {
        if (prefixes.some(p => prefix.startsWith(p))) {
            detectedProvider = provider;
            break;
        }
    }
    
    if (detectedProvider) {
        text.innerHTML = `<span class="provider-detected">${detectedProvider}</span> terdeteksi. Pilih paket yang sesuai.`;
    } else {
        text.innerHTML = 'Provider tidak dikenali';
        text.classList.remove('provider-detected');
    }
}

// ==================== FORM SUBMISSION ====================
document.getElementById('formKuota').addEventListener('submit', function(e) {
    const noHp = document.getElementById('noHp').value;
    const idProduk = document.getElementById('idProduk').value;
    
    if (!noHp || noHp.length < 10) {
        e.preventDefault();
        alert('Masukkan nomor HP yang valid (minimal 10 digit)');
        return;
    }
    
    if (!idProduk) {
        e.preventDefault();
        alert('Pilih paket data terlebih dahulu');
        return;
    }
    
    // Show loading
    const btn = document.getElementById('btnSubmit');
    btn.classList.add('btn-loading');
    btn.disabled = true;
});

// ==================== KEYBOARD SHORTCUTS ====================
document.addEventListener('keydown', function(e) {
    // Escape to close sidebar on mobile or reset selection
    if (e.key === 'Escape') {
        if (isMobile() && sidebar.classList.contains('open')) {
            toggleSidebar();
        } else if (selectedProduct) {
            resetSelection();
        }
    }
});
</script>

</body>
</html>
<?php $conn->close(); ?>