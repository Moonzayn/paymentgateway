<?php
// layout.php - Main layout template
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'PPOB Express' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6353D8;
            --primary-light: #8B7FE8;
            --primary-50: #f5f3ff;
            --primary-dark: #4a3db5;
            --surface: #ffffff;
            --bg: #f0f2f5;
            --border: #e2e8f0;
            --text: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --success: #22c55e;
            --error: #ef4444;
            --sidebar-w: 260px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }

        body {
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }

        /* ══════════════════════════════════════
           SIDEBAR
           ══════════════════════════════════════ */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sidebar-w); height: 100vh;
            background: var(--surface);
            border-right: 1px solid var(--border);
            z-index: 40;
            display: flex; flex-direction: column;
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
            transform: translateX(0);
        }
        .sidebar.sidebar-hidden { transform: translateX(-100%); }

        .sidebar-header {
            padding: 0 16px;
            height: 64px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .sidebar-logo {
            width: 36px; height: 36px;
            background: var(--primary); border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 16px; flex-shrink: 0;
        }

        .sidebar-brand {
            font-size: 18px; font-weight: 700; color: var(--text);
            white-space: nowrap; overflow: hidden;
        }
        .sidebar-brand span { color: var(--primary); }

        /* Toggle button DI DALAM sidebar header */
        .sidebar-toggle {
            margin-left: auto;
            width: 32px; height: 32px;
            border: none; background: transparent;
            color: var(--text-muted); cursor: pointer;
            border-radius: 8px; font-size: 14px;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s ease; flex-shrink: 0;
        }
        .sidebar-toggle:hover {
            background: var(--primary-50);
            color: var(--primary);
        }

        .sidebar-nav {
            flex: 1; padding: 12px 8px;
            overflow-y: auto;
        }

        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 16px; color: var(--text-secondary);
            text-decoration: none; border-radius: 10px;
            margin-bottom: 2px; font-size: 14px; font-weight: 500;
            transition: all 0.18s ease; white-space: nowrap; overflow: hidden;
        }
        .nav-item:hover { background: var(--primary-50); color: var(--primary); }
        .nav-item.active {
            background: var(--primary); color: white;
        }
        .nav-item i { width: 20px; text-align: center; flex-shrink: 0; }

        .nav-label {
            font-size: 11px; font-weight: 600; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.5px;
            padding: 16px 16px 8px;
        }

        .sidebar-footer {
            padding: 12px 8px;
            border-top: 1px solid var(--border); flex-shrink: 0;
        }

        /* ══════════════════════════════════════
           MAIN WRAPPER
           ══════════════════════════════════════ */
        .main-wrapper {
            margin-left: var(--sidebar-w);
            min-height: 100vh; display: flex; flex-direction: column;
            flex: 1; min-width: 0;
            transition: margin-left 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        .main-wrapper.sidebar-hidden { margin-left: 0; }

        /* ══════════════════════════════════════
           TOP NAVBAR
           ══════════════════════════════════════ */
        .top-navbar {
            height: 64px;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 20px;
            position: sticky; top: 0; z-index: 30;
        }

        .navbar-left {
            display: flex; align-items: center; gap: 12px;
        }

        /* Toggle button DI NAVBAR (muncul saat sidebar hidden / mobile) */
        .navbar-toggle {
            width: 40px; height: 40px;
            border: none; background: transparent;
            color: var(--text-secondary); cursor: pointer;
            border-radius: 10px; font-size: 18px;
            display: none; /* Hidden by default, shown when sidebar hidden */
            align-items: center; justify-content: center;
            transition: all 0.2s ease;
        }
        .navbar-toggle:hover {
            background: var(--primary-50);
            color: var(--primary);
        }
        .navbar-toggle:active { transform: scale(0.92); }

        /* Show navbar toggle when sidebar is hidden */
        .main-wrapper.sidebar-hidden .navbar-toggle {
            display: flex;
        }

        .navbar-breadcrumb {
            display: flex; align-items: center; gap: 8px;
            font-size: 14px; color: var(--text-secondary);
        }
        .navbar-breadcrumb .current {
            font-weight: 600; color: var(--text);
        }

        .navbar-right {
            display: flex; align-items: center; gap: 12px;
        }

        .balance-chip {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 16px; background: var(--primary-50);
            border-radius: 20px; font-size: 13px; font-weight: 600;
            color: var(--primary); transition: all 0.2s ease;
        }
        .balance-chip:hover { background: #ede9fe; }
        .balance-chip .chip-icon {
            width: 20px; height: 20px; border-radius: 50%;
            background: var(--primary); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 9px;
        }

        .user-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--primary); color: white; border: none;
            font-weight: 700; cursor: pointer; font-size: 14px;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s ease;
        }
        .user-avatar:hover { background: var(--primary-dark); transform: scale(1.05); }

        /* ══════════════════════════════════════
           PAGE CONTENT
           ══════════════════════════════════════ */
        .page-content { flex: 1; padding: 24px; }

        .page-header { margin-bottom: 24px; }
        .page-header h1 {
            font-size: 22px; font-weight: 700; color: var(--text);
            display: flex; align-items: center; gap: 10px;
        }
        .page-header h1 i {
            color: var(--primary); font-size: 20px;
            width: 40px; height: 40px;
            background: var(--primary-50); border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
        }
        .page-header p {
            color: var(--text-secondary); margin-top: 4px; font-size: 14px;
            margin-left: 50px;
        }

        /* ══════════════════════════════════════
           ALERT
           ══════════════════════════════════════ */
        .alert {
            padding: 12px 16px; border-radius: 12px;
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 20px; font-size: 14px;
            animation: slideDown 0.4s ease;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* ══════════════════════════════════════
           CARDS
           ══════════════════════════════════════ */
        .card {
            background: var(--surface); border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border: 1px solid var(--border);
        }

        /* ══════════════════════════════════════
           BUTTONS
           ══════════════════════════════════════ */
        .btn-primary {
            background: var(--primary); color: white; border: none;
            padding: 12px 24px; border-radius: 10px; font-size: 14px;
            font-weight: 600; cursor: pointer; transition: all 0.2s ease;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-primary:hover { background: var(--primary-light); transform: translateY(-1px); }

        /* ══════════════════════════════════════
           OVERLAY
           ══════════════════════════════════════ */
        .overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
            z-index: 35; display: none;
        }
        .overlay.show { display: block; }

        /* ══════════════════════════════════════
           FOOTER
           ══════════════════════════════════════ */
        .main-footer {
            padding: 16px 24px; border-top: 1px solid var(--border);
            background: white;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 8px;
        }
        .footer-text { font-size: 12px; color: var(--text-muted); }
        .footer-link {
            font-size: 12px; color: var(--text-muted);
            text-decoration: none; transition: color 0.2s;
        }
        .footer-link:hover { color: var(--primary); }

        /* ══════════════════════════════════════
           ANIMATIONS
           ══════════════════════════════════════ */
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes popIn {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }

        /* ══════════════════════════════════════
           SCROLLBAR
           ══════════════════════════════════════ */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* ══════════════════════════════════════
           RESPONSIVE
           ══════════════════════════════════════ */
        @media (max-width: 767px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-wrapper { margin-left: 0 !important; }
            .page-content { padding: 16px; }

            /* Always show navbar toggle on mobile */
            .navbar-toggle { display: flex !important; }

            /* Hide sidebar toggle on mobile (use navbar toggle instead) */
            .sidebar-toggle { display: none; }

            .balance-chip span:not(.chip-icon) { display: none; }
            .balance-chip { padding: 8px 10px; }

            .navbar-breadcrumb span { display: none; }
            .page-header p { margin-left: 0; }
        }

        @media (min-width: 768px) {
            /* Desktop: show sidebar toggle always */
            .sidebar-toggle { display: flex; }
        }
    </style>
    <?php if (isset($additionalStyles)): ?>
    <style><?= $additionalStyles ?></style>
    <?php endif; ?>
</head>
<body>

<!-- Overlay (mobile) -->
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- ═══════════════════ SIDEBAR ═══════════════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><i class="fas fa-wallet"></i></div>
        <div class="sidebar-brand">PPOB<span> Express</span></div>
        <!-- Toggle button di dalam sidebar -->
        <button class="sidebar-toggle" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Tutup Sidebar">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?= ($currentPage ?? '') == 'index' ? 'active' : '' ?>"><i class="fas fa-home"></i><span>Dashboard</span></a>
        <a href="pulsa.php" class="nav-item <?= ($currentPage ?? '') == 'pulsa' ? 'active' : '' ?>"><i class="fas fa-mobile-alt"></i><span>Isi Pulsa</span></a>
        <a href="kuota.php" class="nav-item <?= ($currentPage ?? '') == 'kuota' ? 'active' : '' ?>"><i class="fas fa-wifi"></i><span>Paket Data</span></a>
        <a href="listrik.php" class="nav-item <?= ($currentPage ?? '') == 'listrik' ? 'active' : '' ?>"><i class="fas fa-bolt"></i><span>Token Listrik</span></a>
        <a href="game.php" class="nav-item <?= ($currentPage ?? '') == 'game' ? 'active' : '' ?>"><i class="fas fa-gamepad"></i><span>Top Up Game</span></a>
        <a href="transfer.php" class="nav-item <?= ($currentPage ?? '') == 'transfer' ? 'active' : '' ?>"><i class="fas fa-exchange-alt"></i><span>Transfer</span></a>
        <a href="deposit.php" class="nav-item <?= ($currentPage ?? '') == 'deposit' ? 'active' : '' ?>"><i class="fas fa-plus-circle"></i><span>Deposit</span></a>
        <a href="riwayat.php" class="nav-item <?= ($currentPage ?? '') == 'riwayat' ? 'active' : '' ?>"><i class="fas fa-history"></i><span>Riwayat</span></a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
        <div class="nav-label">Admin</div>
        <a href="kelola_user.php" class="nav-item <?= ($currentPage ?? '') == 'kelola_user' ? 'active' : '' ?>"><i class="fas fa-users"></i><span>Kelola User</span></a>
        <a href="kelola_produk.php" class="nav-item <?= ($currentPage ?? '') == 'kelola_produk' ? 'active' : '' ?>"><i class="fas fa-box"></i><span>Kelola Produk</span></a>
        <a href="laporan.php" class="nav-item <?= ($currentPage ?? '') == 'laporan' ? 'active' : '' ?>"><i class="fas fa-chart-bar"></i><span>Laporan</span></a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </div>
</aside>

<!-- ═══════════════════ MAIN WRAPPER ═══════════════════ -->
<div class="main-wrapper" id="mainWrapper">

    <!-- ── Top Navbar ── -->
    <nav class="top-navbar">
        <div class="navbar-left">
            <!-- Toggle button di NAVBAR (muncul saat sidebar hidden) -->
            <button class="navbar-toggle" id="navbarToggleBtn" onclick="toggleSidebar()" title="Buka Sidebar">
                <i class="fas fa-bars"></i>
            </button>

            <div class="navbar-breadcrumb">
                <span>
                    <i class="fas fa-home" style="font-size:12px;margin-right:4px;"></i>Home
                </span>
                <?php if (isset($pageTitle) && $pageTitle !== 'Dashboard'): ?>
                <i class="fas fa-chevron-right" style="font-size:10px;color:var(--text-muted);"></i>
                <span class="current"><?= $pageTitle ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="navbar-right">
            <div class="balance-chip" title="Saldo Anda">
                <span class="chip-icon"><i class="fas fa-wallet"></i></span>
                <span><?= rupiah($_SESSION['saldo'] ?? 0) ?></span>
            </div>
            <button class="user-avatar" title="<?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User') ?>">
                <?= strtoupper(substr($_SESSION['nama_lengkap'] ?? 'U', 0, 1)) ?>
            </button>
        </div>
    </nav>

    <!-- ── Page Content ── -->
    <main class="page-content">
        <?php if (isset($pageTitle)): ?>
        <div class="page-header">
            <h1>
                <i class="<?= $pageIcon ?? 'fas fa-circle' ?>"></i>
                <?= $pageTitle ?>
            </h1>
            <?php if (isset($pageDesc)): ?><p><?= $pageDesc ?></p><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($alert ?? false): ?>
        <div class="alert alert-<?= $alert['type'] == 'success' ? 'success' : 'error' ?>">
            <i class="fas fa-<?= $alert['type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <span><?= $alert['message'] ?></span>
        </div>
        <?php endif; ?>

<!-- ═══ PAGE SPECIFIC CONTENT STARTS HERE ═══ -->