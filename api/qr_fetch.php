<?php
/**
 * QR Code Fetch - Gets QR from external API server-side
 * Then returns it to browser
 */

header('Content-Type: image/png');

// Get data
$data = $_GET['data'] ?? '';

if (empty($data)) {
    // Return empty PNG
    $img = imagecreatetruecolor(100, 100);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);
    imagepng($img);
    imagedestroy($img);
    exit;
}

// Try to fetch from external API
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

$imageData = null;

foreach ($apis as $api) {
    $url = $api . urlencode($data);
    $response = @file_get_contents($url, false, $context);

    if ($response && strlen($response) > 100 && strpos($response, '<') !== 0) {
        $imageData = $response;
        break;
    }
}

// If we got image data, return it
if ($imageData) {
    // Check if it's a valid image
    if (strpos($imageData, "\x89PNG") === 0 ||
        strpos($imageData, "\xff\xd8\xff") === 0) {
        echo $imageData;
        exit;
    }
}

// Fallback: create simple placeholder
$img = imagecreatetruecolor(200, 200);
$white = imagecolorallocate($img, 255, 255, 255);
$black = imagecolorallocate($img, 0, 0, 0);
imagefill($img, 0, 0, $white);

// Draw simple pattern
for ($i = 0; $i < 10; $i++) {
    if ($i % 2 == 0) {
        imagefilledrectangle($img, $i * 20, 0, ($i + 1) * 20 - 1, 199, $black);
    }
}

imagepng($img);
imagedestroy($img);
