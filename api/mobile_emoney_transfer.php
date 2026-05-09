<?php
/**
 * API: E-Money Transfer (DANA, GoPay, OVO, ShopeePay, LinkAja, dll)
 * Method: POST
 * Usage: Flutter app untuk transfer e-money
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$logFile = 'C:\laragon\www\payment\api\debug_emoney.log';

function debug($msg) {
    global $logFile;
    @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

try {
    while (ob_get_level()) { ob_end_clean(); }

    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    $rawInput = file_get_contents('php://input');
    debug("INPUT: " . $rawInput);

    $data = json_decode($rawInput, true);
    if (!$data) {
        debug("INVALID JSON: " . json_last_error_msg());
        echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit;
    }

    $user_id = intval($data['user_id'] ?? 0);
    $customer_no = preg_replace('/[^0-9]/', '', $data['customer_no'] ?? '');
    $product_code = trim($data['product_code'] ?? '');
    $ref_id = trim($data['ref_id'] ?? '');
    $selling_price = floatval($data['selling_price'] ?? 0);
    $product_name = trim($data['product_name'] ?? '');

    debug("PARAMS: user_id=$user_id, customer_no=$customer_no, product_code=$product_code, selling_price=$selling_price");

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID diperlukan!']);
        exit;
    }

    if (empty($customer_no)) {
        echo json_encode(['success' => false, 'message' => 'Nomor HP wajib diisi!']);
        exit;
    }

    if (empty($product_code)) {
        echo json_encode(['success' => false, 'message' => 'Kode produk wajib diisi!']);
        exit;
    }

    if (empty($ref_id)) {
        echo json_encode(['success' => false, 'message' => 'Ref ID diperlukan!']);
        exit;
    }

    if ($selling_price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Harga tidak valid!']);
        exit;
    }

    $conn = new mysqli('localhost', 'root', '', 'db_ppob');
    if ($conn->connect_error) {
        debug("DB ERROR: " . $conn->connect_error);
        echo json_encode(['success' => false, 'message' => 'DB error']);
        exit;
    }

    $conn->query("ALTER TABLE transaksi MODIFY COLUMN jenis_transaksi ENUM('pulsa', 'kuota', 'listrik', 'transfer', 'game', 'deposit', 'admin', 'emoney') NOT NULL");
    
    $colCheck = $conn->query("SHOW COLUMNS FROM transaksi LIKE 'nama_produk'");
    if ($colCheck->num_rows == 0) {
        $conn->query("ALTER TABLE transaksi ADD COLUMN nama_produk VARCHAR(100) NULL AFTER api_response");
    }

    $stmt = $conn->prepare('SELECT saldo FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        debug("USER NOT FOUND: id=$user_id");
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }

    $saldo = floatval($user['saldo']);
    $harga = $selling_price;

    if ($saldo < $harga) {
        echo json_encode([
            'success' => false,
            'message' => 'Saldo tidak mencukupi!',
            'data' => [
                'status' => 'failed',
                'saldo' => $saldo,
                'harga' => $harga
            ]
        ]);
        $conn->close();
        exit;
    }

    $invoice = 'INV' . date('YmdHis') . rand(1000, 9999);
    $nominal = intval($selling_price);
    $keterangan = 'Menunggu...';
    $status = 'pending';
    $saldo_sesudah = $saldo - $harga;

    $conn->query("UPDATE users SET saldo = $saldo_sesudah WHERE id = $user_id");
    debug("User balance deducted: $saldo -> $saldo_sesudah");

    require_once dirname(__DIR__) . '/config.php';
    require_once dirname(__DIR__) . '/digiflazz.php';
    $df = new DigiflazzAPI();

    $apiResult = $df->buyPulsa($product_code, $customer_no, $ref_id);
    
    $apiStatus = $apiResult['status'] ?? 'failed';
    $apiRc = $apiResult['rc'] ?? '';
    $apiMessage = $apiResult['message'] ?? 'Transaksi Gagal';

    $errorMappings = [
        '44' => 'Layanan sedang gangguan. Silakan coba lagi nanti.',
        '02' => 'Transaksi gagal. Silakan coba lagi.',
        '01' => 'Transaksi timeout. Silakan coba lagi.',
        '40' => 'Terjadi kesalahan sistem.',
        '41' => 'Terjadi kesalahan sistem.',
        '42' => 'Terjadi kesalahan sistem.',
        '43' => 'Produk tidak tersedia.',
        '45' => 'Layanan sedang gangguan.',
        '53' => 'Produk tidak tersedia.',
        '55' => 'Layanan sedang gangguan.',
        '61' => 'Layanan sedang gangguan.',
        '62' => 'Seller sedang gangguan.',
        '64' => 'Terjadi kesalahan.',
        '66' => 'Sistem sedang maintenance.',
        '67' => 'Akun belum ter-verifikasi.',
        '68' => 'Stok produk habis.',
        '80' => 'Akun diblokir.',
        '81' => 'Nomor diblokir.',
        '99' => 'Layanan sedang gangguan.',
    ];
    
    if (in_array(strtolower($apiStatus), ['success', 'sukses', 'Sukses'])) {
        $status = 'success';
        $keterangan = $apiMessage;
        debug("SUCCESS: " . $apiMessage);
    } else {
        $shouldRollback = in_array($apiRc, ['40', '41', '42', '43', '44', '45', '49', '61', '64', '66', '67', '80', '81', '82', '83', '86']);
        
        $userFriendlyMessage = $errorMappings[$apiRc] ?? 'Terjadi kesalahan. Silakan coba lagi.';
        
        if ($shouldRollback) {
            $conn->query("UPDATE users SET saldo = $saldo WHERE id = $user_id");
            $saldo_sesudah = $saldo;
            $status = 'failed';
            $keterangan = $userFriendlyMessage;
            debug("ROLLBACK - balance restored due to RC: $apiRc");
        } else {
            $status = 'failed';
            $keterangan = $userFriendlyMessage;
            debug("FAILED (no rollback): " . $apiMessage);
        }
    }

    $sql = "INSERT INTO transaksi (user_id, produk_id, no_invoice, ref_id, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan, api_response) 
VALUES ($user_id, NULL, '$invoice', '$ref_id', 'emoney', '$customer_no', $nominal, $harga, 0, $harga, $saldo, $saldo_sesudah, '$status', '$keterangan', '" . json_encode($apiResult) . "')";
    
    if (!$conn->query($sql)) {
        debug("INSERT FAILED: " . $conn->error);
    }
    
    $transaksi_id = $conn->insert_id;
    $conn->close();

    debug("DONE: status=$status, reason=$keterangan");

    echo json_encode([
        'success' => true,
        'message' => $keterangan,
        'data' => [
            'transaksi_id' => $transaksi_id,
            'no_invoice' => $invoice,
            'invoice' => $invoice,
            'ref_id' => $ref_id,
            'status' => $status,
            'reason' => $keterangan,
            'message' => $keterangan,
            'no_tujuan' => $customer_no,
            'nominal' => $nominal,
            'produk' => $product_name,
            'harga' => $harga,
            'saldo_sesudah' => $saldo_sesudah,
            'sn' => $apiResult['sn'] ?? null
        ]
    ]);

} catch (Exception $e) {
    debug("EXCEPTION: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}