<?php
/**
 * API Endpoint: Bulk Actions untuk Produk
 *
 * POST /api_bulk_produk.php
 * Body: ids (comma-separated), action (active/inactive/delete), csrf_token
 */

require_once __DIR__ . '/config.php';
setCorsHeaders();

// Must be logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    apiError('Unauthorized', 'UNAUTHORIZED', 401);
}

$conn = koneksi();
$ids = $_POST['ids'] ?? '';
$action = $_POST['action'] ?? '';
$csrf = $_POST['csrf_token'] ?? '';

// Validate CSRF
if (!validateCSRFToken($csrf)) {
    apiError('Sesi tidak valid. Silakan refresh halaman.', 'INVALID_TOKEN', 400);
}

// Validate IDs
if (empty($ids)) {
    apiError('Tidak ada produk dipilih.', 'NO_SELECTION', 400);
}

$idArray = array_filter(array_map('intval', explode(',', $ids)));
if (empty($idArray)) {
    apiError('ID produk tidak valid.', 'INVALID_IDS', 400);
}

$count = count($idArray);
$placeholders = implode(',', array_fill(0, $count, '?'));
$types = str_repeat('i', $count);

switch ($action) {
    case 'active':
        $stmt = $conn->prepare("UPDATE produk SET status = 'active' WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$idArray);
        $stmt->execute();
        apiSuccess(null, "$count produk berhasil diaktifkan.");

    case 'inactive':
        $stmt = $conn->prepare("UPDATE produk SET status = 'inactive' WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$idArray);
        $stmt->execute();
        apiSuccess(null, "$count produk berhasil dinonaktifkan.");

    case 'delete':
        // Check if any product has transactions
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM transaksi WHERE produk_id IN ($placeholders)");
        $stmt->bind_param($types, ...$idArray);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['cnt'] > 0) {
            apiError('Tidak dapat menghapus produk yang sudah memiliki transaksi.', 'HAS_TRANSACTIONS', 400);
        }

        $stmt = $conn->prepare("DELETE FROM produk WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$idArray);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        apiSuccess(null, "$deleted produk berhasil dihapus.");

    case 'update_price':
        // Bulk update harga
        $increaseType = $_POST['increase_type'] ?? 'percent';
        $increaseValue = floatval($_POST['increase_value'] ?? 0);
        $updateField = $_POST['update_field'] ?? 'harga_jual';

        if ($increaseValue <= 0) {
            apiError('Nilai harus lebih dari 0.', 'INVALID_VALUE', 400);
        }

        // Get current prices
        $stmt = $conn->prepare("SELECT id, harga_jual, harga_modal FROM produk WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$idArray);
        $stmt->execute();
        $products = $stmt->get_result();

        $updated = 0;
        while ($p = $products->fetch_assoc()) {
            if ($updateField === 'harga_jual' || $updateField === 'both') {
                if ($increaseType === 'percent') {
                    $p['harga_jual'] = round($p['harga_jual'] * (1 + $increaseValue / 100));
                } else {
                    $p['harga_jual'] = $p['harga_jual'] + $increaseValue;
                }
            }
            if ($updateField === 'harga_modal' || $updateField === 'both') {
                if ($increaseType === 'percent') {
                    $p['harga_modal'] = round($p['harga_modal'] * (1 + $increaseValue / 100));
                } else {
                    $p['harga_modal'] = $p['harga_modal'] + $increaseValue;
                }
            }
            // Ensure harga_jual >= harga_modal
            if ($p['harga_jual'] < $p['harga_modal']) {
                $p['harga_jual'] = $p['harga_modal'];
            }

            $uStmt = $conn->prepare("UPDATE produk SET harga_jual = ?, harga_modal = ? WHERE id = ?");
            $uStmt->bind_param("ddi", $p['harga_jual'], $p['harga_modal'], $p['id']);
            $uStmt->execute();
            $updated++;
        }
        apiSuccess(null, "$updated produk berhasil diperbarui.");

    default:
        apiError('Action tidak valid.', 'INVALID_ACTION', 400);
}

$conn->close();
