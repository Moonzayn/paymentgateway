<?php
/**
 * Test Simple QR Generator
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'simple_qr.php';

echo "<h3>Simple QR Test</h3>";

$qr = new SimpleQRCode();

// Test with TOTP otpauth URL
$otpauth = 'otpauth://totp/Test:user@example.com?secret=ABC123&issuer=Test';

$result = $qr->generate($otpauth, 200);

echo "Result: " . (strpos($result, 'data:image') === 0 ? "OK" : "FAILED") . "<br>";
echo "Length: " . strlen($result) . "<br><br>";

if (strpos($result, 'data:image') === 0) {
    echo "<img src='$result' style='border:2px solid #333;width:200px;height:200px;'>";
} else {
    echo "FAILED: $result";
}
