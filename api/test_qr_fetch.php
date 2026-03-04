<?php
/**
 * Test QR Fetch
 */
header('Content-Type: text/html');

$data = $_GET['data'] ?? 'otpauth://totp/Test:test@test.com?secret=ABC123&issuer=Test';

echo "Testing QR with data: " . htmlspecialchars($data) . "<br><br>";

// Try to fetch QR
$apis = [
    'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=',
    'https://quickchart.io/qr?size=200x200&text='
];

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'ignore_errors' => true
    ]
]);

foreach ($apis as $i => $api) {
    $url = $api . urlencode($data);
    echo "Trying API $i: " . substr($url, 0, 80) . "...<br>";

    $response = @file_get_contents($url, false, $context);

    if ($response) {
        echo "Response length: " . strlen($response) . "<br>";

        if (strlen($response) > 100) {
            echo "SUCCESS! <br>";
            echo "<img src='data:image/png;base64," . base64_encode($response) . "' style='width:200px;height:200px;border:2px solid red;'>";
        } else {
            echo "Response: " . htmlspecialchars(substr($response, 0, 200)) . "<br>";
        }
    } else {
        echo "FAILED - No response<br>";
    }
    echo "<br>";
}
