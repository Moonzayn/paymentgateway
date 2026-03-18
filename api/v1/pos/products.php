<?php
/**
 * API v1 - POS Products Endpoint
 * Method: GET
 * Header: Authorization: Bearer <api_key>
 */

require_once __DIR__ . '/../config.php';

$currentUser = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

$conn = koneksi();

$storeId = (int)($_GET['store_id'] ?? 0);
$categoryId = (int)($_GET['category_id'] ?? 0);
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 50);
$offset = ($page - 1) * $limit;

$userId = $currentUser['user_id'];
$role = $currentUser['role'];

// Build query conditions
$conditions = ["pp.status = 'active'"];
$params = [];
$types = '';

// Non-admin users can only see products from their stores
if ($role !== 'admin' && $role !== 'superadmin') {
    if ($storeId > 0) {
        $conditions[] = "hps.store_id = ?";
        $params[] = $storeId;
        $types .= 'i';
    } else {
        $conditions[] = "hps.store_id IN (SELECT store_id FROM store_users WHERE user_id = ?)";
        $params[] = $userId;
        $types .= 'i';
    }
} elseif ($storeId > 0) {
    $conditions[] = "hps.store_id = ?";
    $params[] = $storeId;
    $types .= 'i';
}

if ($categoryId > 0) {
    $conditions[] = "pp.kategori_id = ?";
    $params[] = $categoryId;
    $types .= 'i';
}

if (!empty($search)) {
    $conditions[] = "(pp.nama_produk LIKE ? OR pp.kode_produk LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count
$countQuery = "SELECT COUNT(DISTINCT pp.id) as total
    FROM produk_pos pp
    LEFT JOIN harga_ppob_store hps ON pp.id = hps.produk_id
    $whereClause";

$stmtCount = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$totalRow = $resultCount->fetch_assoc();
$total = $totalRow['total'];

// Get products
$query = "
    SELECT DISTINCT pp.*, kp.nama_kategori, hps.harga_jual as harga_store
    FROM produk_pos pp
    LEFT JOIN kategori_produk_pos kp ON pp.kategori_id = kp.id
    LEFT JOIN harga_ppob_store hps ON pp.id = hps.produk_id
    $whereClause
    ORDER BY pp.nama_produk ASC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = [
        'id' => $row['id'],
        'kode_produk' => $row['kode_produk'],
        'nama_produk' => $row['nama_produk'],
        'kategori_id' => $row['kategori_id'],
        'nama_kategori' => $row['nama_kategori'] ?? 'Uncategorized',
        'harga_jual' => (float)($row['harga_store'] ?? $row['harga_jual']),
        'stok' => (int)$row['stok'],
        'status' => $row['status']
    ];
}

// Get categories
$stmtCat = $conn->query("SELECT * FROM kategori_produk_pos WHERE status = 'active' ORDER BY nama_kategori");
$categories = [];
while ($cat = $stmtCat->fetch_assoc()) {
    $categories[] = [
        'id' => $cat['id'],
        'nama_kategori' => $cat['nama_kategori']
    ];
}

$conn->close();

apiSuccess([
    'products' => $products,
    'categories' => $categories,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'total_pages' => ceil($total / $limit)
    ]
], 'Daftar produk POS');
