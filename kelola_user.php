<?php
require_once 'config.php';
cekLogin();

// Hanya admin yang bisa mengakses
if ($_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

$conn = koneksi();
$id_user = $_SESSION['user_id'];
$isSuperAdmin = isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] == 'yes';

// Generate form token for CSRF protection (only on GET requests)
if ($_SERVER['REQUEST_METHOD'] != 'POST' && !isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

// ═══ Layout Variables ═══
$pageTitle   = 'Kelola User';
$pageIcon    = 'fas fa-users';
$pageDesc    = 'Kelola data user dan saldo';
$currentPage = 'kelola_user';
$additionalHeadScripts = '';

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
    // Validasi CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: kelola_user.php");
        exit;
    }

    // Prevent double submission
    $form_token = $_POST['form_token'] ?? '';
    if (!isset($_SESSION['form_token']) || $form_token != $_SESSION['form_token']) {
        setAlert('error', 'Permintaan tidak valid!');
        header("Location: kelola_user.php");
        exit;
    }
    unset($_SESSION['form_token']);
    
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
            $force2FA = 'yes';
            
            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, no_hp, saldo, role, status, force_2fa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssdsss", $username, $hashedPassword, $nama_lengkap, $email, $no_hp, $saldo, $role, $status, $force2FA);
            
            if ($stmt->execute()) {
                // Audit log
                logSecurityEvent('USER_CREATED', [
                    'admin_id' => $_SESSION['user_id'],
                    'new_username' => $username,
                    'new_role' => $role,
                    'initial_saldo' => $saldo
                ]);
                
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
    // Validasi CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: kelola_user.php");
        exit;
    }

    // Prevent double submission
    $form_token = $_POST['form_token'] ?? '';
    if (!isset($_SESSION['form_token']) || $form_token != $_SESSION['form_token']) {
        setAlert('error', 'Permintaan tidak valid!');
        header("Location: kelola_user.php");
        exit;
    }
    unset($_SESSION['form_token']);

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
            // Audit log
            logSecurityEvent('USER_UPDATED', [
                'admin_id' => $_SESSION['user_id'],
                'target_user_id' => $user_id,
                'changes' => [
                    'nama_lengkap' => $nama_lengkap,
                    'email' => $email,
                    'role' => $role,
                    'status' => $status
                ]
            ]);
            
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
    // Validasi CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: kelola_user.php");
        exit;
    }
    
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
                
                // Audit log untuk keamanan
                logSecurityEvent('ADMIN_BALANCE_CHANGE', [
                    'admin_id' => $_SESSION['user_id'],
                    'target_user_id' => $user_id,
                    'target_username' => $user['nama_lengkap'],
                    'type' => $saldo_tipe,
                    'amount' => $jumlah,
                    'balance_before' => $saldo_sebelum,
                    'balance_after' => $saldo_sesudah,
                    'note' => $keterangan
                ]);
                
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
    // Validasi CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: kelola_user.php");
        exit;
    }

    // Prevent double submission
    $form_token = $_POST['form_token'] ?? '';
    if (!isset($_SESSION['form_token']) || $form_token != $_SESSION['form_token']) {
        setAlert('error', 'Permintaan tidak valid!');
        header("Location: kelola_user.php");
        exit;
    }
    unset($_SESSION['form_token']);
    
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
                    // Audit log
                    logSecurityEvent('USER_DELETED', [
                        'admin_id' => $_SESSION['user_id'],
                        'deleted_user_id' => $user_id
                    ]);
                    
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
    // Validasi CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: kelola_user.php");
        exit;
    }

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
$_SESSION['saldo'] = getSaldo($id_user);

// Include layout
include 'layout.php';
?>

<!-- ═══════════════════════════════════════════
     KELOLA USER - Professional Styling
