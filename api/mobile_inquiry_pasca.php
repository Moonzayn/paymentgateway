<?php
/**
 * API: Inquiry Pascabayar (PLN Postpaid, HP, PDAM, TV, Internet, BPJS, Multifinance, dll)
 * Method: POST
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/digiflazz.php';

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $user_id = intval($data['user_id'] ?? 0);
    $customer_no = preg_replace('/[^0-9]/', '', $data['customer_no'] ?? '');
    $product_code = trim($data['product_code'] ?? '');

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID diperlukan!']);
        exit;
    }

    if (empty($customer_no)) {
        echo json_encode(['success' => false, 'message' => 'Nomor pelanggan wajib diisi!']);
        exit;
    }

    if (empty($product_code)) {
        echo json_encode(['success' => false, 'message' => 'Kode produk wajib dipilih!']);
        exit;
    }

    $conn = koneksi();
    $ref_id = generateDigiflazzRefId('PSI');

    $df = new DigiflazzAPI();
    $result = $df->inquiryPasca($product_code, $customer_no, $ref_id);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'ref_id' => $ref_id,
                'tr_id' => $result['ref_id'],
                'customer_no' => $result['customer_no'],
                'name' => $result['name'],
                'period' => $result['period'],
                'nominal' => $result['nominal'],
                'admin' => $result['admin'],
                'price' => $result['price'],
                'selling_price' => $result['selling_price'],
                'noref' => $result['noref'],
                'code' => $result['code'],
                'desc' => $result['desc'],
                'rc' => $result['rc'],
                'message' => $result['message'],
                'status' => 'pending', // inquiry = pending, baru setelah payment berubah
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message'],
            'data' => [
                'rc' => $result['rc'],
                'customer_no' => $customer_no,
                'code' => $product_code,
            ]
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}