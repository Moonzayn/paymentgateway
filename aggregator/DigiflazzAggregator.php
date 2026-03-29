<?php
/**
 * Digiflazz Aggregator Implementation
 */

require_once __DIR__ . '/AggregatorInterface.php';

class DigiflazzAggregator implements AggregatorInterface {
    private string $username;
    private string $apiKey;
    private string $apiUrl = 'https://api.digiflazz.com/v1';
    private int $timeout = 30;

    public function __construct(string $username, string $apiKey) {
        $this->username = $username;
        $this->apiKey = $apiKey;
    }

    public function getName(): string {
        return 'Digiflazz';
    }

    /**
     * Generate MD5 signature
     */
    public function generateSign(string $data): string {
        return md5($this->username . $this->apiKey . $data);
    }

    /**
     * Make API request
     */
    private function request(string $endpoint, array $payload): array {
        error_log("Digiflazz API: $endpoint - " . json_encode($payload));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/' . $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Set true in production

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Digiflazz cURL Error: " . $error);
            throw new Exception("cURL Error: " . $error);
        }

        error_log("Digiflazz Response [$httpCode]: " . $response);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Digiflazz JSON Error: " . json_last_error_msg());
            throw new Exception("Invalid JSON response");
        }

        // Digiflazz response wrapped in "data" key
        if (isset($data['data'])) {
            return $data['data'];
        }

        // If no data key, return the whole response
        return $data;
    }

    /**
     * Cek saldo deposit
     */
    public function checkBalance(): float {
        $sign = $this->generateSign('depo');

        $payload = [
            'cmd' => 'deposit',
            'username' => $this->username,
            'sign' => $sign
        ];

        $response = $this->request('cek-saldo', $payload);

        if (isset($response['deposit'])) {
            return floatval($response['deposit']);
        }

        return 0;
    }

    /**
     * Beli produk (pulsa/paket data)
     */
    public function purchase(string $sku, string $customerNo, string $refId, bool $testing = false): array {
        // MODE TESTING - return simulasi sukses
        if ($testing) {
            error_log("Digiflazz: TESTING MODE - Returning simulated success");
            return [
                'ref_id' => $refId,
                'customer_no' => $customerNo,
                'buyer_sku_code' => $sku,
                'status' => 'Sukses',
                'rc' => '00',
                'message' => 'Testing Mode - Simulasi Sukses',
                'sn' => 'TEST' . date('His'),
                'price' => 5285,
                'buyer_last_saldo' => 100000,
            ];
        }

        // MODE REAL - call API
        $sign = $this->generateSign($refId);

        $payload = [
            'username' => $this->username,
            'buyer_sku_code' => $sku,
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign
        ];

        error_log("Digiflazz purchase: sku=$sku, customer=$customerNo, refId=$refId, sign=$sign");

        $response = $this->request('transaction', $payload);

        return [
            'ref_id' => $refId,
            'customer_no' => $customerNo,
            'buyer_sku_code' => $sku,
            'status' => $response['status'] ?? 'Gagal',
            'rc' => $response['rc'] ?? '99',
            'message' => $response['message'] ?? '',
            'sn' => $response['sn'] ?? '',
            'price' => floatval($response['price'] ?? 0),
            'buyer_last_saldo' => floatval($response['buyer_last_saldo'] ?? 0),
        ];
    }

    /**
     * Cek status transaksi
     */
    public function checkStatus(string $refId): array {
        $sign = $this->generateSign($refId);

        $payload = [
            'username' => $this->username,
            'buyer_sku_code' => '',
            'customer_no' => '',
            'ref_id' => $refId,
            'sign' => $sign
        ];

        $response = $this->request('transaction', $payload);

        return [
            'ref_id' => $refId,
            'status' => $response['status'] ?? 'Gagal',
            'rc' => $response['rc'] ?? '99',
            'message' => $response['message'] ?? '',
            'sn' => $response['sn'] ?? '',
            'price' => floatval($response['price'] ?? 0),
            'buyer_last_saldo' => floatval($response['buyer_last_saldo'] ?? 0),
        ];
    }

    /**
     * Get price list
     */
    public function getPriceList(string $type = 'prepaid'): array {
        $sign = $this->generateSign('pricelist');

        $payload = [
            'cmd' => $type,
            'username' => $this->username,
            'sign' => $sign
        ];

        $response = $this->request('price-list', $payload);

        if (isset($response[0])) {
            return $response;
        }

        return [];
    }

    /**
     * Get RC Message
     */
    public static function getRCMessage(string $rc): string {
        $messages = [
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
            '99' => 'DF Router Issue',
        ];

        return $messages[$rc] ?? "Unknown Error (RC: $rc)";
    }
}
