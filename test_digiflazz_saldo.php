<?php
/**
 * Digiflazz CEK SALDO API Test
 * Endpoint: https://api.digiflazz.com/v1/ceksaldo
 */

$username = 'pehaduD7V7ro';
$api_key = 'dev-7e3c8000-6531-11ec-b233-31d3fcbe4c0e';

// Generate signature: MD5(username + api_key + 'pb')
$sign = md5($username . $api_key . 'pb');

$payload = [
    'username' => $username,
    'sign' => $sign
];

echo "=== DIGIFLAZZ CEK SALDO TEST ===\n";
echo "URL: https://api.digiflazz.com/v1/ceksaldo\n";
echo "Username: $username\n";
echo "Sign (MD5): $sign\n";
echo "Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

// cURL request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.digiflazz.com/v1/ceksaldo');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
if ($error) {
    echo "cURL Error: $error\n";
} else {
    echo "Response:\n";
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo $response . "\n";
    }
}
