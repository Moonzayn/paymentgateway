<?php
require_once 'config.php';
cekLogin();

// Hanya admin yang bisa mengakses
if ($_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

$conn = koneksi();

// Ambil semua kategori
$kategori = $conn->query("SELECT * FROM kategori_produk WHERE status = 'active' ORDER BY nama_kategori");

// Ambil data produk dengan filter
$search = $_GET['search'] ?? '';
$kategori_id = $_GET['kategori_id'] ?? '';
$provider = $_GET['provider'] ?? '';
$status = $_GET['status'] ?? '';

// Build query
$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(kode_produk LIKE ? OR nama_produk LIKE ? OR provider LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_fill(0, 3, $searchTerm);
    $types = 'sss';
}

if (!empty($kategori_id) && is_numeric($kategori_id)) {
    $where[] = "kategori_id = ?";
    $params[] = $kategori_id;
    $types .= 'i';
}

if (!empty($provider)) {
    $where[] = "provider = ?";
    $params[] = $provider;
    $types .= 's';
}

if (!empty($status)) {
    $where[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

$whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

$sql = "SELECT p.*, k.nama_kategori 
        FROM produk p 
        LEFT JOIN kategori_produk k ON p.kategori_id = k.id 
        $whereClause 
        ORDER BY p.kategori_id, p.provider, p.nominal";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$produk = $stmt->get_result();

// Ambil semua provider unik
$providers = $conn->query("SELECT DISTINCT provider FROM produk WHERE provider IS NOT NULL AND provider != '' ORDER BY provider");

// Tambah produk baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    // Validasi CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: kelola_produk.php");
        exit;
    }
    
    $kode_produk = trim($_POST['kode_produk'] ?? '');
    $nama_produk = trim($_POST['nama_produk'] ?? '');
    $kategori_id = intval($_POST['kategori_id'] ?? 0);
    $provider = trim($_POST['provider'] ?? '');
    $nominal = floatval($_POST['nominal'] ?? 0);
    $harga_jual = floatval($_POST['harga_jual'] ?? 0);
    $harga_modal = floatval($_POST['harga_modal'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    
    // Validasi
    if (empty($kode_produk) || empty($nama_produk) || $kategori_id == 0) {
        setAlert('error', 'Kode, Nama, dan Kategori wajib diisi!');
    } elseif ($nominal <= 0 || $harga_jual <= 0 || $harga_modal <= 0) {
        setAlert('error', 'Nominal dan harga harus lebih dari 0!');
    } elseif ($harga_jual < $harga_modal) {
        setAlert('error', 'Harga jual harus lebih besar atau sama dengan harga modal!');
    } else {
        // Cek apakah kode produk sudah ada
        $checkStmt = $conn->prepare("SELECT id FROM produk WHERE kode_produk = ?");
        $checkStmt->bind_param("s", $kode_produk);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows > 0) {
            setAlert('error', 'Kode produk sudah digunakan!');
        } else {
            // Insert produk
            $stmt = $conn->prepare("INSERT INTO produk (kode_produk, nama_produk, kategori_id, provider, nominal, harga_jual, harga_modal, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssisdddds", $kode_produk, $nama_produk, $kategori_id, $provider, $nominal, $harga_jual, $harga_modal, $status);
            
            if ($stmt->execute()) {
                setAlert('success', 'Produk berhasil ditambahkan!');
            } else {
                setAlert('error', 'Gagal menambahkan produk!');
            }
        }
    }
    header("Location: kelola_produk.php");
    exit;
}

// Update produk
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    // Validasi CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: kelola_produk.php");
        exit;
    }
    
    $produk_id = intval($_POST['produk_id'] ?? 0);
    $kode_produk = trim($_POST['kode_produk'] ?? '');
    $nama_produk = trim($_POST['nama_produk'] ?? '');
    $kategori_id = intval($_POST['kategori_id'] ?? 0);
    $provider = trim($_POST['provider'] ?? '');
    $nominal = floatval($_POST['nominal'] ?? 0);
    $harga_jual = floatval($_POST['harga_jual'] ?? 0);
    $harga_modal = floatval($_POST['harga_modal'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    
    if ($produk_id == 0) {
        setAlert('error', 'Produk ID tidak valid!');
    } elseif (empty($kode_produk) || empty($nama_produk) || $kategori_id == 0) {
        setAlert('error', 'Kode, Nama, dan Kategori wajib diisi!');
    } elseif ($nominal <= 0 || $harga_jual <= 0 || $harga_modal <= 0) {
        setAlert('error', 'Nominal dan harga harus lebih dari 0!');
    } elseif ($harga_jual < $harga_modal) {
        setAlert('error', 'Harga jual harus lebih besar atau sama dengan harga modal!');
    } else {
        // Cek apakah kode produk sudah digunakan oleh produk lain
        $checkStmt = $conn->prepare("SELECT id FROM produk WHERE kode_produk = ? AND id != ?");
        $checkStmt->bind_param("si", $kode_produk, $produk_id);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows > 0) {
            setAlert('error', 'Kode produk sudah digunakan!');
        } else {
            // Update produk
            $stmt = $conn->prepare("UPDATE produk SET kode_produk = ?, nama_produk = ?, kategori_id = ?, provider = ?, nominal = ?, harga_jual = ?, harga_modal = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssisddddss", $kode_produk, $nama_produk, $kategori_id, $provider, $nominal, $harga_jual, $harga_modal, $status, $produk_id);
            
            if ($stmt->execute()) {
                setAlert('success', 'Produk berhasil diperbarui!');
            } else {
                setAlert('error', 'Gagal memperbarui produk!');
            }
        }
    }
    header("Location: kelola_produk.php");
    exit;
}

// Delete produk
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    // Validasi CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: kelola_produk.php");
        exit;
    }
    
    $produk_id = intval($_POST['produk_id'] ?? 0);
    
    if ($produk_id == 0) {
        setAlert('error', 'Produk ID tidak valid!');
    } else {
        // Cek apakah produk ada di transaksi
        $checkStmt = $conn->prepare("SELECT COUNT(*) as total FROM transaksi WHERE produk_id = ?");
        $checkStmt->bind_param("i", $produk_id);
        $checkStmt->execute();
        $result = $checkStmt->get_result()->fetch_assoc();
        
        if ($result['total'] > 0) {
            setAlert('error', 'Tidak dapat menghapus produk yang sudah ada transaksi!');
        } else {
            // Delete produk
            $stmt = $conn->prepare("DELETE FROM produk WHERE id = ?");
            $stmt->bind_param("i", $produk_id);
            
            if ($stmt->execute()) {
                setAlert('success', 'Produk berhasil dihapus!');
            } else {
                setAlert('error', 'Gagal menghapus produk!');
            }
        }
    }
    header("Location: kelola_produk.php");
    exit;
}

