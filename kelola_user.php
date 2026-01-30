<?php
require_once 'config.php';
cekLogin();

// Hanya admin yang bisa mengakses
if ($_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

$conn = koneksi();

// Fungsi untuk mendapatkan semua user
function getAllUsers($conn, $search = '', $role = '', $status = '') {
    $where = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $where[] = "(username LIKE ? OR nama_lengkap LIKE ? OR email LIKE ? OR no_hp LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_fill(0, 4, $searchTerm);
        $types = 'ssss';
    }
    
    if (!empty($role)) {
        $where[] = "role = ?";
        $params[] = $role;
        $types .= 's';
    }
    
    if (!empty($status)) {
        $where[] = "status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "SELECT * FROM users $whereClause ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}

// Tambah user baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $username = trim($_POST['username'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'member';
    $status = $_POST['status'] ?? 'active';
    $saldo = floatval($_POST['saldo'] ?? 0);
    
    // Validasi
    if (empty($username) || empty($nama_lengkap) || empty($email) || empty($password)) {
        setAlert('error', 'Semua field wajib diisi!');
    } elseif (strlen($password) < 6) {
        setAlert('error', 'Password minimal 6 karakter!');
    } elseif ($saldo < 0) {
        setAlert('error', 'Saldo tidak boleh negatif!');
    } else {
        // Cek apakah username sudah ada
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows > 0) {
            setAlert('error', 'Username sudah digunakan!');
        } else {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, no_hp, saldo, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssdss", $username, $hashedPassword, $nama_lengkap, $email, $no_hp, $saldo, $role, $status);
            
            if ($stmt->execute()) {
                setAlert('success', 'User berhasil ditambahkan!');
            } else {
                setAlert('error', 'Gagal menambahkan user!');
            }
        }
    }
    header("Location: kelola_user.php");
    exit;
}

// Update user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $role = $_POST['role'] ?? 'member';
    $status = $_POST['status'] ?? 'active';
    $saldo = floatval($_POST['saldo'] ?? 0);
    
    if ($user_id == 0) {
        setAlert('error', 'User ID tidak valid!');
    } else {
        // Update user
        $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, no_hp = ?, saldo = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssdssi", $nama_lengkap, $email, $no_hp, $saldo, $role, $status, $user_id);
        
        if ($stmt->execute()) {
            setAlert('success', 'User berhasil diperbarui!');
        } else {
            setAlert('error', 'Gagal memperbarui user!');
        }
    }
    header("Location: kelola_user.php");
    exit;
}

// Update saldo user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_saldo') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $saldo_tipe = $_POST['saldo_tipe'] ?? 'tambah';
    $jumlah = floatval($_POST['jumlah'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');
    
    if ($user_id == 0 || $jumlah <= 0) {
        setAlert('error', 'Data tidak valid!');
    } else {
        // Get current saldo
        $stmt = $conn->prepare("SELECT saldo, nama_lengkap FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $saldo_sebelum = $user['saldo'];
            
            if ($saldo_tipe == 'tambah') {
                $saldo_sesudah = $saldo_sebelum + $jumlah;
            } else {
                $saldo_sesudah = $saldo_sebelum - $jumlah;
                if ($saldo_sesudah < 0) {
                    setAlert('error', 'Saldo tidak mencukupi!');
                    header("Location: kelola_user.php");
                    exit;
                }
            }
            
            // Update saldo
            $updateStmt = $conn->prepare("UPDATE users SET saldo = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->bind_param("di", $saldo_sesudah, $user_id);
            
            if ($updateStmt->execute()) {
                // Catat transaksi admin
                $invoice = 'ADM' . date('YmdHis') . rand(100, 999);
                $keterangan_transaksi = $keterangan ?: ($saldo_tipe == 'tambah' ? "Penambahan saldo oleh admin" : "Pengurangan saldo oleh admin");
                
                $transaksiStmt = $conn->prepare("INSERT INTO transaksi (user_id, no_invoice, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan) VALUES (?, ?, 'admin', ?, ?, ?, 0, ?, ?, ?, 'success', ?)");
                $transaksiStmt->bind_param("isddddds", $user_id, $invoice, $user['nama_lengkap'], $jumlah, $jumlah, $jumlah, $saldo_sebelum, $saldo_sesudah, $keterangan_transaksi);
                $transaksiStmt->execute();
                
                setAlert('success', 'Saldo berhasil diperbarui!');
            } else {
                setAlert('error', 'Gagal memperbarui saldo!');
            }
        } else {
            setAlert('error', 'User tidak ditemukan!');
        }
    }
    header("Location: kelola_user.php");
    exit;
}

// Delete user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id == $_SESSION['user_id']) {
        setAlert('error', 'Tidak dapat menghapus akun sendiri!');
    } elseif ($user_id == 0) {
        setAlert('error', 'User ID tidak valid!');
    } else {
        // Cek apakah user ada
        $checkStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $checkStmt->bind_param("i", $user_id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Tidak boleh hapus admin lain
            if ($user['role'] == 'admin') {
                setAlert('error', 'Tidak dapat menghapus user admin!');
            } else {
                // Delete user
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    setAlert('success', 'User berhasil dihapus!');
                } else {
                    setAlert('error', 'Gagal menghapus user!');
                }
            }
        } else {
            setAlert('error', 'User tidak ditemukan!');
        }
    }
    header("Location: kelola_user.php");
    exit;
}

