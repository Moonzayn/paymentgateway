<?php
/**
 * Digiflazz API Integration Helper
 *
 * Documentation: https://api.digiflazz.com/v1
 */

class DigiflazzAPI {
    private $username;
    private $apiKey;
    private $apiUrl = 'https://api.digiflazz.com/v1';
    private $testing = false; // Set true untuk simulasi

    public function setTesting($testing) {
        $this->testing = $testing;
        return $this;
    }

    public function isTesting() {
        return $this->testing;
    }

    // RC Code messages
    const RC_MESSAGES = [
        '00' => 'Transaksi Sukses',
        '01' => 'Timeout',
        '02' => 'Transaksi Gagal',
        '03' => 'Transaksi Pending',
        '40' => 'Payload Error',
        '41' => 'Signature tidak valid',
        '42' => 'Username tidak valid',
        '43' => 'SKU tidak ditemukan/Non-Aktif',
        '44' => 'Saldo tidak cukup',
        '45' => 'IP tidak dikenali',
        '49' => 'Ref ID tidak unik',
        '50' => 'Transaksi Tidak Ditemukan',
        '51' => 'Nomor Tujuan Diblokir',
        '52' => 'Prefix Tidak Sesuai Operator',
        '53' => 'Produk Seller Tidak Tersedia',
        '54' => 'Nomor Tujuan Salah',
        '55' => 'Produk Sedang Gangguan',
        '57' => 'Jumlah Digit Kurang/Lebih',
        '58' => 'Sedang Cut Off',
        '60' => 'Tagihan belum tersedia',
        '61' => 'Belum pernah deposit',
        '62' => 'Seller sedang gangguan',
        '64' => 'Tarik tiket gagal',
        '66' => 'Cut Off (Perbaikan Sistem)',
        '67' => 'Seller belum ter-verifikasi',
        '68' => 'Stok habis',
        '69' => 'Harga seller lebih besar',
        '70' => 'Timeout Dari Biller',
        '80' => 'Akun diblokir oleh Seller',
        '81' => 'Seller diblokir oleh Anda',
        '82' => 'Akun belum ter-verifikasi',
        '83' => 'Limitasi pricelist',
        '84' => 'Nominal tidak valid',
        '85' => 'Limitasi transaksi',
        '86' => 'Limitasi PLN inquiry',
        '87' => 'E-money wajib kelipatan Rp 1.000',
        '99' => 'DF Router Issue',
    ];

    public function __construct($username = null, $apiKey = null) {
        // Load credentials from pengaturan if not provided
        if ($username === null || $apiKey === null) {
            $this->username = getPengaturan('digiflazz_username') ?: 'pehaduD7V7ro';
            $this->apiKey   = getPengaturan('digiflazz_api_key') ?: '3c737104-00a2-5561-b992-341577aa87f5';
            $this->testing  = getPengaturan('digiflazz_testing') === 'true';
        } else {
            $this->username = $username;
            $this->apiKey   = $apiKey;
        }
    }

    /**
     * Generate MD5 signature
     * Formula: md5(username + apiKey + data)
     */
    private function generateSign($data) {
        return md5($this->username . $this->apiKey . $data);
    }

