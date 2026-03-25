<?php
/**
 * API Endpoint: Check Status Pending Transactions
 *
 * GET ?ids=1,2,3
 * Returns JSON with latest status from Digiflazz
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/digiflazz.php';
setCorsHeaders();

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    apiError('Unauthorized', 'UNAUTHORIZED', 401);
}

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get transaction IDs
$idsParam = $_GET['ids'] ?? '';
if (empty($idsParam)) {
    apiError('No IDs provided', 'BAD_REQUEST', 400);
}

$ids = array_filter(array_map('intval', explode(',', $idsParam)));
if (empty($ids)) {
    apiError('Invalid IDs', 'BAD_REQUEST', 400);
}

// Limit to 20 at a time
$ids = array_slice($ids, 0, 20);

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

$stmt = $conn->prepare("
    SELECT t.id, t.no_invoice, t.ref_id, t.status, t.no_tujuan, t.jenis_transaksi,
           p.kode_produk, t.created_at, t.updated_at, t.keterangan
    FROM transaksi t
    LEFT JOIN produk p ON t.produk_id = p.id
    WHERE t.id IN ($placeholders)
      AND t.status = 'pending'
      AND t.jenis_transaksi IN ('pulsa', 'kuota')
");
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$transactions = $stmt->get_result();

$results = [];
$df = new DigiflazzAPI();

while ($trx = $transactions->fetch_assoc()) {
    $result = [
        'id' => $trx['id'],
        'invoice' => $trx['no_invoice'],
        'status' => $trx['status'],
        'is_suspect' => false,
    ];

    // Only check if has ref_id and product code
    if (!empty($trx['ref_id']) && !empty($trx['kode_produk'])) {
        $customerNo = formatPhoneForDigiflazz($trx['no_tujuan']);

        // Call Digiflazz API
        $apiResult = $df->buyPulsa($trx['kode_produk'], $customerNo, $trx['ref_id']);

        $newStatus = $apiResult['status'];

        // Update if status changed
        if ($newStatus !== $trx['status']) {
            $keterangan = $apiResult['message'];
            $sn = $apiResult['sn'] ?? null;

            // Check if suspect (pending > 24 hours)
            $hoursOld = (time() - strtotime($trx['created_at'])) / 3600;
            if ($newStatus === 'pending' && $hoursOld > 24) {
                $result['is_suspect'] = true;
                $keterangan = "[SUSPECT - H+1+] " . $keterangan;
            }

            // Update in database
            $stmtUpdate = $conn->prepare("
                UPDATE transaksi
                SET status = ?,
                    keterangan = ?,
                    server_id = ?,
                    api_response = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $apiResponse = json_encode($apiResult);
            $stmtUpdate->bind_param("ssssi", $newStatus, $keterangan, $sn, $apiResponse, $trx['id']);
            $stmtUpdate->execute();

            $result['status'] = $newStatus;
            $result['message'] = $keterangan;
            $result['sn'] = $sn;
        }

        $result['rc'] = $apiResult['rc'] ?? null;
    }

    $results[] = $result;
}

apiSuccess($results, 'Status checked');

$conn->close();