// Reset password user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reset_password') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $new_password = $_POST['new_password'] ?? '';
    
    if ($user_id == 0) {
        setAlert('error', 'User ID tidak valid!');
    } elseif (strlen($new_password) < 6) {
        setAlert('error', 'Password baru minimal 6 karakter!');
    } else {
        // Hash password baru
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $user_id);
        
        if ($stmt->execute()) {
            setAlert('success', 'Password berhasil direset!');
        } else {
            setAlert('error', 'Gagal reset password!');
        }
    }
    header("Location: kelola_user.php");
    exit;
}

// Ambil parameter filter
$search = $_GET['search'] ?? '';
$filter_role = $_GET['role'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Ambil data users dengan filter
$users = getAllUsers($conn, $search, $filter_role, $filter_status);

$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - PPOB Express</title>
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
        
        .badge-admin {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        
        .badge-member {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
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
        
        .badge-suspended {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
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
            max-width: 500px;
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
                    <a href="kelola_user.php" class="menu-item active flex items-center gap-3 px-4 py-3 text-gray-700">
                        <i class="fas fa-users w-5"></i>
                        <span>Kelola User</span>
                    </a>
                    <a href="kelola_produk.php" class="menu-item flex items-center gap-3 px-4 py-3 text-gray-700">
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
                        <button onclick="toggleSidebar()" class="md:hidden text-gray-600 hover:text-blue-600">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">Kelola User</h2>
                            <p class="text-sm text-gray-500">Kelola data member dan admin</p>
                        </div>
                    </div>
                    <button onclick="openModal('addUserModal')" class="btn-primary px-4 py-2 rounded-lg font-semibold flex items-center gap-2">
                        <i class="fas fa-user-plus"></i>
                        Tambah User
                    </button>
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
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter User</h3>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cari User</label>
                            <input type="text" 
                                   name="search" 
                                   value="<?= htmlspecialchars($search) ?>"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                   placeholder="Username, Nama, Email, No HP">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select name="role" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="">Semua Role</option>
                                <option value="admin" <?= $filter_role == 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="member" <?= $filter_role == 'member' ? 'selected' : '' ?>>Member</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="">Semua Status</option>
                                <option value="active" <?= $filter_status == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $filter_status == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="suspended" <?= $filter_status == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end gap-2">
                            <button type="submit" class="btn-primary flex-1 py-2 px-4 rounded-lg font-medium flex items-center justify-center gap-2">
                                <i class="fas fa-filter"></i>
                                Filter
                            </button>
                            <a href="kelola_user.php" class="py-2 px-4 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Stats Summary -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <?php
                    // Hitung statistik
                    $totalUsers = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
                    $totalAdmins = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'")->fetch_assoc()['total'];
                    $totalMembers = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'member'")->fetch_assoc()['total'];
                    $totalSaldo = $conn->query("SELECT SUM(saldo) as total FROM users WHERE status = 'active'")->fetch_assoc()['total'];
                    ?>
                    <div class="card p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total User</p>
                                <p class="text-2xl font-bold text-gray-900"><?= number_format($totalUsers) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-blue-50">
                                <i class="fas fa-users text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Admin</p>
                                <p class="text-2xl font-bold text-blue-600"><?= number_format($totalAdmins) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-blue-50">
                                <i class="fas fa-user-shield text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Member</p>
                                <p class="text-2xl font-bold text-green-600"><?= number_format($totalMembers) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-green-50">
                                <i class="fas fa-user text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-sm">Total Saldo</p>
                                <p class="text-xl font-bold text-purple-600"><?= rupiah($totalSaldo) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-purple-50">
                                <i class="fas fa-coins text-purple-600"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Users Table -->
                <div class="card overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">
                                Daftar User
                                <span class="text-sm font-normal text-gray-500">(<?= $users->num_rows ?> user)</span>
                            </h3>
                            <button onclick="openModal('addUserModal')" class="btn-primary px-4 py-2 rounded-lg font-medium flex items-center gap-2">
                                <i class="fas fa-plus"></i>
                                Tambah User
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($users->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="table-header">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Kontak</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Saldo</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Role & Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Bergabung</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while ($user = $users->fetch_assoc()): ?>
                                <tr class="table-row">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#<?= $user['id'] ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                    <i class="fas fa-user text-blue-600"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
                                                <div class="text-sm text-gray-500">@<?= htmlspecialchars($user['username']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($user['email']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($user['no_hp']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-lg font-semibold text-gray-900"><?= rupiah($user['saldo']) ?></div>
                                        <button onclick="openUpdateSaldoModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['nama_lengkap']) ?>', <?= $user['saldo'] ?>)" 
                                                class="text-xs text-blue-600 hover:text-blue-800 mt-1">
                                            <i class="fas fa-edit mr-1"></i> Edit Saldo
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="space-y-1">
                                            <span class="badge badge-<?= $user['role'] ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                            <span class="badge badge-<?= $user['status'] ?>">
                                                <?= ucfirst($user['status']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex gap-2">
                                            <button onclick="openEditModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>', '<?= htmlspecialchars($user['nama_lengkap']) ?>', '<?= htmlspecialchars($user['email']) ?>', '<?= htmlspecialchars($user['no_hp']) ?>', <?= $user['saldo'] ?>, '<?= $user['role'] ?>', '<?= $user['status'] ?>')" 
                                                    class="text-blue-600 hover:text-blue-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="openResetPasswordModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" 
                                                    class="text-yellow-600 hover:text-yellow-900" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id'] && $user['role'] != 'admin'): ?>
                                            <button onclick="openDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" 
                                                    class="text-red-600 hover:text-red-900" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
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
                            <i class="fas fa-users text-gray-400 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada user</h3>
                        <p class="text-gray-500 mb-4">Tidak ada user yang sesuai dengan filter yang dipilih</p>
                        <button onclick="openModal('addUserModal')" class="btn-primary px-4 py-2 rounded-lg font-medium flex items-center gap-2 mx-auto">
                            <i class="fas fa-user-plus"></i>
                            Tambah User Pertama
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal Tambah User -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Tambah User Baru</h3>
            </div>
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="action" value="add">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                            <input type="text" name="username" required
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                   placeholder="username">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                            <input type="password" name="password" required minlength="6"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                   placeholder="Minimal 6 karakter">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                        <input type="text" name="nama_lengkap" required
                               class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                               placeholder="Nama lengkap">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input type="email" name="email" required
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                   placeholder="email@example.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label>
                            <input type="tel" name="no_hp"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                   placeholder="081234567890">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Saldo Awal</label>
                            <input type="number" name="saldo" min="0" step="500"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                   value="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select name="role" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="member" selected>Member</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 pt-6">
                    <button type="button" onclick="closeModal('addUserModal')" 
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
    
    <!-- Modal Edit User -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Edit User</h3>
            </div>
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" id="edit_username" disabled
                               class="input-field w-full px-4 py-2 border border-gray-300 bg-gray-50 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                        <input type="text" name="nama_lengkap" id="edit_nama_lengkap" required
                               class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input type="email" name="email" id="edit_email" required
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label>
                            <input type="tel" name="no_hp" id="edit_no_hp"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Saldo</label>
                            <input type="number" name="saldo" id="edit_saldo" min="0" step="500"
                                   class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select name="role" id="edit_role" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="member">Member</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" id="edit_status" class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 pt-6">
                    <button type="button" onclick="closeModal('editUserModal')" 
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
    
    <!-- Modal Update Saldo -->
    <div id="updateSaldoModal" class="modal">
        <div class="modal-content">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Update Saldo User</h3>
            </div>
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="action" value="update_saldo">
                <input type="hidden" name="user_id" id="saldo_user_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                        <input type="text" id="saldo_user_name" disabled
                               class="input-field w-full px-4 py-2 border border-gray-300 bg-gray-50 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Saldo Saat Ini</label>
                        <input type="text" id="current_saldo" disabled
                               class="input-field w-full px-4 py-2 border border-gray-300 bg-gray-50 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Update</label>
                        <div class="flex gap-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="saldo_tipe" value="tambah" checked 
                                       class="text-blue-600 focus:ring-blue-500">
                                <span class="ml-2">Tambah Saldo</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="saldo_tipe" value="kurang"
                                       class="text-blue-600 focus:ring-blue-500">
                                <span class="ml-2">Kurangi Saldo</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah (Rp) *</label>
                        <input type="number" name="jumlah" required min="1000" step="1000"
                               class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                               placeholder="1000">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                        <textarea name="keterangan" rows="2"
                                  class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                                  placeholder="Alasan update saldo..."></textarea>
                    </div>
                </div>
                <div class="flex gap-3 pt-6">
                    <button type="button" onclick="closeModal('updateSaldoModal')" 
                            class="flex-1 py-3 px-4 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                        Batal
                    </button>
                    <button type="submit" 
                            class="btn-primary flex-1 py-3 px-4 rounded-lg font-semibold">
                        <i class="fas fa-coins mr-2"></i> Update Saldo
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Reset Password -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Reset Password User</h3>
            </div>
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" id="reset_username" disabled
                               class="input-field w-full px-4 py-2 border border-gray-300 bg-gray-50 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password Baru *</label>
                        <input type="password" name="new_password" required minlength="6"
                               class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                               placeholder="Minimal 6 karakter">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password *</label>
                        <input type="password" id="confirm_password" required
                               class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none"
                               placeholder="Ulangi password baru">
                    </div>
                </div>
                <div class="flex gap-3 pt-6">
                    <button type="button" onclick="closeModal('resetPasswordModal')" 
                            class="flex-1 py-3 px-4 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                        Batal
                    </button>
                    <button type="submit" onclick="return validatePassword()"
                            class="btn-primary flex-1 py-3 px-4 rounded-lg font-semibold">
                        <i class="fas fa-key mr-2"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Delete User -->
    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Hapus User</h3>
            </div>
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="space-y-4">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <p class="text-gray-700 mb-2">Anda yakin ingin menghapus user <span id="delete_username" class="font-semibold"></span>?</p>
                        <p class="text-sm text-gray-500">Data transaksi user ini juga akan dihapus. Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                </div>
                <div class="flex gap-3 pt-6">
                    <button type="button" onclick="closeModal('deleteUserModal')" 
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
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('closed');
            document.getElementById('overlay').classList.toggle('hidden');
        }
        
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
        
        // Edit User Modal
        function openEditModal(userId, username, namaLengkap, email, noHp, saldo, role, status) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_nama_lengkap').value = namaLengkap;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_no_hp').value = noHp;
            document.getElementById('edit_saldo').value = saldo;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_status').value = status;
            openModal('editUserModal');
        }
        
        // Update Saldo Modal
        function openUpdateSaldoModal(userId, userName, currentSaldo) {
            document.getElementById('saldo_user_id').value = userId;
            document.getElementById('saldo_user_name').value = userName;
            document.getElementById('current_saldo').value = formatRupiah(currentSaldo);
            openModal('updateSaldoModal');
        }
        
        // Reset Password Modal
        function openResetPasswordModal(userId, username) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_username').value = username;
            openModal('resetPasswordModal');
        }
        
        // Delete User Modal
        function openDeleteModal(userId, username) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_username').textContent = username;
            openModal('deleteUserModal');
        }
        
        // Format Rupiah
        function formatRupiah(num) {
            return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Validate password confirmation
        function validatePassword() {
            const password = document.querySelector('input[name="new_password"]');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password.value !== confirmPassword.value) {
                alert('Password dan konfirmasi password tidak sama!');
                confirmPassword.focus();
                return false;
            }
            
            if (password.value.length < 6) {
                alert('Password minimal 6 karakter!');
                password.focus();
                return false;
            }
            
            return confirm('Anda yakin ingin reset password user ini?');
        }
        
        // Close sidebar on menu click (mobile)
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    toggleSidebar();
                }
            });
        });
        
        // Auto-format saldo input
        document.addEventListener('input', function(e) {
            if (e.target.name === 'saldo' || e.target.name === 'jumlah') {
                const value = e.target.value.replace(/[^0-9]/g, '');
                e.target.value = value;
            }
        });
    </script>
</body>
</html>
<?php 
$conn->close();
?>