    /**
     * Make HTTP POST request to Digiflazz API
     */
    private function request($endpoint, $payload) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/' . $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error'   => 'CURL Error: ' . $error,
                'rc'      => 'CURL',
                'message' => 'Gagal terhubung ke server Digiflazz'
            ];
        }

        $data = json_decode($response, true);

        // Check if response has data wrapper
        if (isset($data['data'])) {
            return $data['data'];
        }

        return $data;
    }

    /**
     * Get RC message from code
     */
    public static function getRcMessage($rc) {
        return self::RC_MESSAGES[$rc] ?? 'Unknown RC: ' . $rc;
    }

    /**
     * Determine transaction status from RC code
     */
    public static function getStatusFromRc($rc) {
        if ($rc === '00') return 'success';
        if ($rc === '03' || $rc === '99') return 'pending';
        return 'failed';
    }

    /**
     * Check if transaction should rollback (no deduction counted)
     */
    public static function shouldRollback($rc) {
        // These RC codes should NOT be counted as a transaction
        $noTransaksi = ['40', '41', '42', '43', '44', '45', '49', '61', '64', '66', '67', '80', '81', '82', '83', '86'];
        return in_array($rc, $noTransaksi);
    }

    /**
     * Cek Saldo Deposit
     */
    public function cekSaldo() {
        $payload = [
            'cmd'      => 'deposit',
            'username' => $this->username,
            'sign'     => $this->generateSign('depo')
        ];

        $result = $this->request('cek-saldo', $payload);

        if (isset($result['deposit'])) {
            return [
                'success'  => true,
                'deposit'  => (float) $result['deposit'],
                'rc'       => $result['rc'] ?? '00',
                'message'  => $result['message'] ?? 'Success'
            ];
        }

        return [
            'success' => false,
            'deposit' => 0,
            'rc'      => $result['rc'] ?? 'UNKNOWN',
            'message' => $result['message'] ?? 'Gagal mengambil saldo'
        ];
    }

    /**
     * Beli Pulsa / Topup Prepaid
     *
     * @param string $skuCode     Kode produk Digiflazz (e.g. TSEL5)
     * @param string $customerNo   Nomor HP tujuan
     * @param string $refId       Unique reference ID
     * @param bool   $testing     Set true untuk mode testing
     * @return array
     */
    public function buyPulsa($skuCode, $customerNo, $refId, $testing = null) {
        $payload = [
            'username'     => $this->username,
            'buyer_sku_code' => $skuCode,
            'customer_no' => $customerNo,
            'ref_id'      => $refId,
            'sign'        => $this->generateSign($refId)
        ];

        if ($testing !== null) {
            $payload['testing'] = $testing;
        } elseif ($this->testing) {
            $payload['testing'] = true;
        }

        $result = $this->request('transaction', $payload);

        // Handle different response formats
        $rc = $result['rc'] ?? null;
        $apiStatus = strtolower($result['status'] ?? '');

        // Digiflazz sometimes returns status text instead of RC
        if ($rc === null || $rc === '') {
            if ($apiStatus === 'sukses' || $apiStatus === 'success') {
                $rc = '00';
            } elseif ($apiStatus === 'pending') {
                $rc = '03';
            } else {
                $rc = '02'; // Default to gagal
            }
        }

        $status = self::getStatusFromRc($rc);
        $sn     = $result['sn'] ?? null;

        return [
            'success'         => in_array($status, ['success', 'pending']),
            'status'          => $status,
            'rc'              => $rc,
            'rc_message'      => self::getRcMessage($rc),
            'message'         => $result['message'] ?: ($result['status'] ?? self::getRcMessage($rc)),
            'ref_id'          => $result['ref_id'] ?? $refId,
            'customer_no'     => $result['customer_no'] ?? $customerNo,
            'sku_code'        => $result['buyer_sku_code'] ?? $skuCode,
            'sn'              => $sn,
            'price'           => isset($result['price']) ? (float) $result['price'] : null,
            'buyer_last_saldo' => isset($result['buyer_last_saldo']) ? (float) $result['buyer_last_saldo'] : null,
            'should_rollback' => self::shouldRollback($rc),
            'raw_response'    => $result
        ];
    }

    /**
     * Inquiry Tagihan Pascabayar
     */
    public function inquiryPasca($buyerSkuCode, $customerNo, $refId) {
        $payload = [
            'commands'    => 'inq-pasca',
            'username'    => $this->username,
            'buyer_sku_code' => $buyerSkuCode,
            'customer_no' => $customerNo,
            'ref_id'      => $refId,
            'sign'        => $this->generateSign($refId)
        ];

        $result = $this->request('transaction', $payload);

        $rc     = $result['rc'] ?? 'UNKNOWN';
        $status = self::getStatusFromRc($rc);

        return [
            'success'  => $status === 'success',
            'status'   => $status,
            'rc'       => $rc,
            'rc_message' => self::getRcMessage($rc),
            'message'  => $result['message'] ?? self::getRcMessage($rc),
            'ref_id'   => $refId,
            'customer_no' => $customerNo,
            'buyer_sku_code' => $buyerSkuCode,
            'name'     => $result['name'] ?? null,
            'bill'     => isset($result['bill']) ? (float) $result['bill'] : null,
            'admin'    => isset($result['admin']) ? (float) $result['admin'] : null,
            'message'  => $result['message'] ?? null,
            'raw_response' => $result
        ];
    }

    /**
     * Bayar Tagihan Pascabayar
     */
    public function bayarPasca($buyerSkuCode, $customerNo, $refId) {
        $payload = [
            'commands'    => 'pay-pasca',
            'username'    => $this->username,
            'buyer_sku_code' => $buyerSkuCode,
            'customer_no' => $customerNo,
            'ref_id'      => $refId,
            'sign'        => $this->generateSign($refId)
        ];

        $result = $this->request('transaction', $payload);

        $rc     = $result['rc'] ?? 'UNKNOWN';
        $status = self::getStatusFromRc($rc);

        return [
            'success'  => in_array($status, ['success', 'pending']),
            'status'   => $status,
            'rc'       => $rc,
            'rc_message' => self::getRcMessage($rc),
            'message'  => $result['message'] ?? self::getRcMessage($rc),
            'ref_id'   => $result['ref_id'] ?? $refId,
            'sn'       => $result['sn'] ?? null,
            'price'    => isset($result['price']) ? (float) $result['price'] : null,
            'buyer_last_saldo' => isset($result['buyer_last_saldo']) ? (float) $result['buyer_last_saldo'] : null,
            'should_rollback' => self::shouldRollback($rc),
            'raw_response' => $result
        ];
    }

    /**
     * Cek Status Transaksi
     */
    public function cekStatus($buyerSkuCode, $customerNo, $refId) {
        $payload = [
            'commands'    => 'status-pasca',
            'username'    => $this->username,
            'buyer_sku_code' => $buyerSkuCode,
            'customer_no' => $customerNo,
            'ref_id'      => $refId,
            'sign'        => $this->generateSign($refId)
        ];

        $result = $this->request('transaction', $payload);

        $rc     = $result['rc'] ?? 'UNKNOWN';
        $status = self::getStatusFromRc($rc);

        return [
            'success'  => $status === 'success',
            'status'   => $status,
            'rc'       => $rc,
            'rc_message' => self::getRcMessage($rc),
            'message'  => $result['message'] ?? self::getRcMessage($rc),
            'ref_id'   => $refId,
            'raw_response' => $result
        ];
    }

    /**
     * Inquiry PLN (Cek Validasi ID PLN)
     */
    public function inquiryPln($customerNo) {
        $payload = [
            'username'   => $this->username,
            'customer_no' => $customerNo,
            'sign'      => $this->generateSign($customerNo)
        ];

        $result = $this->request('inquiry-pln', $payload);

        $rc     = $result['rc'] ?? 'UNKNOWN';
        $status = self::getStatusFromRc($rc);

        return [
            'success'  => $status === 'success',
            'status'   => $status,
            'rc'       => $rc,
            'rc_message' => self::getRcMessage($rc),
            'message'  => $result['message'] ?? self::getRcMessage($rc),
            'customer_no' => $customerNo,
            'name'     => $result['name'] ?? null,
            'segment_power' => $result['segment_power'] ?? null,
            'power_amount'  => $result['power_amount'] ?? null,
            'raw_response'  => $result
        ];
    }

    /**
     * Get Price List
     */
    public function priceList($cmd = 'prepaid') {
        $payload = [
            'cmd'      => $cmd,
            'username' => $this->username,
            'sign'     => $this->generateSign('pricelist')
        ];

        $result = $this->request('price-list', $payload);

        if (isset($result['data'])) {
            return [
                'success' => true,
                'data'    => $result['data']
            ];
        }

        return [
            'success' => false,
            'data'    => [],
            'rc'      => $result['rc'] ?? 'UNKNOWN',
            'message' => $result['message'] ?? 'Gagal mengambil pricelist'
        ];
    }
}

/**
 * Helper: Generate unique ref_id untuk transaksi
 */
function generateDigiflazzRefId($prefix = 'TX') {
    return $prefix . date('ymdHis') . rand(100, 999);
}

/**
 * Helper: Format nomor HP untuk Digiflazz (selalu 08xx)
 */
function formatPhoneForDigiflazz($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Convert 62xx -> 08xx
    if (substr($phone, 0, 2) === '62') {
        return '0' . substr($phone, 2);
    }
    // Already 08xx or +62xx -> just 08xx
    if (substr($phone, 0, 1) === '0') {
        return $phone;
    }
    // 8xx -> 08xx
    return '0' . $phone;
}

/**
 * Helper: Format nomor HP dari format Digiflazz (62xx -> 08xx)
 */
function formatPhoneFromDigiflazz($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 2) === '62') {
        return '0' . substr($phone, 2);
    }
    return $phone;
}
