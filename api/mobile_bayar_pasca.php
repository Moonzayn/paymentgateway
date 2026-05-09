<?php
/**
 * API: Bayar Pascabayar (PLN Postpaid, HP, PDAM, TV, Internet, BPJS, Multifinance, dll)
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
    $ref_id = trim($data['ref_id'] ?? '');
    $selling_price = floatval($data['selling_price'] ?? 0);
    $product_name = trim($data['product_name'] ?? '');

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

    if (empty($ref_id)) {
        echo json_encode(['success' => false, 'message' => 'Ref ID inquiry diperlukan!']);
        exit;
    }

    if ($selling_price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Harga tidak valid!']);
        exit;
    }

    $conn = koneksi();

    // Cek saldo user
    $saldo = getSaldo($user_id);
    if ($saldo < $selling_price) {
        echo json_encode([
            'success' => false,
            'message' => 'Saldo tidak mencukupi!',
            'data' => [
                'status' => 'failed',
                'reason' => 'INSUFFICIENT_USER_BALANCE',
                'saldo' => $saldo,
                'harga' => $selling_price
            ]
        ]);
        exit;
    }

    // Generate invoice & ref_id payment
    $invoice = generateInvoice();
    $pay_ref_id = generateDigiflazzRefId('PSP');
    $saldo_sebelum = $saldo;
    $saldo_sesudah = $saldo - $selling_price;

    // Insert transaksi pending
    $stmt = $conn->prepare("INSERT INTO transaksi (user_id, produk_id, no_invoice, ref_id, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan) VALUES (?, 0, ?, ?, 'pasca', ?, ?, ?, 0, ?, ?, ?, 'pending', 'Menunggu response Digiflazz')");
    $stmt->bind_param("iisssddd", $user_id, $invoice, $pay_ref_id, $customer_no, $selling_price, $selling_price, $selling_price, $saldo_sebelum, $saldo_sesudah);
    $stmt->execute();
    $transaksi_id = $conn->insert_id;

    // Potong saldo
    updateSaldo($user_id, $selling_price, 'kurang');

    // Bayar ke Digiflazz
    $df = new DigiflazzAPI();
    $result = $df->bayarPasca($product_code, $customer_no, $ref_id);

    $rc = $result['rc'] ?? 'UNKNOWN';
    $status = $result['status'] ?? 'failed';
    $keterangan = $result['message'] ?? 'Transaksi selesai';

    // Rollback jika perlu
    if ($result['should_rollback'] ?? false) {
        updateSaldo($user_id, $selling_price, 'tambah');
        $saldo_sesudah = $saldo;
    }

    // Update transaksi
    $stmt = $conn->prepare("UPDATE transaksi SET status = ?, keterangan = ?, api_response = ? WHERE id = ?");
    $stmt->bind_param("sssi", $status, $keterangan, json_encode($result), $transaksi_id);
    $stmt->execute();

    // Success response SELALU return success:true (status ada di data.status)
    echo json_encode([
        'success' => true,
        'message' => $keterangan,
        'data' => [
            'transaksi_id' => $transaksi_id,
            'no_invoice' => $invoice,
            'ref_id' => $result['ref_id'] ?? $ref_id,
            'tr_id' => $ref_id,
            'status' => $status,
            'rc' => $rc,
            'message' => $keterangan,
            'customer_no' => $result['customer_no'] ?? $customer_no,
            'name' => $result['name'] ?? $product_name,
            'period' => $result['period'] ?? null,
            'nominal' => $result['nominal'] ?? $selling_price,
            'admin' => $result['admin'] ?? 0,
            'price' => $result['price'] ?? $selling_price,
            'selling_price' => $result['selling_price'] ?? $selling_price,
            'noref' => $result['noref'] ?? null,
            'code' => $result['code'] ?? $product_code,
            'desc' => $result['desc'] ?? null,
            'sn' => $result['sn'] ?? null,
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
