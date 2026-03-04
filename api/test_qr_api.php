<?php
/**
 * Test Local QR Generation API
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Local QR Generator Test</h3>";

// Include the QR class directly (not the whole file)
require_once 'local_qr.php';

echo "Class loaded OK<br>";

// Test 1: Simple text
$qr = new LocalQRCode();
echo "Object created OK<br>";

$otpauth = 'otpauth://totp/Test:user@example.com?secret=ABC123&issuer=Test';
echo "Generating QR...<br>";

$result = $qr->generate($otpauth, 200);

echo "Result length: " . strlen($result) . "<br>";
echo "Result prefix: " . substr($result, 0, 30) . "<br>";

if (strpos($result, 'data:image') === 0) {
    echo "<img src='$result' style='width:200px;height:200px;border:2px solid #333;'>";
    echo "<br><br>SUCCESS!";
} else {
    echo "<br>FAILED - Result is not valid image data";
}