// Update status produk
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    // Validasi CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: kelola_produk.php");
        exit;
    }
    
    $produk_id = intval($_POST['produk_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    
    if ($produk_id == 0) {
        setAlert('error', 'Produk ID tidak valid!');
    } else {
        // Update status
        $stmt = $conn->prepare("UPDATE produk SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $produk_id);
        
        if ($stmt->execute()) {
            setAlert('success', 'Status produk berhasil diperbarui!');
        } else {
            setAlert('error', 'Gagal memperbarui status produk!');
        }
    }
    header("Location: kelola_produk.php");
    exit;
}

// Mass update harga
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'mass_update') {
    // Validasi CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: kelola_produk.php");
        exit;
    }
    
    $kategori_id = intval($_POST['kategori_id'] ?? 0);
    $provider = trim($_POST['provider'] ?? '');
    $increase_type = $_POST['increase_type'] ?? 'percent';
    $increase_value = floatval($_POST['increase_value'] ?? 0);
    $update_harga_jual = isset($_POST['update_harga_jual']);
    $update_harga_modal = isset($_POST['update_harga_modal']);
    
    if ($increase_value <= 0) {
        setAlert('error', 'Nilai kenaikan harus lebih dari 0!');
    } elseif (!$update_harga_jual && !$update_harga_modal) {
        setAlert('error', 'Pilih setidaknya satu harga untuk diperbarui!');
    } else {
        // Build where clause for mass update
        $where = [];
        $params = [];
        $types = '';
        
        if ($kategori_id > 0) {
            $where[] = "kategori_id = ?";
            $params[] = $kategori_id;
            $types .= 'i';
        }
        
        if (!empty($provider)) {
            $where[] = "provider = ?";
            $params[] = $provider;
            $types .= 's';
        }
        
        $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
        
        // Get all affected products
        $getStmt = $conn->prepare("SELECT id, harga_jual, harga_modal FROM produk $whereClause");
        if (!empty($params)) {
            $getStmt->bind_param($types, ...$params);
        }
        $getStmt->execute();
        $affectedProducts = $getStmt->get_result();
        
        $updatedCount = 0;
        
        // Update each product
        while ($product = $affectedProducts->fetch_assoc()) {
            $new_harga_jual = $update_harga_jual ? calculateNewPrice($product['harga_jual'], $increase_type, $increase_value) : $product['harga_jual'];
            $new_harga_modal = $update_harga_modal ? calculateNewPrice($product['harga_modal'], $increase_type, $increase_value) : $product['harga_modal'];
            
            // Ensure harga_jual >= harga_modal
            if ($new_harga_jual < $new_harga_modal) {
                $new_harga_jual = $new_harga_modal;
            }
            
            $updateStmt = $conn->prepare("UPDATE produk SET harga_jual = ?, harga_modal = ? WHERE id = ?");
            $updateStmt->bind_param("ddi", $new_harga_jual, $new_harga_modal, $product['id']);
            if ($updateStmt->execute()) {
                $updatedCount++;
            }
        }
        
        if ($updatedCount > 0) {
            setAlert('success', "Berhasil memperbarui {$updatedCount} produk!");
        } else {
            setAlert('info', 'Tidak ada produk yang diperbarui.');
        }
    }
    header("Location: kelola_produk.php");
    exit;
}

