<?php
require_once 'config.php';
cekLogin();

// Hanya admin/superadmin yang bisa mengakses
if (!in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit;
}

$conn = koneksi();
$id_user = $_SESSION['user_id'];

// ═══ Layout Variables ═══
$pageTitle   = 'Kelola Produk';
$pageIcon    = 'fas fa-box';
$pageDesc    = 'Kelola produk pulsa, kuota, dan listrik';
$currentPage = 'kelola_produk';
$additionalHeadScripts = ''; // Tidak perlu script tambahan

// Ambil semua kategori
$kategori = $conn->query("SELECT * FROM kategori_produk WHERE status = 'active' ORDER BY nama_kategori");

// Ambil data produk dengan filter
$search = $_GET['search'] ?? '';
$kategori_id = $_GET['kategori_id'] ?? '';
$provider = $_GET['provider'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Pagination
$per_page = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 20;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $per_page;

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
    $where[] = "p.kategori_id = ?";
    $params[] = $kategori_id;
    $types .= 'i';
}

if (!empty($provider)) {
    $where[] = "p.provider = ?";
    $params[] = $provider;
    $types .= 's';
}

if (!empty($status_filter)) {
    $where[] = "p.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// Count total records
$countSql = "SELECT COUNT(*) as total FROM produk p $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();
$total_records = $countResult['total'];
$total_pages = ceil($total_records / $per_page);

$sql = "SELECT p.*, k.nama_kategori
        FROM produk p
        LEFT JOIN kategori_produk k ON p.kategori_id = k.id
        $whereClause
        ORDER BY p.kategori_id, p.provider, p.nominal
        LIMIT ? OFFSET ?";
$stmtProduk = $conn->prepare($sql);

if (!empty($params)) {
    $allParams = $params;
    $allParams[] = $per_page;
    $allParams[] = $offset;
    $stmtProduk->bind_param($types . 'ii', ...$allParams);
} else {
    $stmtProduk->bind_param('ii', $per_page, $offset);
}

$stmtProduk->execute();
$produk = $stmtProduk->get_result();

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
            $stmt->bind_param("ssisd ddds", $kode_produk, $nama_produk, $kategori_id, $provider, $nominal, $harga_jual, $harga_modal, $status);

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
            $stmt->bind_param("ssisddssi", $kode_produk, $nama_produk, $kategori_id, $provider, $nominal, $harga_jual, $harga_modal, $status, $produk_id);
            
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

// Import CSV
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'import_csv') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: kelola_produk.php");
        exit;
    }
    
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        setAlert('error', 'File CSV belum dipilih!');
        header("Location: kelola_produk.php");
        exit;
    }
    
    $file = $_FILES['csv_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($ext !== 'csv') {
        setAlert('error', 'File harus berformat CSV!');
        header("Location: kelola_produk.php");
        exit;
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        setAlert('error', 'Gagal membuka file CSV!');
        header("Location: kelola_produk.php");
        exit;
    }
    
    // CSV dengan nama kategori (bukan ID)
    $kategori_map = [
        'pulsa' => 1,
        'kuota' => 2,
        'internet' => 2,
        'data' => 2,
        'listrik' => 3,
        'token' => 3,
        'pln' => 3
    ];
    
    $row = 0;
    $inserted = 0;
    $updated = 0;
    $errors = [];
    
    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
        $row++;
        
        // Skip header
        if ($row == 1) {
            if (strtolower($data[0]) === 'kategori' || strtolower($data[0]) === 'kategori_id') continue;
        }
        
        // Skip empty rows
        if (empty($data[0]) || empty($data[1])) continue;
        
        // Parse kategori - bisa ID (1/2/3) atau nama (pulsa/kuota/listrik)
        $kategori_input = strtolower(trim($data[0] ?? ''));
        if (is_numeric($kategori_input)) {
            $kategori_id = intval($kategori_input);
        } else {
            $kategori_id = $kategori_map[$kategori_input] ?? 0;
        }
        
        // Auto-generate kode jika kosong
        $kode_produk = trim($data[1] ?? '');
        if (empty($kode_produk)) {
            $provider_code = strtoupper(substr(trim($data[3] ?? 'XXX'), 0, 3));
            $kode_produk = $provider_code . '-' . strtoupper(uniqid());
        }
        
        $nama_produk = trim($data[2] ?? '');
        $provider = trim($data[3] ?? '');
        $nominal = floatval($data[4] ?? 0);
        $harga_jual = floatval($data[5] ?? 0);
        $harga_modal = floatval($data[6] ?? 0);
        $status = strtolower(trim($data[7] ?? '')) ?: 'active';
        
        // Validation
        if ($kategori_id < 1 || $kategori_id > 3) {
            $errors[] = "Baris {$row}: kategori tidak valid (pakai: pulsa/kuota/listrik atau 1/2/3)";
            continue;
        }
        if (empty($kode_produk)) {
            $errors[] = "Baris {$row}: kode_produk wajib diisi";
            continue;
        }
        if (empty($nama_produk)) {
            $errors[] = "Baris {$row}: nama_produk wajib diisi";
            continue;
        }
        if ($harga_jual <= 0 || $harga_modal <= 0) {
            $errors[] = "Baris {$row}: harga harus lebih dari 0";
            continue;
        }
        if (!in_array($status, ['active', 'inactive'])) {
            $status = 'active';
        }
        
        // Check if exists - Upsert
        $checkStmt = $conn->prepare("SELECT id FROM produk WHERE kode_produk = ?");
        $checkStmt->bind_param("s", $kode_produk);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->num_rows > 0;
        
        if ($exists) {
            // Update
            $stmt = $conn->prepare("UPDATE produk SET nama_produk = ?, kategori_id = ?, provider = ?, nominal = ?, harga_jual = ?, harga_modal = ?, status = ? WHERE kode_produk = ?");
            $stmt->bind_param("sisdddss", $nama_produk, $kategori_id, $provider, $nominal, $harga_jual, $harga_modal, $status, $kode_produk);
            if ($stmt->execute()) $updated++;
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO produk (kategori_id, kode_produk, nama_produk, provider, nominal, harga_jual, harga_modal, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssddds", $kategori_id, $kode_produk, $nama_produk, $provider, $nominal, $harga_jual, $harga_modal, $status);
            if ($stmt->execute()) $inserted++;
        }
    }
    
    fclose($handle);
    
    if ($inserted > 0 || $updated > 0) {
        $msg = "Import selesai! {$inserted} produk baru ditambahkan, {$updated} produk diperbarui.";
        if (!empty($errors)) {
            $msg .= " (" . count($errors) . " error)";
        }
        setAlert('success', $msg);
    } elseif (!empty($errors)) {
        setAlert('error', "Gagal import: " . implode("; ", array_slice($errors, 0, 5)));
        if (count($errors) > 5) setAlert('error', "... dan " . (count($errors) - 5) . " error lainnya");
    } else {
        setAlert('info', 'Tidak ada data yang diimport');
    }
    
    header("Location: kelola_produk.php");
    exit;
}

