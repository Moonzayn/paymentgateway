<?php
/**
 * API Endpoint: Beli Pulsa (AJAX)
 *
 * POST /api_beli_pulsa.php
 * Body: csrf_token, no_hp, produk_id
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/digiflazz.php';
setCorsHeaders();

// Detect mobile app request
$input = json_decode(file_get_contents('php://input'), true);
$postData = $_POST + ($input ?? []);

$isMobileApp = isset($postData['user_id']) && !empty($postData['user_id']);

// Must be logged in
if ($isMobileApp) {
    $user_id = intval($postData['user_id']);
    if ($user_id <= 0) {
        apiError('Invalid user_id', 'INVALID_USER', 400);
    }
} else {
    if (!isset($_SESSION['user_id'])) {
        apiError('Unauthorized', 'UNAUTHORIZED', 401);
    }
    $user_id = $_SESSION['user_id'];
}

$conn = koneksi();

// Rate limit
if (!checkRateLimit('purchase_pulsa', 10, 60)) {
    apiError('Terlalu banyak permintaan. Silakan tunggu sebentar.', 'RATE_LIMITED', 429);
}

// Validate CSRF (skip for mobile app)
if (!$isMobileApp) {
    $csrf = $postData['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf)) {
        apiError('Sesi tidak valid. Silakan refresh halaman.', 'INVALID_TOKEN', 400);
    }
}

// Get params
$no_hp = preg_replace('/[^0-9]/', '', $postData['no_hp'] ?? '');
$produk_id = intval($postData['produk_id'] ?? 0);

// Validate input
if (strlen($no_hp) < 10) {
    apiError('Nomor HP tidak valid (minimal 10 digit).', 'INVALID_PHONE', 400);
}
if (strlen($no_hp) > 15) {
    apiError('Nomor HP tidak valid (maksimal 15 digit).', 'INVALID_PHONE', 400);
}
if ($produk_id == 0) {
    apiError('Pilih nominal pulsa yang valid.', 'INVALID_PRODUCT', 400);
}

// Get product
$stmt = $conn->prepare("SELECT * FROM produk WHERE id = ?");
$stmt->bind_param("i", $produk_id);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();
if (!$produk) {
    apiError('Produk tidak ditemukan.', 'NOT_FOUND', 404);
}

// Check balance
$saldo = getSaldo($user_id);
if ($saldo < $produk['harga_jual']) {
    apiError('Saldo tidak mencukupi. Silakan deposit terlebih dahulu.', 'INSUFFICIENT_BALANCE', 400);
}

// Setup transaction
$ref_id = generateDigiflazzRefId('PLS');
$invoice = generateInvoice();
$harga = $produk['harga_jual'];
$customerNo = formatPhoneForDigiflazz($no_hp);
$saldo_sebelum = $saldo;
$saldo_sesudah = $saldo - $harga;

// Insert transaksi with pending status
$stmt = $conn->prepare("
    INSERT INTO transaksi
    (user_id, produk_id, no_invoice, ref_id, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan)
    VALUES (?, ?, ?, ?, 'pulsa', ?, ?, ?, 0, ?, ?, ?, 'pending', 'Menunggu response Digiflazz')
");
$stmt->bind_param(
    "iisssddddd",
    $user_id, $produk_id, $invoice, $ref_id,
    $customerNo, $produk['nominal'], $harga,
    $harga, $saldo_sebelum, $saldo_sesudah
);
$stmt->execute();
$transaksi_id = $conn->insert_id;

// Deduct balance
updateSaldo($user_id, $harga, 'kurang');

// Call Digiflazz API
$df = new DigiflazzAPI();
$apiResult = $df->buyPulsa($produk['kode_produk'], $customerNo, $ref_id);

// Determine final status
$finalStatus = $apiResult['status'];
$keterangan = $apiResult['message'];
$sn = $apiResult['sn'] ?? null;
$apiResponseJson = json_encode($apiResult);

// Update transaksi dengan hasil API
$stmt = $conn->prepare("
    UPDATE transaksi
    SET status = ?,
        keterangan = ?,
        server_id = ?,
        api_response = ?,
        updated_at = NOW()
    WHERE id = ?
");
$stmt->bind_param("ssssi", $finalStatus, $keterangan, $sn, $apiResponseJson, $transaksi_id);
$stmt->execute();

// Handle rollback if needed
if ($finalStatus === 'failed' && $apiResult['should_rollback']) {
    updateSaldo($user_id, $harga, 'tambah');
    $keterangan = "[ROLLBACK] " . $keterangan;

    $stmt = $conn->prepare("UPDATE transaksi SET keterangan = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $keterangan, $transaksi_id);
    $stmt->execute();
}

// Update session saldo
$_SESSION['saldo'] = getSaldo($user_id);

// Return response
apiSuccess([
    'transaksi_id'  => $transaksi_id,
    'invoice'       => $invoice,
    'status'        => $finalStatus,
    'sn'            => $sn,
    'rc'            => $apiResult['rc'] ?? null,
    'message'       => $keterangan,
    'jenis'         => 'pulsa',
    'produk'        => $produk['nama_produk'],
    'provider'      => $produk['provider'],
    'nominal'       => $produk['nominal'],
    'harga'         => $harga,
    'no_tujuan'     => $customerNo,
    'ref_id'        => $ref_id,
    'tanggal'       => date('Y-m-d H:i:s'),
    'should_rollback' => $apiResult['should_rollback'] ?? false,
]);

$conn->close();