function calculateNewPrice($oldPrice, $type, $value) {
    if ($type == 'percent') {
        return $oldPrice * (1 + ($value / 100));
    } else {
        return $oldPrice + $value;
    }
}

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - PPOB Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom CSS konsisten dengan tema */
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #3b82f6;
            --light-blue: #eff6ff;
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
        
        /* Toggle Button Animation */
        .toggle-btn {
            transition: transform 0.2s ease, background-color 0.2s ease;
        }
        
        .toggle-btn:active {
            transform: scale(0.95);
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
        
        .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-weight: 500;
        }
        
        .badge-active {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .badge-inactive {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        .table-header {
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .table-row {
            transition: background-color 0.2s ease;
        }
        
        .table-row:hover {
            background-color: #f9fafb;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 0.75rem;
            max-width: 600px;
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .input-field {
            transition: all 0.2s ease;
        }
        
        .input-field:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-primary {
            background-color: var(--primary-blue);
            color: white;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-danger {
            background-color: #ef4444;
            color: white;
            transition: all 0.2s ease;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
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
        
        .sticky-header {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .tab-button {
            transition: all 0.2s ease;
            border-bottom: 2px solid transparent;
        }
        
        .tab-button.active {
            color: var(--primary-blue);
            border-bottom-color: var(--primary-blue);
            font-weight: 600;
        }
        
        .tab-button:hover:not(.active) {
            color: var(--primary-blue);
            border-bottom-color: #cbd5e1;
        }
        
        .category-pulsa {
            border-left: 4px solid #6366f1;
        }
        
        .category-kuota {
            border-left: 4px solid #10b981;
        }
        
        .category-listrik {
            border-left: 4px solid #f59e0b;
        }
        
        .profit-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 500;
        }
        
        .profit-positive {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .profit-negative {
            background-color: #fee2e2;
            color: #991b1b;
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
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <div class="pt-4 mt-4 border-t border-gray-100">
                    <p class="px-4 text-xs text-gray-500 uppercase tracking-wider mb-2 font-semibold">Admin Menu</p>
                    <a href="kelola_user.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
                        <i class="fas fa-users w-5"></i>
                        <span>Kelola User</span>
                    </a>
                    <a href="kelola_produk.php" class="menu-item active flex items-center gap-3 px-4 py-3 text-gray-700">
                        <i class="fas fa-box w-5"></i>
                        <span>Kelola Produk</span>
                    </a>
                    <a href="laporan.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
                        <i class="fas fa-chart-bar w-5"></i>
                        <span>Laporan</span>
                    </a>
                </div>
                <?php endif; ?>
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
            <header class="bg-white shadow-sm sticky top-0 z-10 sticky-header">
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()" class="toggle-btn text-gray-600 hover:text-blue-600 hover:bg-gray-100 p-2 rounded-lg" title="Toggle Sidebar">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">Kelola Produk</h2>
                            <p class="text-sm text-gray-500">Kelola produk pulsa, kuota, dan listrik</p>
                        </div>
                    </div>
                    <!-- <div class="flex gap-2">
                        <button onclick="openModal('massUpdateModal')" class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 transition flex items-center gap-2">
                            <i class="fas fa-percentage"></i>
                            Update Massal
                        </button>
                        <button onclick="openModal('addProductModal')" class="btn-primary px-4 py-2 rounded-lg font-semibold flex items-center gap-2">
                            <i class="fas fa-plus"></i>
                            Tambah Produk
                        </button>
                    </div> -->
                </div>
            </header>
            
            <div class="p-4 md:p-6 max-w-7xl mx-auto">
                <?php if ($alert): ?>
                <div class="alert mb-4 p-4 rounded-lg flex items-center gap-3 <?= $alert['type'] == 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
                    <i class="fas <?= $alert['type'] == 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> text-lg"></i>
                    <span class="font-medium"><?= $alert['message'] ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Filter Section -->
                <div class="card p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter Produk</h3>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cari Produk</label>
                            <input type="text" 
                                   name="search" 
                                   value="<?= htmlspecialchars($search) ?>"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                   placeholder="Kode, Nama, Provider">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                            <select name="kategori_id" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="">Semua Kategori</option>
                                <?php while ($kat = $kategori->fetch_assoc()): ?>
                                <option value="<?= $kat['id'] ?>" <?= $kategori_id == $kat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kat['nama_kategori']) ?>
                                </option>
                                <?php endwhile; ?>
                                <?php $kategori->data_seek(0); // Reset pointer ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
                            <select name="provider" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="">Semua Provider</option>
                                <?php while ($prov = $providers->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($prov['provider']) ?>" <?= $provider == $prov['provider'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prov['provider']) ?>
                                </option>
                                <?php endwhile; ?>
                                <?php $providers->data_seek(0); // Reset pointer ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="">Semua Status</option>
                                <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end gap-2">
                            <button type="submit" class="btn-primary flex-1 py-2 px-4 rounded-lg font-medium flex items-center justify-center gap-2">
                                <i class="fas fa-filter"></i>
                                Filter
                            </button>
                            <a href="kelola_produk.php" class="py-2 px-4 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Stats Summary -->
                <?php
                // Hitung statistik
                $totalProduk = $conn->query("SELECT COUNT(*) as total FROM produk")->fetch_assoc()['total'];
                $activeProduk = $conn->query("SELECT COUNT(*) as total FROM produk WHERE status = 'active'")->fetch_assoc()['total'];
                $totalPulsa = $conn->query("SELECT COUNT(*) as total FROM produk WHERE kategori_id = 1")->fetch_assoc()['total'];
                $totalKuota = $conn->query("SELECT COUNT(*) as total FROM produk WHERE kategori_id = 2")->fetch_assoc()['total'];
                $totalListrik = $conn->query("SELECT COUNT(*) as total FROM produk WHERE kategori_id = 3")->fetch_assoc()['total'];
                ?>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                    <div class="card p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Produk</p>
                                <p class="text-2xl font-bold text-gray-900"><?= number_format($totalProduk) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-blue-50">
                                <i class="fas fa-box text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Produk Aktif</p>
                                <p class="text-2xl font-bold text-green-600"><?= number_format($activeProduk) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-green-50">
                                <i class="fas fa-check-circle text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-4 category-pulsa">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Produk Pulsa</p>
                                <p class="text-2xl font-bold text-indigo-600"><?= number_format($totalPulsa) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-indigo-50">
                                <i class="fas fa-mobile-alt text-indigo-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-4 category-kuota">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Produk Kuota</p>
                                <p class="text-2xl font-bold text-green-600"><?= number_format($totalKuota) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-green-50">
                                <i class="fas fa-wifi text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-4 category-listrik">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Produk Listrik</p>
                                <p class="text-2xl font-bold text-yellow-600"><?= number_format($totalListrik) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-yellow-50">
                                <i class="fas fa-bolt text-yellow-600"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Products Table -->
                <div class="card overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">
                                Daftar Produk
                                <span class="text-sm font-normal text-gray-500">(<?= $produk->num_rows ?> produk)</span>
                            </h3>
                            <div class="flex gap-2">
                                <button onclick="openModal('massUpdateModal')" class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 transition flex items-center gap-2">
                                    <i class="fas fa-percentage"></i>
                                    Update Massal
                                </button>
                                <button onclick="openModal('addProductModal')" class="btn-primary px-4 py-2 rounded-lg font-medium flex items-center gap-2">
                                    <i class="fas fa-plus"></i>
                                    Tambah Produk
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($produk->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="table-header">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Produk</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Kategori</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Provider</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Harga & Profit</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($p = $produk->fetch_assoc()): 
                                    $profit = $p['harga_jual'] - $p['harga_modal'];
                                    $profit_margin = $p['harga_modal'] > 0 ? ($profit / $p['harga_modal']) * 100 : 0;
                                    $category_class = '';
                                    if ($p['kategori_id'] == 1) $category_class = 'category-pulsa';
                                    if ($p['kategori_id'] == 2) $category_class = 'category-kuota';
                                    if ($p['kategori_id'] == 3) $category_class = 'category-listrik';
                                ?>
                                <tr class="table-row <?= $category_class ?>">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#<?= $p['id'] ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full <?= $category_class == 'category-pulsa' ? 'bg-indigo-100' : ($category_class == 'category-kuota' ? 'bg-green-100' : 'bg-yellow-100') ?> flex items-center justify-center">
                                                    <i class="fas <?= $category_class == 'category-pulsa' ? 'fa-mobile-alt text-indigo-600' : ($category_class == 'category-kuota' ? 'fa-wifi text-green-600' : 'fa-bolt text-yellow-600') ?>"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($p['nama_produk']) ?></div>
                                                <div class="text-sm text-gray-500">Kode: <?= htmlspecialchars($p['kode_produk']) ?></div>
                                                <div class="text-sm text-gray-500">Nominal: <?= rupiah($p['nominal']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($p['nama_kategori'] ?? '-') ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($p['provider']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="space-y-1">
                                            <div class="flex justify-between items-center">
                                                <span class="text-xs text-gray-500">Modal:</span>
                                                <span class="text-sm font-medium"><?= rupiah($p['harga_modal']) ?></span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-xs text-gray-500">Jual:</span>
                                                <span class="text-sm font-semibold text-blue-600"><?= rupiah($p['harga_jual']) ?></span>
                                            </div>
                                            <div class="flex justify-between items-center pt-1 border-t border-gray-100">
                                                <span class="text-xs text-gray-500">Profit:</span>
                                                <span class="profit-badge <?= $profit >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                    <?= $profit >= 0 ? '+' : '' ?><?= rupiah($profit) ?>
                                                    <span class="text-xs">(<?= number_format($profit_margin, 1) ?>%)</span>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <form method="POST" action="" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="produk_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $p['status'] == 'active' ? 'inactive' : 'active' ?>">
                                            <button type="submit"
                                                    class="badge badge-<?= $p['status'] ?> hover:opacity-80 transition">
                                                <?= ucfirst($p['status']) ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex gap-2">
                                            <button onclick="openEditModal(
                                                <?= $p['id'] ?>, 
                                                '<?= htmlspecialchars($p['kode_produk']) ?>', 
                                                '<?= htmlspecialchars($p['nama_produk']) ?>', 
                                                <?= $p['kategori_id'] ?>, 
                                                '<?= htmlspecialchars($p['provider']) ?>', 
                                                <?= $p['nominal'] ?>, 
                                                <?= $p['harga_jual'] ?>, 
                                                <?= $p['harga_modal'] ?>, 
                                                '<?= $p['status'] ?>'
                                            )" 
                                                    class="text-blue-600 hover:text-blue-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="openDeleteModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nama_produk']) ?>')" 
                                                    class="text-red-600 hover:text-red-900" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-12 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-box text-gray-400 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada produk</h3>
                        <p class="text-gray-500 mb-4">Tidak ada produk yang sesuai dengan filter yang dipilih</p>
                        <button onclick="openModal('addProductModal')" class="btn-primary px-4 py-2 rounded-lg font-medium flex items-center gap-2 mx-auto">
                            <i class="fas fa-plus"></i>
                            Tambah Produk Pertama
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal Tambah Produk -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Tambah Produk Baru</h3>
            </div>
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="add">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kode Produk *</label>
                            <input type="text" name="kode_produk" required
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                   placeholder="TSEL5">
                            <p class="text-xs text-gray-500 mt-1">Kode unik untuk produk</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk *</label>
                            <input type="text" name="nama_produk" required
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                   placeholder="Pulsa Telkomsel 5K">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kategori *</label>
                            <select name="kategori_id" required class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="">Pilih Kategori</option>
                                <?php while ($kat = $kategori->fetch_assoc()): ?>
                                <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                                <?php endwhile; ?>
                                <?php $kategori->data_seek(0); ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
                            <input type="text" name="provider"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                   placeholder="Telkomsel, XL, PLN, dll">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nominal *</label>
                            <input type="number" name="nominal" required min="1" step="1"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                   placeholder="5000">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Harga Modal *</label>
                            <input type="number" name="harga_modal" required min="1" step="1"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                   placeholder="5500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Harga Jual *</label>
                            <input type="number" name="harga_jual" required min="1" step="1"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                   placeholder="6500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="pt-6">
                            <div id="profitPreview" class="text-sm p-2 bg-gray-50 rounded">
                                <p>Profit: <span id="profitValue" class="font-semibold">Rp 0</span></p>
                                <p>Margin: <span id="marginValue" class="font-semibold">0%</span></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 pt-6">
                    <button type="button" onclick="closeModal('addProductModal')" 
                            class="flex-1 py-3 px-4 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                        Batal
                    </button>
                    <button type="submit" 
                            class="btn-primary flex-1 py-3 px-4 rounded-lg font-semibold">
                        <i class="fas fa-save mr-2"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Edit Produk -->
    <div id="editProductModal" class="modal">
        <div class="modal-content">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Edit Produk</h3>
            </div>
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="produk_id" id="edit_produk_id">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kode Produk *</label>
                            <input type="text" name="kode_produk" id="edit_kode_produk" required
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk *</label>
                            <input type="text" name="nama_produk" id="edit_nama_produk" required
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kategori *</label>
                            <select name="kategori_id" id="edit_kategori_id" required class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <?php while ($kat = $kategori->fetch_assoc()): ?>
                                <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                                <?php endwhile; ?>
                                <?php $kategori->data_seek(0); ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
                            <input type="text" name="provider" id="edit_provider"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nominal *</label>
                            <input type="number" name="nominal" id="edit_nominal" required min="1" step="1"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Harga Modal *</label>
                            <input type="number" name="harga_modal" id="edit_harga_modal" required min="1" step="1"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Harga Jual *</label>
                            <input type="number" name="harga_jual" id="edit_harga_jual" required min="1" step="1"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" id="edit_status" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="pt-6">
                            <div id="editProfitPreview" class="text-sm p-2 bg-gray-50 rounded">
                                <p>Profit: <span id="editProfitValue" class="font-semibold">Rp 0</span></p>
                                <p>Margin: <span id="editMarginValue" class="font-semibold">0%</span></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 pt-6">
                    <button type="button" onclick="closeModal('editProductModal')" 
                            class="flex-1 py-3 px-4 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                        Batal
                    </button>
                    <button type="submit" 
                            class="btn-primary flex-1 py-3 px-4 rounded-lg font-semibold">
                        <i class="fas fa-save mr-2"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Mass Update -->
    <div id="massUpdateModal" class="modal">
        <div class="modal-content">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Update Harga Massal</h3>
            </div>
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="mass_update">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                            <select name="kategori_id" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="">Semua Kategori</option>
                                <?php while ($kat = $kategori->fetch_assoc()): ?>
                                <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                                <?php endwhile; ?>
                                <?php $kategori->data_seek(0); ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
                            <select name="provider" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="">Semua Provider</option>
                                <?php while ($prov = $providers->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($prov['provider']) ?>"><?= htmlspecialchars($prov['provider']) ?></option>
                                <?php endwhile; ?>
                                <?php $providers->data_seek(0); ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Kenaikan</label>
                            <select name="increase_type" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="percent">Persentase (%)</option>
                                <option value="fixed">Jumlah Tetap (Rp)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nilai Kenaikan *</label>
                            <input type="number" name="increase_value" required min="1" step="0.01"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                   placeholder="10">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Update Harga</label>
                        <div class="space-y-2">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="update_harga_jual" checked
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2">Harga Jual</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="update_harga_modal"
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2">Harga Modal</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <p class="text-sm text-yellow-800">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Perhatian: Update massal akan mempengaruhi semua produk yang sesuai dengan filter di atas.
                        </p>
                    </div>
                </div>
                <div class="flex gap-3 pt-6">
                    <button type="button" onclick="closeModal('massUpdateModal')" 
                            class="flex-1 py-3 px-4 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                        Batal
                    </button>
                    <button type="submit" 
                            class="btn-primary flex-1 py-3 px-4 rounded-lg font-semibold">
                        <i class="fas fa-sync-alt mr-2"></i> Update Massal
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Delete Produk -->
    <div id="deleteProductModal" class="modal">
        <div class="modal-content">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Hapus Produk</h3>
            </div>
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="produk_id" id="delete_produk_id">
                <div class="space-y-4">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <p class="text-gray-700 mb-2">Anda yakin ingin menghapus produk <span id="delete_product_name" class="font-semibold"></span>?</p>
                        <p class="text-sm text-gray-500">
                            Produk yang sudah ada transaksi tidak dapat dihapus. 
                            Sebaiknya ubah status menjadi inactive.
                        </p>
                    </div>
                </div>
                <div class="flex gap-3 pt-6">
                    <button type="button" onclick="closeModal('deleteProductModal')" 
                            class="flex-1 py-3 px-4 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                        Batal
                    </button>
                    <button type="submit" 
                            class="btn-danger flex-1 py-3 px-4 rounded-lg font-semibold">
                        <i class="fas fa-trash mr-2"></i> Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Smooth Sidebar Toggle with localStorage
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const isHidden = sidebar.classList.contains('hidden');
            
            if (isHidden) {
                sidebar.classList.remove('hidden');
                overlay.classList.remove('hidden');
                localStorage.setItem('sidebar_visible', 'true');
            } else {
                sidebar.classList.add('hidden');
                overlay.classList.add('hidden');
                localStorage.setItem('sidebar_visible', 'false');
            }
        }
        
        // Initialize sidebar state
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const savedState = localStorage.getItem('sidebar_visible');
            const isMobile = window.innerWidth < 768;
            
            if (savedState === 'false' || (isMobile && savedState !== 'true')) {
                sidebar.classList.add('hidden');
                overlay.classList.add('hidden');
            } else {
                sidebar.classList.remove('hidden');
                overlay.classList.remove('hidden');
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('hidden');
                overlay.classList.add('hidden');
            }
        });
        
        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        }
        
        // Edit Produk Modal
        function openEditModal(id, kode, nama, kategoriId, provider, nominal, hargaJual, hargaModal, status) {
            document.getElementById('edit_produk_id').value = id;
            document.getElementById('edit_kode_produk').value = kode;
            document.getElementById('edit_nama_produk').value = nama;
            document.getElementById('edit_kategori_id').value = kategoriId;
            document.getElementById('edit_provider').value = provider;
            document.getElementById('edit_nominal').value = nominal;
            document.getElementById('edit_harga_jual').value = hargaJual;
            document.getElementById('edit_harga_modal').value = hargaModal;
            document.getElementById('edit_status').value = status;
            
            // Update profit preview
            updateEditProfitPreview();
            
            openModal('editProductModal');
        }
        
        // Delete Produk Modal
        function openDeleteModal(id, nama) {
            document.getElementById('delete_produk_id').value = id;
            document.getElementById('delete_product_name').textContent = nama;
            openModal('deleteProductModal');
        }
        
        // Format Rupiah
        function formatRupiah(num) {
            return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Calculate profit and update preview
        function updateProfitPreview() {
            const hargaModal = parseFloat(document.querySelector('input[name="harga_modal"]').value) || 0;
            const hargaJual = parseFloat(document.querySelector('input[name="harga_jual"]').value) || 0;
            const profit = hargaJual - hargaModal;
            const margin = hargaModal > 0 ? (profit / hargaModal) * 100 : 0;
            
            document.getElementById('profitValue').textContent = formatRupiah(profit);
            document.getElementById('marginValue').textContent = margin.toFixed(1) + '%';
            
            // Color code
            const profitPreview = document.getElementById('profitPreview');
            const profitValue = document.getElementById('profitValue');
            const marginValue = document.getElementById('marginValue');
            
            if (profit >= 0) {
                profitPreview.classList.remove('bg-red-50', 'text-red-800');
                profitPreview.classList.add('bg-green-50', 'text-green-800');
                profitValue.classList.remove('text-red-600');
                profitValue.classList.add('text-green-600');
                marginValue.classList.remove('text-red-600');
                marginValue.classList.add('text-green-600');
            } else {
                profitPreview.classList.remove('bg-green-50', 'text-green-800');
                profitPreview.classList.add('bg-red-50', 'text-red-800');
                profitValue.classList.remove('text-green-600');
                profitValue.classList.add('text-red-600');
                marginValue.classList.remove('text-green-600');
                marginValue.classList.add('text-red-600');
            }
        }
        
        function updateEditProfitPreview() {
            const hargaModal = parseFloat(document.getElementById('edit_harga_modal').value) || 0;
            const hargaJual = parseFloat(document.getElementById('edit_harga_jual').value) || 0;
            const profit = hargaJual - hargaModal;
            const margin = hargaModal > 0 ? (profit / hargaModal) * 100 : 0;
            
            document.getElementById('editProfitValue').textContent = formatRupiah(profit);
            document.getElementById('editMarginValue').textContent = margin.toFixed(1) + '%';
            
            // Color code
            const profitPreview = document.getElementById('editProfitPreview');
            const profitValue = document.getElementById('editProfitValue');
            const marginValue = document.getElementById('editMarginValue');
            
            if (profit >= 0) {
                profitPreview.classList.remove('bg-red-50', 'text-red-800');
                profitPreview.classList.add('bg-green-50', 'text-green-800');
                profitValue.classList.remove('text-red-600');
                profitValue.classList.add('text-green-600');
                marginValue.classList.remove('text-red-600');
                marginValue.classList.add('text-green-600');
            } else {
                profitPreview.classList.remove('bg-green-50', 'text-green-800');
                profitPreview.classList.add('bg-red-50', 'text-red-800');
                profitValue.classList.remove('text-green-600');
                profitValue.classList.add('text-red-600');
                marginValue.classList.remove('text-green-600');
                marginValue.classList.add('text-red-600');
            }
        }
        
        // Add event listeners for profit preview
        document.addEventListener('DOMContentLoaded', function() {
            // Add product modal
            const hargaModalInput = document.querySelector('input[name="harga_modal"]');
            const hargaJualInput = document.querySelector('input[name="harga_jual"]');
            
            if (hargaModalInput && hargaJualInput) {
                hargaModalInput.addEventListener('input', updateProfitPreview);
                hargaJualInput.addEventListener('input', updateProfitPreview);
                updateProfitPreview(); // Initial calculation
            }
            
            // Edit product modal (will be added when modal opens)
            const editHargaModalInput = document.getElementById('edit_harga_modal');
            const editHargaJualInput = document.getElementById('edit_harga_jual');
            
            if (editHargaModalInput && editHargaJualInput) {
                editHargaModalInput.addEventListener('input', updateEditProfitPreview);
                editHargaJualInput.addEventListener('input', updateEditProfitPreview);
            }
        });
        
        // Form validation for add/edit
        const forms = document.querySelectorAll('form[action*="kelola_produk"]');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const hargaModal = parseFloat(this.querySelector('input[name="harga_modal"]')?.value) || 0;
                const hargaJual = parseFloat(this.querySelector('input[name="harga_jual"]')?.value) || 0;
                
                if (hargaJual < hargaModal) {
                    e.preventDefault();
                    alert('Harga jual harus lebih besar atau sama dengan harga modal!');
                }
            });
        });
        
        // Auto-format number inputs
        document.addEventListener('input', function(e) {
            if (e.target.name === 'nominal' || e.target.name === 'harga_modal' || e.target.name === 'harga_jual') {
                const value = e.target.value.replace(/[^0-9]/g, '');
                e.target.value = value;
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
    </script>
</body>
</html>
<?php 
$conn->close();
?>