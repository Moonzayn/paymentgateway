<?php
/**
 * API: E-Money/Wallet Inquiry (DANA, GoPay, ShopeePay, OVO, LinkAja, dll)
 * Method: POST
 * Usage: Flutter app untuk inquiry e-money sebelum transfer
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$logFile = 'C:\laragon\www\payment\api\debug_inquiry.log';

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
    $provider_code = strtoupper(trim($data['provider_code'] ?? ''));
    $customer_no = preg_replace('/[^0-9]/', '', $data['customer_no'] ?? '');

    debug("PARAMS: user_id=$user_id, provider_code=$provider_code, customer_no=$customer_no");

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID diperlukan!']);
        exit;
    }

    if (empty($provider_code)) {
        echo json_encode(['success' => false, 'message' => 'Provider code wajib diisi!']);
        exit;
    }

    if (empty($customer_no)) {
        echo json_encode(['success' => false, 'message' => 'Nomor HP wajib diisi!']);
        exit;
    }

    if (strlen($customer_no) < 10 || strlen($customer_no) > 13) {
        echo json_encode(['success' => false, 'message' => 'Format nomor HP tidak valid!']);
        exit;
    }

    $providerMap = [
        'DANA' => 'dana',
        'GOPAY' => 'gopay',
        'SHOPEEPAY' => 'shopeepay',
        'OVO' => 'ovo',
        'LINKAJA' => 'linkaja',
        'SHP' => 'shopeepay',
    ];

    $providerKey = $providerMap[$provider_code] ?? strtolower($provider_code);
    $buyer_sku_code = 'emoney';
    $customer_no_formatted = $providerKey . ',' . $customer_no;

    debug("SKU: $buyer_sku_code, customer_no: $customer_no_formatted");

    $conn = new mysqli('localhost', 'root', '', 'db_ppob');
    if ($conn->connect_error) {
        debug("DB ERROR: " . $conn->connect_error);
        echo json_encode(['success' => false, 'message' => 'DB error']);
        exit;
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

    require_once dirname(__DIR__) . '/config.php';
    require_once dirname(__DIR__) . '/digiflazz.php';

    $df = new DigiflazzAPI();
    $ref_id = generateDigiflazzRefId('INQ');

    $result = $df->inquiryPasca($buyer_sku_code, $customer_no_formatted, $ref_id);

    debug("API RESULT: " . json_encode($result));

    if ($result['success']) {
        $customer_name = $result['name'] ?? '';
        $price = floatval($result['price'] ?? 0);
        $admin = floatval($result['admin'] ?? 0);
        $harga = $price + $admin;

        debug("SUCCESS: name=$customer_name, harga=$harga");

        echo json_encode([
            'success' => true,
            'provider' => $provider_code,
            'customer_no' => $customer_no,
            'customer_name' => $customer_name,
            'sku_used' => strtoupper($providerKey) . 'INQ',
            'harga' => $harga,
            'price' => $price,
            'admin' => $admin,
            'ref_id' => $ref_id,
            'data' => $result
        ]);
    } else {
        $errorMsg = $result['message'] ?? 'Inquiry gagal';
        debug("FAILED: " . $errorMsg);

        echo json_encode([
            'success' => false,
            'message' => $errorMsg,
            'provider' => $provider_code,
            'customer_no' => $customer_no,
            'rc' => $result['rc'] ?? null,
            'data' => $result
        ]);
    }

    $conn->close();

} catch (Exception $e) {
    debug("EXCEPTION: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}