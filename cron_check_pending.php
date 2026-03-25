<?php
/**
 * Cron Job: Check Pending Transactions
 *
 * Usage: Run via cron or browser
 * Schedule: Every 5-15 minutes
 *
 * Examples:
 *   Browser: https://yoursite.com/cron_check_pending.php?key=YOUR_SECRET_KEY
 *   CLI: php cron_check_pending.php
 *   Cron: (every 5 min) php /path/to/cron_check_pending.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/digiflazz.php';

// Security: Set a secret key for browser access
define('CRON_SECRET_KEY', getPengaturan('cron_secret_key') ?: 'your-secret-key-here');

// Check if called from browser with valid key
if (php_sapi_name() !== 'cli') {
    $key = $_GET['key'] ?? '';
    if ($key !== CRON_SECRET_KEY) {
        http_response_code(403);
        die('Access denied');
    }
}

echo "=== Checking Pending Transactions ===" . PHP_EOL;
echo "Started: " . date('Y-m-d H:i:s') . PHP_EOL;

$conn = koneksi();
$updated = 0;
$failed = 0;

// Get all pending pulsa/kuota transactions (max 50 at a time)
$stmt = $conn->prepare("
    SELECT t.*, p.kode_produk
    FROM transaksi t
    LEFT JOIN produk p ON t.produk_id = p.id
    WHERE t.status = 'pending'
      AND t.jenis_transaksi IN ('pulsa', 'kuota')
      AND t.ref_id IS NOT NULL
      AND t.ref_id != ''
    ORDER BY t.created_at ASC
    LIMIT 50
");
$stmt->execute();
$pendingTransactions = $stmt->get_result();

echo "Found: " . $pendingTransactions->num_rows . " pending transactions" . PHP_EOL;

if ($pendingTransactions->num_rows == 0) {
    echo "No pending transactions to check." . PHP_EOL;
    exit;
}

$df = new DigiflazzAPI();

while ($trx = $pendingTransactions->fetch_assoc()) {
    $trxId = $trx['id'];
    $refId = $trx['ref_id'];
    $noTujuan = $trx['no_tujuan'];
    $kodeProduk = $trx['kode_produk'] ?? '';

    echo PHP_EOL . "Checking TX #$trxId (ref: $refId)... ";

    if (empty($kodeProduk) || empty($noTujuan)) {
        echo "SKIP (missing product code or destination)";
        $failed++;
        continue;
    }

    // Format phone for API
    $customerNo = formatPhoneForDigiflazz($noTujuan);

    // Check status with Digiflazz
    $result = $df->buyPulsa($kodeProduk, $customerNo, $refId);

    $newStatus = $result['status'];
    $newMessage = $result['message'];
    $newSn = $result['sn'] ?? null;

    echo "Status: $newStatus (RC: " . ($result['rc'] ?? 'N/A') . ") ";

    if ($newStatus !== $trx['status']) {
        // Update transaction
        $stmtUpdate = $conn->prepare("
            UPDATE transaksi
            SET status = ?,
                keterangan = ?,
                server_id = ?,
                api_response = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $apiResponse = json_encode($result);
        $stmtUpdate->bind_param("ssssi", $newStatus, $newMessage, $newSn, $apiResponse, $trxId);
        $stmtUpdate->execute();

        // If still pending after 24 hours, mark as suspect
        $createdAt = strtotime($trx['created_at']);
        $hoursOld = (time() - $createdAt) / 3600;

        if ($newStatus === 'pending' && $hoursOld > 24) {
            $suspectNote = "[SUSPECT - H+1+] " . $newMessage;
            $stmtSuspect = $conn->prepare("UPDATE transaksi SET keterangan = ? WHERE id = ?");
            $stmtSuspect->bind_param("si", $suspectNote, $trxId);
            $stmtSuspect->execute();
            echo "MARKED SUSPECT ";
        }

        $updated++;
        echo "UPDATED";
    } else {
        echo "No change";
    }
}

echo PHP_EOL . PHP_EOL;
echo "=== Summary ===" . PHP_EOL;
echo "Updated: $updated" . PHP_EOL;
echo "Skipped: $failed" . PHP_EOL;
echo "Finished: " . date('Y-m-d H:i:s') . PHP_EOL;

// Clean up old suspect transactions (older than 7 days, still pending)
$stmtClean = $conn->prepare("
    UPDATE transaksi
    SET status = 'failed',
        keterangan = CONCAT(keterangan, ' [AUTO-EXPIRED setelah 7 hari]')
    WHERE status = 'pending'
      AND keterangan LIKE '%[SUSPECT%'
      AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmtClean->execute();
$expiredCount = $stmtClean->affected_rows;
if ($expiredCount > 0) {
    echo "Auto-expired $expiredCount old suspect transactions" . PHP_EOL;
}

$conn->close();
echo "Done!" . PHP_EOL;
