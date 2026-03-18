<?php
/**
 * API v1 - POS Transactions Endpoint
 * Method: GET (list) / POST (create)
 * Header: Authorization: Bearer <api_key>
 */

require_once __DIR__ . '/../config.php';

$currentUser = requireAuth();
$conn = koneksi();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get POS transactions
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    $storeId = (int)($_GET['store_id'] ?? 0);
    $status = $_GET['status'] ?? '';

    $userId = $currentUser['user_id'];
    $role = $currentUser['role'];

    // Build query conditions
    $conditions = [];
    $params = [];
    $types = '';

    // Non-admin users can only see their own transactions or their store's transactions
    if ($role !== 'admin' && $role !== 'superadmin') {
        $conditions[] = "tp.user_id = ?";
        $params[] = $userId;
        $types .= 'i';
    }

    if ($storeId > 0) {
        $conditions[] = "tp.store_id = ?";
        $params[] = $storeId;
        $types .= 'i';
    }

    if (!empty($status)) {
        $conditions[] = "tp.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Get total count
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM transaksi_pos tp $whereClause");
    if (!empty($params)) {
        $stmtCount->bind_param($types, ...$params);
    }
    $stmtCount->execute();
    $resultCount = $stmtCount->get_result();
    $totalRow = $resultCount->fetch_assoc();
    $total = $totalRow['total'];

    // Get transactions
    $query = "
        SELECT tp.*, s.nama_toko
        FROM transaksi_pos tp
        LEFT JOIN stores s ON tp.store_id = s.id
        $whereClause
        ORDER BY tp.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        // Get transaction details
        $stmtDetail = $conn->prepare("SELECT * FROM transaksi_pos_detail WHERE transaksi_id = ?");
        $stmtDetail->bind_param("i", $row['id']);
        $stmtDetail->execute();
        $resultDetail = $stmtDetail->get_result();

        $details = [];
        while ($detail = $resultDetail->fetch_assoc()) {
            $details[] = [
                'id' => $detail['id'],
                'produk_id' => $detail['produk_id'],
                'nama_produk' => $detail['nama_produk'],
                'harga' => (float)$detail['harga'],
                'jumlah' => (int)$detail['jumlah'],
                'subtotal' => (float)$detail['subtotal']
            ];
        }

        $transactions[] = [
            'id' => $row['id'],
            'no_invoice' => $row['no_invoice'],
            'store_id' => $row['store_id'],
            'nama_toko' => $row['nama_toko'],
            'total' => (float)$row['total'],
            'bayar' => (float)$row['bayar'],
            'kembalian' => (float)$row['kembalian'],
            'status' => $row['status'],
            'metode_bayar' => $row['metode_bayar'],
            'details' => $details,
            'created_at' => $row['created_at']
        ];
    }

    $conn->close();

    apiSuccess([
        'transactions' => $transactions,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ], 'Daftar transaksi POS');

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new POS transaction
    $input = json_decode(file_get_contents('php://input'), true);

    $storeId = (int)($input['store_id'] ?? 0);
    $items = $input['items'] ?? [];
    $metodeBayar = $input['metode_bayar'] ?? 'tunai';
    $bayar = (float)($input['bayar'] ?? 0);

    // Validation
    if (empty($items) || !is_array($items)) {
        apiError('Item diperlukan', 'VALIDATION_ERROR');
    }

    if ($storeId <= 0) {
        apiError('Store ID diperlukan', 'VALIDATION_ERROR');
    }

    $userId = $currentUser['user_id'];
    $role = $currentUser['role'];

    // Verify user has access to this store
    if ($role !== 'admin' && $role !== 'superadmin') {
        $stmtCheck = $conn->prepare("SELECT id FROM store_users WHERE user_id = ? AND store_id = ?");
        $stmtCheck->bind_param("ii", $userId, $storeId);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if ($resultCheck->num_rows === 0) {
            apiError('Akses ditolak ke store ini', 'ACCESS_DENIED', 403);
        }
    }

    // Calculate total
    $total = 0;
    foreach ($items as $item) {
        $harga = (float)($item['harga'] ?? 0);
        $jumlah = (int)($item['jumlah'] ?? 1);
        $total += $harga * $jumlah;
    }

    // Check payment
    if ($bayar < $total) {
        apiError('Pembayaran kurang', 'INSUFFICIENT_PAYMENT');
    }

    $kembalian = $bayar - $total;
    $noInvoice = 'POS' . date('YmdHis') . rand(100, 999);

    // Insert transaction
    $stmt = $conn->prepare("
        INSERT INTO transaksi_pos (no_invoice, store_id, user_id, total, bayar, kembalian, metode_bayar, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
    ");
    $stmt->bind_param("siiddss", $noInvoice, $storeId, $userId, $total, $bayar, $kembalian, $metodeBayar);
    $stmt->execute();

    $transaksiId = $conn->insert_id;

    // Insert transaction details
    foreach ($items as $item) {
        $produkId = (int)$item['produk_id'];
        $namaProduk = $item['nama_produk'] ?? 'Unknown';
        $harga = (float)$item['harga'];
        $jumlah = (int)$item['jumlah'];
        $subtotal = $harga * $jumlah;

        $stmtDetail = $conn->prepare("
            INSERT INTO transaksi_pos_detail (transaksi_id, produk_id, nama_produk, harga, jumlah, subtotal)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmtDetail->bind_param("iisdii", $transaksiId, $produkId, $namaProduk, $harga, $jumlah, $subtotal);
        $stmtDetail->execute();
    }

    $conn->close();

    apiSuccess([
        'id' => $transaksiId,
        'no_invoice' => $noInvoice,
        'total' => $total,
        'bayar' => $bayar,
        'kembalian' => $kembalian,
        'status' => 'completed'
    ], 'Transaksi berhasil');

} else {
    apiError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}