// Export CSV
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'export_csv') {
    $kategori_filter = $_POST['kategori_filter'] ?? '';
    $provider_filter = $_POST['provider_filter'] ?? '';
    
    $where = [];
    $params = [];
    $types = '';
    
    if (!empty($kategori_filter) && is_numeric($kategori_filter)) {
        $where[] = "kategori_id = ?";
        $params[] = $kategori_filter;
        $types .= 'i';
    }
    
    if (!empty($provider_filter)) {
        $where[] = "provider = ?";
        $params[] = $provider_filter;
        $types .= 's';
    }
    
    $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "SELECT kategori_id, kode_produk, nama_produk, provider, nominal, harga_jual, harga_modal, status FROM produk $whereClause ORDER BY kategori_id, provider, nominal";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Kategori map for export
    $kategori_names = [1 => 'pulsa', 2 => 'kuota', 3 => 'listrik'];
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="produk_export_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['kategori', 'kode_produk', 'nama_produk', 'provider', 'nominal', 'harga_jual', 'harga_modal', 'status']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $kategori_names[$row['kategori_id']] ?? 'unknown',
            $row['kode_produk'],
            $row['nama_produk'],
            $row['provider'],
            $row['nominal'],
            $row['harga_jual'],
            $row['harga_modal'],
            $row['status']
        ]);
    }
    
    fclose($output);
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
$_SESSION['saldo'] = getSaldo($id_user);