═══════════════════════════════════════════ -->
<style>
/* ===== VARIABLES & RESET ===== */
:root {
    --primary: #2563eb;
    --primary-dark: #1d4ed8;
    --primary-light: #3b82f6;
    --primary-bg: #eff6ff;
    --secondary: #10b981;
    --secondary-dark: #059669;
    --warning: #f59e0b;
    --danger: #ef4444;
    --danger-dark: #dc2626;
    --dark: #1f2937;
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-400: #9ca3af;
    --gray-500: #6b7280;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-800: #1f2937;
    --gray-900: #111827;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    --radius-sm: 0.375rem;
    --radius: 0.5rem;
    --radius-lg: 0.75rem;
    --radius-xl: 1rem;
}

* {
    box-sizing: border-box;
}

/* ===== TYPOGRAPHY & UTILITY CLASSES ===== */
.text-xs { font-size: 0.75rem; line-height: 1rem; }
.text-sm { font-size: 0.875rem; line-height: 1.25rem; }
.text-base { font-size: 1rem; line-height: 1.5rem; }
.text-lg { font-size: 1.125rem; line-height: 1.75rem; }
.text-xl { font-size: 1.25rem; line-height: 1.75rem; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.font-medium { font-weight: 500; }
.font-semibold { font-weight: 600; }
.font-bold { font-weight: 700; }

.text-gray-400 { color: var(--gray-400); }
.text-gray-500 { color: var(--gray-500); }
.text-gray-600 { color: var(--gray-600); }
.text-gray-700 { color: var(--gray-700); }
.text-gray-800 { color: var(--gray-800); }
.text-gray-900 { color: var(--gray-900); }
.text-primary { color: var(--primary); }
.text-success { color: var(--secondary); }
.text-warning { color: var(--warning); }
.text-danger { color: var(--danger); }

.bg-gray-50 { background-color: var(--gray-50); }
.bg-gray-100 { background-color: var(--gray-100); }
.bg-primary { background-color: var(--primary); }
.bg-primary-bg { background-color: var(--primary-bg); }
.bg-success-bg { background-color: #d1fae5; }
.bg-warning-bg { background-color: #fed7aa; }
.bg-danger-bg { background-color: #fee2e2; }

/* ===== BADGES ===== */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    line-height: 1rem;
    white-space: nowrap;
    gap: 0.25rem;
}

.badge-sm {
    padding: 0.125rem 0.375rem;
    font-size: 0.625rem;
}

.badge-primary {
    background-color: var(--primary-bg);
    color: var(--primary-dark);
    border: 1px solid #bfdbfe;
}

.badge-success {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.badge-warning {
    background-color: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}

.badge-danger {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.badge-purple {
    background-color: #f3e8ff;
    color: #6b21a8;
    border: 1px solid #e9d5ff;
}

/* ===== STAT CARDS ===== */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-light);
}

.stat-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-label {
    color: var(--gray-500);
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.25rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
}

.stat-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.stat-icon.blue { background: var(--primary-bg); color: var(--primary); }
.stat-icon.green { background: #d1fae5; color: var(--secondary-dark); }
.stat-icon.purple { background: #f3e8ff; color: #7e22ce; }

/* ===== CARDS ===== */
.card {
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--gray-200);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    background: var(--gray-50);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.card-header h3 {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 1rem;
    margin: 0;
}

.card-header p {
    color: var(--gray-500);
    font-size: 0.75rem;
    margin-top: 0.125rem;
}

.card-body {
    padding: 1.5rem;
}

/* ===== FILTER SECTION ===== */
.filter-section {
    padding: 1.25rem 1.5rem;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

.filter-actions {
    display: flex;
    align-items: flex-end;
    gap: 0.5rem;
}

/* ===== FORM ELEMENTS ===== */
.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--gray-600);
    margin-bottom: 0.375rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.form-control {
    width: 100%;
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    border: 1px solid var(--gray-300);
    border-radius: var(--radius);
    background: white;
    transition: all 0.15s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-control:disabled,
.form-control[readonly] {
    background-color: var(--gray-100);
    border-color: var(--gray-300);
    color: var(--gray-500);
    cursor: not-allowed;
}

select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-position: right 0.75rem center;
    background-repeat: no-repeat;
    background-size: 1.25rem;
    padding-right: 2.5rem;
}

/* ===== BUTTONS ===== */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: var(--radius);
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    gap: 0.375rem;
}

.btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
}

.btn-primary {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.btn-primary:hover {
    background: var(--primary-dark);
    border-color: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}

.btn-outline {
    background: white;
    color: var(--gray-700);
    border-color: var(--gray-300);
}

.btn-outline:hover {
    background: var(--gray-50);
    border-color: var(--gray-400);
}

.btn-danger {
    background: var(--danger);
    color: white;
    border-color: var(--danger);
}

.btn-danger:hover {
    background: var(--danger-dark);
    border-color: var(--danger-dark);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
}

/* ===== TABLE ===== */
.table-container {
    overflow-x: auto;
    margin: 0;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    background: var(--gray-50);
    padding: 0.875rem 1.5rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--gray-600);
    border-bottom: 2px solid var(--gray-200);
    white-space: nowrap;
}

.table td {
    padding: 1rem 1.5rem;
    font-size: 0.875rem;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    vertical-align: middle;
}

.table tbody tr:hover {
    background: var(--gray-50);
}

.table tbody tr:last-child td {
    border-bottom: none;
}

/* User avatar in table */
.user-avatar {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 9999px;
    background: var(--primary-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1rem;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-name {
    font-weight: 500;
    color: var(--gray-800);
}

.user-username {
    font-size: 0.75rem;
    color: var(--gray-500);
}

/* Action buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.action-btn {
    width: 2rem;
    height: 2rem;
    border-radius: var(--radius);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--gray-200);
    background: white;
    color: var(--gray-600);
    cursor: pointer;
    transition: all 0.15s ease;
}

.action-btn:hover {
    background: var(--gray-100);
    border-color: var(--gray-300);
    transform: translateY(-1px);
}

.action-btn.edit:hover { color: var(--primary); border-color: var(--primary); }
.action-btn.key:hover { color: var(--warning); border-color: var(--warning); }
.action-btn.delete:hover { color: var(--danger); border-color: var(--danger); }

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 3rem 1.5rem;
}

.empty-state-icon {
    width: 4rem;
    height: 4rem;
    border-radius: 9999px;
    background: var(--gray-100);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    color: var(--gray-400);
    font-size: 2rem;
}

.empty-state h4 {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.25rem;
    font-size: 1rem;
}

.empty-state p {
    color: var(--gray-500);
    font-size: 0.875rem;
    margin-bottom: 1.25rem;
}

/* ===== MODALS ===== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    backdrop-filter: blur(4px);
    overflow-y: auto;
}

.modal-dialog {
    display: flex;
    align-items: flex-start;
    justify-content: center;
    min-height: 100%;
    padding: 2rem 1rem;
}

.modal-content {
    background: white;
    border-radius: var(--radius-lg);
    max-width: 500px;
    width: 100%;
    box-shadow: var(--shadow-xl);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-header h3 {
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
    font-size: 1.125rem;
}

.modal-close {
    background: none;
    border: none;
    color: var(--gray-400);
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.25rem;
    border-radius: 9999px;
    transition: all 0.15s ease;
}

.modal-close:hover {
    background: var(--gray-100);
    color: var(--gray-600);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.25rem 1.5rem;
    border-top: 1px solid var(--gray-200);
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

.modal-footer .btn {
    flex: 1;
}

/* ===== ALERTS ===== */
.alert {
    padding: 1rem 1.25rem;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-success {
    background: #d1fae5;
    border: 1px solid #a7f3d0;
    color: #065f46;
}

.alert-error {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.alert-icon {
    font-size: 1.25rem;
}

.alert-content {
    flex: 1;
    font-size: 0.875rem;
    font-weight: 500;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
    .stat-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .card-header {
        padding: 1rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .filter-section {
        padding: 1rem;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .table th,
    .table td {
        padding: 0.75rem 1rem;
    }
    
    .user-avatar {
        width: 2rem;
        height: 2rem;
        font-size: 0.875rem;
    }
    
    .hide-mobile {
        display: none;
    }
    
    .modal-dialog {
        padding: 1rem;
    }
    
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 1rem;
    }
}

@media (max-width: 480px) {
    .stat-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-btn {
        width: 100%;
    }
}
</style>

<!-- Main Content Container -->
<div class="container" style="max-width: 1400px; margin: 0 auto; padding: 0 1.5rem;">

    <!-- Alert Message -->
    <?php if ($alert): ?>
    <div class="alert alert-<?= $alert['type'] ?>">
        <i class="fas <?= $alert['type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> alert-icon"></i>
        <span class="alert-content"><?= htmlspecialchars($alert['message']) ?></span>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <?php
    $totalUsers = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
    $totalAdmins = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'")->fetch_assoc()['total'];
    $totalMembers = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'member'")->fetch_assoc()['total'];
    $totalSaldo = $conn->query("SELECT SUM(saldo) as total FROM users WHERE status = 'active'")->fetch_assoc()['total'] ?? 0;
    ?>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-content">
                <div>
                    <div class="stat-label">Total User</div>
                    <div class="stat-value"><?= number_format($totalUsers) ?></div>
                </div>
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-content">
                <div>
                    <div class="stat-label">Admin</div>
                    <div class="stat-value" style="color: var(--primary);"><?= number_format($totalAdmins) ?></div>
                </div>
                <div class="stat-icon blue">
                    <i class="fas fa-user-shield"></i>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-content">
                <div>
                    <div class="stat-label">Member</div>
                    <div class="stat-value" style="color: var(--secondary-dark);"><?= number_format($totalMembers) ?></div>
                </div>
                <div class="stat-icon green">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-content">
                <div>
                    <div class="stat-label">Total Saldo</div>
                    <div class="stat-value" style="font-size: 1.25rem;"><?= rupiah($totalSaldo) ?></div>
                </div>
                <div class="stat-icon purple">
                    <i class="fas fa-coins"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card">
        <div class="filter-section">
            <form method="GET">
                <div class="filter-grid">
                    <div>
                        <label class="form-label">Cari User</label>
                        <input type="text" name="search" class="form-control" 
                               value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Username, Nama, Email, No HP">
                    </div>
                    <div>
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control">
                            <option value="">Semua Role</option>
                            <option value="admin" <?= $filter_role == 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="member" <?= $filter_role == 'member' ? 'selected' : '' ?>>Member</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">Semua Status</option>
                            <option value="active" <?= $filter_status == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $filter_status == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="suspended" <?= $filter_status == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary flex-1">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="kelola_user.php" class="btn btn-outline">
                            <i class="fas fa-sync-alt"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3>Daftar User</h3>
                <p><?= $users->num_rows ?> user ditemukan</p>
            </div>
            <button onclick="openModal('addUserModal')" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Tambah User
            </button>
        </div>

        <?php if ($users->num_rows > 0): ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Kontak</th>
                        <th>Saldo</th>
                        <th>Role & Status</th>
                        <th>Bergabung</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td style="color: var(--gray-500); font-weight: 500;">#<?= $user['id'] ?></td>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="user-details">
                                    <span class="user-name"><?= htmlspecialchars($user['nama_lengkap']) ?></span>
                                    <span class="user-username">@<?= htmlspecialchars($user['username']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 500;"><?= htmlspecialchars($user['email']) ?></div>
                            <div style="font-size: 0.75rem; color: var(--gray-500);"><?= htmlspecialchars($user['no_hp'] ?: '-') ?></div>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: var(--gray-800);"><?= rupiah($user['saldo']) ?></div>
                            <button onclick="openUpdateSaldoModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['nama_lengkap']) ?>', <?= $user['saldo'] ?>)" 
                                    class="action-btn edit" style="margin-top: 0.25rem;" title="Edit Saldo">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                        <td>
                            <div style="display: flex; flex-direction: column; gap: 0.375rem;">
                                <span class="badge <?= $user['role'] == 'admin' ? 'badge-purple' : 'badge-primary' ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                                <span class="badge badge-sm <?php
                                    echo $user['status'] == 'active' ? 'badge-success' : 
                                        ($user['status'] == 'inactive' ? 'badge-warning' : 'badge-danger');
                                ?>">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                            </div>
                        </td>
                        <td style="color: var(--gray-500);">
                            <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button onclick="openEditModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>', '<?= htmlspecialchars($user['nama_lengkap']) ?>', '<?= htmlspecialchars($user['email']) ?>', '<?= htmlspecialchars($user['no_hp']) ?>', <?= $user['saldo'] ?>, '<?= $user['role'] ?>', '<?= $user['status'] ?>')" 
                                        class="action-btn edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="openResetPasswordModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" 
                                        class="action-btn key" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                <?php if ($isSuperAdmin): ?>
                                <button onclick="openReset2FAModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')"
                                        class="action-btn" style="background:#f59e0b;color:white;" title="Reset 2FA">
                                    <i class="fas fa-shield-alt"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($user['id'] != $_SESSION['user_id'] && $user['role'] != 'admin'): ?>
                                <button onclick="openDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')"
                                        class="action-btn delete" title="Hapus">
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
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-users"></i>
            </div>
            <h4>Tidak ada user</h4>
            <p>Tidak ada user yang sesuai dengan filter yang dipilih</p>
            <button onclick="openModal('addUserModal')" class="btn btn-primary">
                <i class="fas fa-user-plus"></i>
                Tambah User Pertama
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- MODALS -->

    <!-- Modal Tambah User -->
    <div id="addUserModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Tambah User Baru</h3>
                    <button type="button" class="modal-close" onclick="closeModal('addUserModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" action="" onsubmit="const btn=this.querySelector('button[type=\'submit\']'); if(btn.disabled){return false;} btn.disabled=true; btn.innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Menyimpan...';">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="form_token" value="<?= htmlspecialchars($_SESSION['form_token'] ?? '') ?>">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Username <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="username" class="form-control" required placeholder="username">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password <span style="color: var(--danger);">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="6" placeholder="Minimal 6 karakter">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="nama_lengkap" class="form-control" required placeholder="Nama lengkap">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email <span style="color: var(--danger);">*</span></label>
                            <input type="email" name="email" class="form-control" required placeholder="email@example.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">No. HP</label>
                            <input type="tel" name="no_hp" class="form-control" placeholder="081234567890">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Saldo Awal</label>
                            <input type="number" name="saldo" class="form-control" min="0" step="500" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-control">
                                <option value="member">Member</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-shield-alt" style="color: var(--primary);"></i> 
                                Semua user baru wajib 2FA
                            </label>
                            <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 4px;">User harus setup Google Authenticator sebelum bisa akses menu</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit User -->
    <div id="editUserModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit User</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editUserModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" id="edit_username" class="form-control" disabled readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email <span style="color: var(--danger);">*</span></label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">No. HP</label>
                            <input type="tel" name="no_hp" id="edit_no_hp" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Saldo</label>
                            <input type="number" name="saldo" id="edit_saldo" class="form-control" min="0" step="500">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <select name="role" id="edit_role" class="form-control">
                                <option value="member">Member</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">Batal</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Update Saldo -->
    <div id="updateSaldoModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Update Saldo User</h3>
                    <button type="button" class="modal-close" onclick="closeModal('updateSaldoModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="update_saldo">
                    <input type="hidden" name="user_id" id="saldo_user_id">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">User</label>
                            <input type="text" id="saldo_user_name" class="form-control" disabled readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Saldo Saat Ini</label>
                            <input type="text" id="current_saldo" class="form-control" disabled readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tipe Update</label>
                            <div style="display: flex; gap: 1rem; padding: 0.5rem 0;">
                                <label style="display: flex; align-items: center; gap: 0.375rem;">
                                    <input type="radio" name="saldo_tipe" value="tambah" checked style="accent-color: var(--primary);">
                                    <span style="font-size: 0.875rem;">Tambah Saldo</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.375rem;">
                                    <input type="radio" name="saldo_tipe" value="kurang" style="accent-color: var(--primary);">
                                    <span style="font-size: 0.875rem;">Kurangi Saldo</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Jumlah (Rp) <span style="color: var(--danger);">*</span></label>
                            <input type="number" name="jumlah" class="form-control" required min="1000" step="1000" placeholder="1000">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="2" placeholder="Alasan update saldo..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('updateSaldoModal')">Batal</button>
                        <button type="submit" class="btn btn-primary">Update Saldo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Reset Password -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Reset Password User</h3>
                    <button type="button" class="modal-close" onclick="closeModal('resetPasswordModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" action="" onsubmit="return validatePassword()">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" id="reset_username" class="form-control" disabled readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password Baru <span style="color: var(--danger);">*</span></label>
                            <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Minimal 6 karakter">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Konfirmasi Password <span style="color: var(--danger);">*</span></label>
                            <input type="password" id="confirm_password" class="form-control" required placeholder="Ulangi password baru">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('resetPasswordModal')">Batal</button>
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Reset 2FA -->
    <div id="reset2FAModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Reset 2FA User</h3>
                    <button type="button" class="modal-close" onclick="closeModal('reset2FAModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" action="" onsubmit="return reset2FA(event)">
                    <div class="modal-body">
                        <p>Anda akan mereset 2FA untuk user:</p>
                        <input type="hidden" name="action" value="reset_2fa">
                        <input type="hidden" name="user_id" id="reset2fa_user_id">
                        <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:16px;margin:12px 0;">
                            <strong style="color:#92400e;" id="reset2fa_username"></strong>
                        </div>
                        <p style="color:#dc2626;font-size:14px;">User akan logout dan harus setup 2FA ulang saat login.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('reset2FAModal')">Batal</button>
                        <button type="submit" class="btn btn-primary" style="background:#f59e0b;border-color:#f59e0b;">Reset 2FA</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Delete User -->
    <div id="deleteUserModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Hapus User</h3>
                    <button type="button" class="modal-close" onclick="closeModal('deleteUserModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" action="" onsubmit="this.querySelector('button[type=\'submit\']').disabled = true;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <input type="hidden" name="form_token" value="<?= htmlspecialchars($_SESSION['form_token'] ?? '') ?>">
                    <div class="modal-body">
                        <div style="text-align: center; padding: 1rem 0;">
                            <div style="width: 4rem; height: 4rem; background: #fee2e2; border-radius: 9999px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                                <i class="fas fa-exclamation-triangle" style="color: var(--danger); font-size: 1.5rem;"></i>
                            </div>
                            <p style="color: var(--gray-700); margin-bottom: 0.5rem;">
                                Anda yakin ingin menghapus user <strong id="delete_username" style="color: var(--gray-900);"></strong>?
                            </p>
                            <p style="color: var(--gray-500); font-size: 0.875rem;">
                                Data transaksi user ini juga akan dihapus. Tindakan ini tidak dapat dibatalkan.
                            </p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('deleteUserModal')">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
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
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
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
    document.getElementById('current_saldo').value = 'Rp ' + currentSaldo.toLocaleString('id-ID');
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

// Reset 2FA Modal
function openReset2FAModal(userId, username) {
    document.getElementById('reset2fa_user_id').value = userId;
    document.getElementById('reset2fa_username').textContent = username;
    openModal('reset2FAModal');
}

function reset2FA(e) {
    e.preventDefault();
    var userId = document.getElementById('reset2fa_user_id').value;
    var path = window.location.pathname;
    var base = path.includes('/payment/') ? '/payment' : '';
    var apiUrl = base + '/api/2fa_reset.php?action=reset';

    fetch(apiUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'user_id=' + userId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('2FA berhasil direset!');
            closeModal('reset2FAModal');
            location.reload();
        } else {
            alert('Gagal: ' + data.message);
        }
    });
    return false;
}

// Validate Password
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
    
    return true; // Skip confirmation
}

// Format Rupiah helper
function formatRupiah(num) {
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Auto-submit filter on change (desktop)
document.querySelectorAll('select[name="role"], select[name="status"]').forEach(el => {
    el.addEventListener('change', function() {
        this.closest('form').submit();
    });
});

// Debounced search
let searchTimeout;
document.querySelector('input[name="search"]')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.closest('form').submit();
    }, 500);
});
</script>

<?php 
include 'layout_footer.php';
$conn->close();
?>