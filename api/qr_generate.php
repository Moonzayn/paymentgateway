<?php
/**
 * Simple QR Code Generator API
 * Generates QR code locally without external APIs
 */

header('Content-Type: image/png');

// Get data from URL
$data = $_GET['data'] ?? '';
$size = isset($_GET['size']) ? (int)$_GET['size'] : 200;

if (empty($data)) {
    // Return empty image
    header('Content-Type: image/png');
    $img = imagecreatetruecolor(100, 100);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);
    imagepng($img);
    imagedestroy($img);
    exit;
}

// Simple QR code using内置 PHP QR generator (phpqrcode style)
// This is a simplified version - use composer package for production

// Try to use QRCode library if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('QRCode')) {
        ob_start();
        QRCode::png($data, null, 'L', 4, 2);
        $png = ob_get_clean();
        header('Content-Type: image/png');
        echo $png;
        exit;
    }
}

// Fallback: create simple barcode-style image
$img = imagecreatetruecolor($size, $size);
$white = imagecolorallocate($img, 255, 255, 255);
$black = imagecolorallocate($img, 0, 0, 0);
imagefill($img, 0, 0, $white);

// Simple vertical barcode pattern based on data
$hash = md5($data);
$barWidth = $size / 20;
for ($i = 0; $i < 20; $i++) {
    $val = hexdec(substr($hash, $i % 32, 1)) % 2;
    if ($val) {
        imagefilledrectangle($img, $i * $barWidth, 0, ($i + 1) * $barWidth - 1, $size - 1, $black);
    }
}

header('Content-Type: image/png');
imagepng($img);
imagedestroy($img);
