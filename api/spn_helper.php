<?php
// Access Credentials: 
// Key: V-Q1LLH3TVPRUWX1EP 
// Token: ljh7yJ1P29jDvuzmwpaEd8ZQrxaYVk4m 

// For more details, please refer to the official documentation: https://docs.vansoncash.com
class SPNHelper {
    private $apiKey;
    private $apiToken;
    private $baseUrl;
    private $callbackUrl;

    public function __construct($apiKey = null, $apiToken = null, $isSandbox = true) {
        $this->apiKey = $apiKey ?: 'V-Q1LLH3TVPRUWX1EP';
        $this->apiToken = $apiToken ?: 'ljh7yJ1P29jDvuzmwpaEd8ZQrxaYVk4m';
        $this->baseUrl = $isSandbox 
            ? 'https://api.vansoncash.com' 
            : 'https://api.vansoncash.com';
        $this->callbackUrl = '';
    }

    public function setCallbackUrl($url) {
        $this->callbackUrl = $url;
    }

    private function generateSignature($body) {
        return hash_hmac('sha512', $this->apiKey . $body, $this->apiToken);
    }

    public function createQRIS($reference, $amount, $expiryMinutes = 30, $viewName = 'PPOB Express') {
        $data = [
            'reference' => $reference,
            'amount' => (int)$amount,
            'expiry_minutes' => (int)$expiryMinutes,
            'view_name' => $viewName
        ];

        if (!empty($this->callbackUrl)) {
            $data['additional_info'] = [
                'callback' => $this->callbackUrl
            ];
        }

        $body = json_encode($data);
        $signature = $this->generateSignature($body);

        $ch = curl_init($this->baseUrl . '/api/transaction/qris');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-Key: ' . $this->apiKey,
                'X-Token: ' . $this->apiToken,
                'X-Signature: ' . $signature,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => $error
            ];
        }

        $result = json_decode($response, true);
        
        // Handle response structure: response_code, response_message, response_data
        $success = isset($result['response_code']) && $result['response_code'] == 200;
        
        if ($success && isset($result['response_data']['qris']['image'])) {
            return [
                'success' => true,
                'http_code' => $httpCode,
                'data' => [
                    'reference' => $result['response_data']['merchant_ref'] ?? $reference,
                    'qris_image' => $result['response_data']['qris']['image'],
                    'qris_string' => $result['response_data']['qris']['content'] ?? '',
                    'expired_at' => $result['response_data']['expired_date'] ?? null,
                    'status' => $result['response_data']['status'] ?? 'pending'
                ]
            ];
        }
        
        return [
            'success' => false,
            'http_code' => $httpCode,
            'message' => $result['response_message'] ?? 'Failed to generate QRIS',
            'raw' => $result
        ];
    }

    public function checkStatus($reference) {
        $data = [
            'reference' => $reference
        ];

        $body = json_encode($data);
        $signature = $this->generateSignature($body);

        $ch = curl_init($this->baseUrl . '/api/transaction/status');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-Key: ' . $this->apiKey,
                'X-Token: ' . $this->apiToken,
                'X-Signature: ' . $signature,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => $error
            ];
        }

        $result = json_decode($response, true);

        $success = isset($result['response_code']) && $result['response_code'] == 200;
        
        return [
            'success' => $success,
            'http_code' => $httpCode,
            'data' => $result['response_data'] ?? $result
        ];
    }

    public function cancelTransaction($reference) {
        $data = [
            'reference' => $reference
        ];

        $body = json_encode($data);
        $signature = $this->generateSignature($body);

        $ch = curl_init($this->baseUrl . '/api/transaction/cancel');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-Key: ' . $this->apiKey,
                'X-Token: ' . $this->apiToken,
                'X-Signature: ' . $signature,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => $error
            ];
        }

        $result = json_decode($response, true);

        return [
            'success' => isset($result['response_code']) && $result['response_code'] == 200,
            'http_code' => $httpCode,
            'data' => $result['response_data'] ?? $result
        ];
    }
}

function generateQRIS($reference, $amount, $expiry = 30, $viewName = 'PPOB Express', $callbackUrl = '') {
    $spn = new SPNHelper();
    if (!empty($callbackUrl)) {
        $spn->setCallbackUrl($callbackUrl);
    }
    return $spn->createQRIS($reference, $amount, $expiry, $viewName);
}

function checkQRISStatus($reference) {
    $spn = new SPNHelper();
    return $spn->checkStatus($reference);
}

function cancelQRIS($reference) {
    $spn = new SPNHelper();
    return $spn->cancelTransaction($reference);
}