// Include layout
include 'layout.php';
?>

<!-- ═══════════════════════════════════════════
     KELOLA PRODUK - Custom Styles
═══════════════════════════════════════════ -->
<style>
:root {
    --primary-blue: #2563eb;
    --secondary-blue: #3b82f6;
    --light-blue: #eff6ff;
}

/* ── Badge ── */
.badge {
    display: inline-block;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
    transition: opacity 0.2s ease;
}
.badge:hover {
    opacity: 0.8;
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

/* ── Category Cards ── */
.category-pulsa {
    border-left: 4px solid #6366f1;
}
.category-kuota {
    border-left: 4px solid #10b981;
}
.category-listrik {
    border-left: 4px solid #f59e0b;
}

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

/* ── Bulk Action Bar ── */
.bulk-action-bar {
    display: none;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    background: linear-gradient(135deg, #1e293b, #334155);
    color: white;
    padding: 0.875rem 1.25rem;
    border-radius: 0.875rem;
    margin-bottom: 1rem;
    animation: slideDown 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.bulk-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    font-size: 0.9rem;
}
.bulk-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.bulk-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 0.875rem;
    border-radius: 0.5rem;
    font-size: 0.8rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}
.bulk-btn:hover { transform: translateY(-1px); opacity: 0.9; }
.bulk-btn:active { transform: scale(0.97); }
.bulk-btn-active { background: #22c55e; color: white; }
.bulk-btn-inactive { background: #f59e0b; color: white; }
.bulk-btn-price { background: #6366f1; color: white; }
.bulk-btn-delete { background: #ef4444; color: white; }
.bulk-btn-clear { background: rgba(255,255,255,0.15); color: white; }

/* Bulk Price Modal */
.bulk-price-modal {
    display: none;
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    z-index: 10000;
    justify-content: center;
    align-items: center;
}
.bulk-price-modal.active { display: flex; }
.bulk-price-content {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 25px 50px rgba(0,0,0,0.25);
    animation: slideUp 0.3s ease;
}
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

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

/* ── Input Field ── */
.input-field {
    transition: all 0.2s ease;
}
.input-field:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* ── Button Primary ── */
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

/* ── Button Danger ── */
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

/* ── Profit Badge ── */
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

/* ── Modal ── */
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

/* ── Alert ── */
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

/* ── Animation Delays ── */
.delay-100 { animation-delay: 0.1s; }
.delay-200 { animation-delay: 0.2s; }
.delay-300 { animation-delay: 0.3s; }

/* ── Responsive helpers ── */
@media (max-width: 767px) {
    .hide-mobile { display: none !important; }
}
@media (min-width: 768px) {
    .hide-desktop { display: none !important; }
}

/* ── Tabs ── */
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

<!-- ═══════════════════════════════════════════
     KELOLA PRODUK - Content
═══════════════════════════════════════════ -->

<!-- Page Title (using welcome-section style from dashboard) -->

<!-- Alert Message -->
<?php if ($alert): ?>
<div class="alert mb-6 p-4 rounded-lg flex items-center gap-3 <?= $alert['type'] == 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>" style="animation: slideIn 0.3s ease;">
    <i class="fas <?= $alert['type'] == 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> text-lg"></i>
    <span class="font-medium"><?= $alert['message'] ?></span>
</div>
<?php endif; ?>

<!-- Stats Summary -->
<?php
// Hitung statistik
$totalProduk = $conn->query("SELECT COUNT(*) as total FROM produk")->fetch_assoc()['total'];
$activeProduk = $conn->query("SELECT COUNT(*) as total FROM produk WHERE status = 'active'")->fetch_assoc()['total'];
$totalPulsa = $conn->query("SELECT COUNT(*) as total FROM produk WHERE kategori_id = 1")->fetch_assoc()['total'];
$totalKuota = $conn->query("SELECT COUNT(*) as total FROM produk WHERE kategori_id = 2")->fetch_assoc()['total'];
$totalListrik = $conn->query("SELECT COUNT(*) as total FROM produk WHERE kategori_id = 3")->fetch_assoc()['total'];
?>
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6 animate-slide-in delay-100">
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 mb-1">Total Produk</p>
                <p class="text-2xl font-bold text-gray-800"><?= number_format($totalProduk) ?></p>
            </div>
            <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center">
                <i class="fas fa-box text-blue-600"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 mb-1">Produk Aktif</p>
                <p class="text-2xl font-bold text-green-600"><?= number_format($activeProduk) ?></p>
            </div>
            <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center">
                <i class="fas fa-check-circle text-green-600"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card category-pulsa">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 mb-1">Produk Pulsa</p>
                <p class="text-2xl font-bold text-indigo-600"><?= number_format($totalPulsa) ?></p>
            </div>
            <div class="w-10 h-10 rounded-full bg-indigo-50 flex items-center justify-center">
                <i class="fas fa-mobile-alt text-indigo-600"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card category-kuota">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 mb-1">Produk Kuota</p>
                <p class="text-2xl font-bold text-green-600"><?= number_format($totalKuota) ?></p>
            </div>
            <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center">
                <i class="fas fa-wifi text-green-600"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card category-listrik">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 mb-1">Produk Listrik</p>
                <p class="text-2xl font-bold text-yellow-600"><?= number_format($totalListrik) ?></p>
            </div>
            <div class="w-10 h-10 rounded-full bg-yellow-50 flex items-center justify-center">
                <i class="fas fa-bolt text-yellow-600"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="card p-6 mb-6 animate-slide-in delay-200">
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
                <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Inactive</option>
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

<!-- Products Table -->
<div class="card overflow-hidden animate-slide-in delay-300">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h3 class="text-lg font-semibold text-gray-900">
                Daftar Produk
                <span class="text-sm font-normal text-gray-500 ml-2">(<?= $produk->num_rows ?> produk)</span>
            </h3>
            <div class="flex gap-2">
                <button onclick="openModal('importCsvModal')" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition flex items-center gap-2">
                    <i class="fas fa-file-import"></i>
                    Import CSV
                </button>
                <form method="POST" action="" id="exportForm" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="export_csv">
                    <input type="hidden" name="kategori_filter" value="<?= htmlspecialchars($_GET['kategori_id'] ?? '') ?>">
                    <input type="hidden" name="provider_filter" value="<?= htmlspecialchars($_GET['provider'] ?? '') ?>">
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 transition flex items-center gap-2">
                        <i class="fas fa-file-export"></i>
                        Export CSV
                    </button>
                </form>
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
    <!-- Bulk Action Bar -->
    <div id="bulkActionBar" style="display:none;" class="bulk-action-bar">
        <div class="bulk-info">
            <i class="fas fa-check-square"></i>
            <span id="selectedCount">0</span> produk dipilih
        </div>
        <div class="bulk-buttons">
            <button type="button" onclick="bulkAction('active')" class="bulk-btn bulk-btn-active">
                <i class="fas fa-check"></i> Aktifkan
            </button>
            <button type="button" onclick="bulkAction('inactive')" class="bulk-btn bulk-btn-inactive">
                <i class="fas fa-ban"></i> Nonaktifkan
            </button>
            <button type="button" onclick="showBulkPriceModal()" class="bulk-btn bulk-btn-price">
                <i class="fas fa-tag"></i> Update Harga
            </button>
            <button type="button" onclick="bulkAction('delete')" class="bulk-btn bulk-btn-delete">
                <i class="fas fa-trash"></i> Hapus
            </button>
            <button type="button" onclick="clearSelection()" class="bulk-btn bulk-btn-clear">
                <i class="fas fa-times"></i> Clear
            </button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">
                        <input type="checkbox" id="selectAll" onclick="toggleAll(this)" class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Produk</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kategori</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Provider</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Harga & Profit</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            <tbody>
                <?php while ($p = $produk->fetch_assoc()): 
                    $profit = $p['harga_jual'] - $p['harga_modal'];
                    $profit_margin = $p['harga_modal'] > 0 ? ($profit / $p['harga_modal']) * 100 : 0;
                    $category_class = '';
                    if ($p['kategori_id'] == 1) $category_class = 'category-pulsa';
                    if ($p['kategori_id'] == 2) $category_class = 'category-kuota';
                    if ($p['kategori_id'] == 3) $category_class = 'category-listrik';
                ?>
                <tr class="hover:bg-gray-50 transition-colors duration-150 <?= $category_class ?>">
                    <td class="px-4 py-3 whitespace-nowrap">
                        <input type="checkbox" class="produk-check w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" value="<?= $p['id'] ?>" onclick="updateBulkBar()">
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">#<?= $p['id'] ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0 h-10 w-10 rounded-lg <?= $category_class == 'category-pulsa' ? 'bg-indigo-100' : ($category_class == 'category-kuota' ? 'bg-green-100' : 'bg-yellow-100') ?> flex items-center justify-center">
                                <i class="fas <?= $category_class == 'category-pulsa' ? 'fa-mobile-alt text-indigo-600' : ($category_class == 'category-kuota' ? 'fa-wifi text-green-600' : 'fa-bolt text-yellow-600') ?>"></i>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($p['nama_produk']) ?></div>
                                <div class="text-xs text-gray-500">Kode: <?= htmlspecialchars($p['kode_produk']) ?></div>
                                <div class="text-xs text-gray-500">Nominal: <?= rupiah($p['nominal']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            <?= htmlspecialchars($p['nama_kategori'] ?? '-') ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($p['provider']) ?></div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
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
                    <td class="px-4 py-3 whitespace-nowrap">
                        <form method="POST" action="" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="produk_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="status" value="<?= $p['status'] == 'active' ? 'inactive' : 'active' ?>">
                            <button type="submit" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium transition <?= $p['status'] == 'active' ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                <?= $p['status'] == 'active' ? '<i class="fas fa-check-circle mr-1"></i>' : '<i class="fas fa-times-circle mr-1"></i>' ?>
                                <?= ucfirst($p['status']) ?>
                            </button>
                        </form>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="flex items-center gap-1">
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
                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="openDeleteModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nama_produk']) ?>')" 
                                    class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 px-6 py-4 bg-white border-t border-gray-200">
        <div class="text-sm text-gray-600">
            Menampilkan <span class="font-medium"><?= $offset + 1 ?></span> - <span class="font-medium"><?= min($offset + $per_page, $total_records) ?></span> dari <span class="font-medium"><?= $total_records ?></span> produk
        </div>
        <div class="flex items-center gap-1">
            <?php
            // Build pagination URL with existing filters
            $paginationUrl = function($page) use ($search, $kategori_id, $provider, $status_filter, $per_page) {
                $params = [];
                if ($search) $params[] = "search=" . urlencode($search);
                if ($kategori_id) $params[] = "kategori_id=" . urlencode($kategori_id);
                if ($provider) $params[] = "provider=" . urlencode($provider);
                if ($status_filter) $params[] = "status=" . urlencode($status_filter);
                if ($per_page != 20) $params[] = "per_page=" . $per_page;
                $params[] = "page=" . $page;
                return "?" . implode("&", $params);
            };

            // Show max 5 page numbers around current page
            $start = max(1, $current_page - 2);
            $end = min($total_pages, $current_page + 2);

            if ($current_page > 1):
            ?>
                <a href="<?= $paginationUrl(1) ?>" class="px-3 py-2 text-sm border border-gray-300 rounded-lg text-gray-600 bg-white hover:bg-gray-50 hover:border-gray-400 transition" title="First">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="<?= $paginationUrl($current_page - 1) ?>" class="px-3 py-2 text-sm border border-gray-300 rounded-lg text-gray-600 bg-white hover:bg-gray-50 hover:border-gray-400 transition" title="Previous">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i == $current_page): ?>
                    <span class="px-3 py-2 text-sm font-medium border rounded-lg bg-indigo-600 text-white border-indigo-600"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= $paginationUrl($i) ?>" class="px-3 py-2 text-sm border border-gray-300 rounded-lg text-gray-600 bg-white hover:bg-gray-50 hover:border-gray-400 transition"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="<?= $paginationUrl($current_page + 1) ?>" class="px-3 py-2 text-sm border border-gray-300 rounded-lg text-gray-600 bg-white hover:bg-gray-50 hover:border-gray-400 transition" title="Next">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <a href="<?= $paginationUrl($total_pages) ?>" class="px-3 py-2 text-sm border border-gray-300 rounded-lg text-gray-600 bg-white hover:bg-gray-50 hover:border-gray-400 transition" title="Last">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Per halaman:</label>
            <select onchange="window.location.href='?<?= http_build_query(array_merge($_GET, ['per_page' => ''])) ?>' + this.value + '&page=1'" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <?php foreach ([10, 20, 50, 100] as $limit): ?>
                    <option value="<?= $limit ?>" <?= $per_page == $limit ? 'selected' : '' ?>><?= $limit ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php endif; ?>

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

<!-- Modal Import CSV -->
<div id="importCsvModal" class="modal">
    <div class="modal-content">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Import Data Produk (CSV)</h3>
        </div>
        <form method="POST" action="" class="p-6" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action" value="import_csv">
            
            <div class="space-y-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-medium text-blue-800">Format CSV:</h4>
                        <button type="button" onclick="downloadTemplate()" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                            <i class="fas fa-download mr-1"></i> Download Template
                        </button>
                    </div>
                    <p class="text-sm text-blue-700">Kolom: <code>kategori,kode_produk,nama_produk,provider,nominal,harga_jual,harga_modal,status</code></p>
                    <p class="text-sm text-blue-700 mt-1"><b>Kategori:</b> pulsa/kuota/listrik (atau 1/2/3)</p>
                    <p class="text-sm text-blue-700"><b>Kode produk:</b> opsional, kalau kosong auto-generate</p>
                    <p class="text-sm text-blue-700"><b>Status:</b> active / inactive</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pilih File CSV *</label>
                    <input type="file" name="csv_file" accept=".csv" required
                           class="input-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none">
                </div>
                
                <div class="flex items-center gap-2">
                    <i class="fas fa-info-circle text-gray-400"></i>
                    <p class="text-sm text-gray-500">Produk dengan kode yang sama akan di-update, produk baru akan ditambahkan</p>
                </div>
            </div>
            
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeModal('importCsvModal')" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                    Batal
                </button>
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition">
                    <i class="fas fa-file-import mr-2"></i>
                    Import
                </button>
            </div>
        </form>
    </div>
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

<!-- ═══════════════════════════════════════════
     KELOLA PRODUK - JavaScript
═══════════════════════════════════════════ -->
<script>
// ═══════════════════════════════════════════════════════
//  MODAL FUNCTIONS
// ═══════════════════════════════════════════════════════
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

// Download CSV Template
function downloadTemplate() {
    const template = 'kategori,kode_produk,nama_produk,provider,nominal,harga_jual,harga_modal,status\n' +
        'pulsa,TSEL-5RB,Pulsa Telkomsel 5rb,Telkomsel,5000000,6000,5500,active\n' +
        'kuota,TLK-5GB,Internet 5GB 30hr,Telkomsel,5368709120,55000,52000,active\n' +
        'listrik,TOKEN-20RB,Token Listrik 20rb,PLN,20000000,22500,20000,active\n' +
        'kuota,,Internet Indosat 10GB,Indosat,10737418240,95000,90000,inactive';
    
    const blob = new Blob([template], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'template_produk.csv';
    a.click();
    window.URL.revokeObjectURL(url);
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
            showToast('Harga jual harus lebih besar atau sama dengan harga modal!', 'error');
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

// ═══════════════════════════════════════════════════════
//  AUTO-SUBMIT FILTER on change (desktop only)
// ═══════════════════════════════════════════════════════
document.querySelectorAll('.hide-mobile form[method="GET"] select, .hide-mobile form[method="GET"] input[type="text"]')
    .forEach(el => el.addEventListener('change', function() {
        this.closest('form').submit();
    }));

// Debounce untuk search input
let searchTimeout;
document.querySelector('.hide-mobile input[name="search"]')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.closest('form').submit();
    }, 500);
});

// ═══════════════════════════════════════════
// BULK ACTIONS
// ═══════════════════════════════════════════
function toggleAll(source) {
    document.querySelectorAll('.produk-check').forEach(cb => cb.checked = source.checked);
    updateBulkBar();
}

function updateBulkBar() {
    const checked = document.querySelectorAll('.produk-check:checked');
    const bar = document.getElementById('bulkActionBar');
    const count = document.getElementById('selectedCount');
    bar.style.display = checked.length > 0 ? 'flex' : 'none';
    count.textContent = checked.length;

    // Update select all checkbox state
    const allCbs = document.querySelectorAll('.produk-check');
    const selectAll = document.getElementById('selectAll');
    if (allCbs.length > 0) {
        selectAll.checked = checked.length === allCbs.length;
        selectAll.indeterminate = checked.length > 0 && checked.length < allCbs.length;
    }
}

function clearSelection() {
    document.querySelectorAll('.produk-check').forEach(cb => cb.checked = false);
    updateBulkBar();
}

async function bulkAction(action) {
    const checked = document.querySelectorAll('.produk-check:checked');
    if (checked.length === 0) return;

    const ids = Array.from(checked).map(cb => cb.value);
    const csrf = document.querySelector('input[name="csrf_token"]').value;

    const messages = {
        'active': 'mengaktifkan',
        'inactive': 'menonaktifkan',
        'delete': 'menghapus'
    };

    if (action === 'delete') {
        if (!confirm(`Yakin ingin menghapus ${ids.length} produk?\n\nProduk yang sudah memiliki transaksi tidak bisa dihapus.`)) {
            return;
        }
    } else {
        if (!confirm(`${messages[action]} ${ids.length} produk?`)) {
            return;
        }
    }

    const formData = new FormData();
    formData.append('ids', ids.join(','));
    formData.append('action', action);
    formData.append('csrf_token', csrf);

    try {
        const response = await fetch('api_bulk_produk.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message || 'Terjadi kesalahan', 'error');
        }
    } catch (err) {
        showToast('Gagal terhubung ke server', 'error');
        console.error(err);
    }
}

// Bulk Price Modal
function showBulkPriceModal() {
    const checked = document.querySelectorAll('.produk-check:checked');
    if (checked.length === 0) {
        showToast('Pilih produk terlebih dahulu', 'error');
        return;
    }
    document.getElementById('bulkPriceModal').classList.add('active');
    document.getElementById('bulkPriceCount').textContent = checked.length;
}

function closeBulkPriceModal() {
    document.getElementById('bulkPriceModal').classList.remove('active');
}

async function submitBulkPrice() {
    const checked = document.querySelectorAll('.produk-check:checked');
    if (checked.length === 0) return;

    const ids = Array.from(checked).map(cb => cb.value);
    const csrf = document.querySelector('input[name="csrf_token"]').value;
    const increaseType = document.getElementById('bulkIncreaseType').value;
    const increaseValue = parseFloat(document.getElementById('bulkIncreaseValue').value);
    const updateField = document.getElementById('bulkUpdateField').value;

    if (!increaseValue || increaseValue <= 0) {
        showToast('Masukkan nilai yang valid', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('ids', ids.join(','));
    formData.append('action', 'update_price');
    formData.append('csrf_token', csrf);
    formData.append('increase_type', increaseType);
    formData.append('increase_value', increaseValue);
    formData.append('update_field', updateField);

    try {
        const response = await fetch('api_bulk_produk.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            showToast(data.message, 'success');
            closeBulkPriceModal();
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message || 'Terjadi kesalahan', 'error');
        }
    } catch (err) {
        showToast('Gagal terhubung ke server', 'error');
        console.error(err);
    }
}
</script>

<?php
include 'layout_footer.php';
$conn->close();
?>

<!-- Bulk Price Update Modal -->
<div class="bulk-price-modal" id="bulkPriceModal">
    <div class="bulk-price-content">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
            <h3 style="font-size:1.1rem;font-weight:700;color:#1e293b;">
                <i class="fas fa-tag" style="color:#6366f1;margin-right:0.5rem;"></i>Update Harga Bulk
            </h3>
            <button onclick="closeBulkPriceModal()" style="background:none;border:none;cursor:pointer;font-size:1.25rem;color:#94a3b8;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <p style="font-size:0.875rem;color:#64748b;margin-bottom:1.25rem;">
            <span id="bulkPriceCount">0</span> produk dipilih
        </p>

        <div style="space-y:1rem;">
            <div>
                <label style="display:block;font-size:0.813rem;font-weight:600;color:#374151;margin-bottom:0.375rem;">Tipe Update</label>
                <select id="bulkUpdateField" style="width:100%;padding:0.625rem;border:1px solid #e2e8f0;border-radius:0.5rem;font-size:0.875rem;">
                    <option value="harga_jual">Harga Jual</option>
                    <option value="harga_modal">Harga Modal</option>
                    <option value="both">Keduanya</option>
                </select>
            </div>

            <div>
                <label style="display:block;font-size:0.813rem;font-weight:600;color:#374151;margin-bottom:0.375rem;">Metode</label>
                <select id="bulkIncreaseType" style="width:100%;padding:0.625rem;border:1px solid #e2e8f0;border-radius:0.5rem;font-size:0.875rem;">
                    <option value="percent">Persen (%)</option>
                    <option value="fixed">Nominal Tetap (Rp)</option>
                </select>
            </div>

            <div>
                <label style="display:block;font-size:0.813rem;font-weight:600;color:#374151;margin-bottom:0.375rem;">Nilai</label>
                <input type="number" id="bulkIncreaseValue" placeholder="Contoh: 5 atau 1000" min="0" step="any"
                    style="width:100%;padding:0.625rem;border:1px solid #e2e8f0;border-radius:0.5rem;font-size:0.875rem;">
            </div>
        </div>

        <div style="display:flex;gap:0.75rem;margin-top:1.5rem;">
            <button onclick="closeBulkPriceModal()" style="flex:1;padding:0.75rem;border:1px solid #e2e8f0;border-radius:0.5rem;background:white;color:#64748b;font-weight:600;cursor:pointer;">
                Batal
            </button>
            <button onclick="submitBulkPrice()" style="flex:1;padding:0.75rem;border:none;border-radius:0.5rem;background:#6366f1;color:white;font-weight:600;cursor:pointer;">
                <i class="fas fa-save"></i> Update
            </button>
        </div>
    </div>
</div>

<script>
// Close modal on outside click
document.getElementById('bulkPriceModal').addEventListener('click', function(e) {
    if (e.target === this) closeBulkPriceModal();
});
// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeBulkPriceModal();
});
</script>