<?php
/**
 * Test Local QR Generator
 */
require_once 'local_qr.php';

header('Content-Type: text/html');

$qr = new LocalQRCode();
$otpauth = 'otpauth://totp/PPOB%20Express:admin@ppobexpress?secret=TEST12345678&issuer=PPOB%20Express';
$result = $qr->generate($otpauth, 200);

echo "<h3>Local QR Test</h3>";
echo "<img src='$result' style='width:200px;height:200px;border:2px solid #333;'>";
echo "<br><br>";
echo "<p>Result type: " . (strpos($result, 'data:image') === 0 ? 'SUCCESS - Base64 PNG' : 'FAILED') . "</p>";
