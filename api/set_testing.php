<?php
/**
 * Toggle Digiflazz Testing Mode
 * Usage: api_set_testing.php?mode=true or ?mode=false
 */

error_reporting(0);
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config.php';

$mode = $_GET['mode'] ?? 'true';
$conn = koneksi();

// Check if exists
$stmt = $conn->prepare("SELECT * FROM pengaturan WHERE nama_key = 'digiflazz_testing'");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt = $conn->prepare("UPDATE pengaturan SET nilai = ? WHERE nama_key = 'digiflazz_testing'");
    $stmt->bind_param("s", $mode);
} else {
    $stmt = $conn->prepare("INSERT INTO pengaturan (nama_key, nilai) VALUES ('digiflazz_testing', ?)");
    $stmt->bind_param("s", $mode);
}
$stmt->execute();

// Also set dev key if testing mode
if ($mode === 'true') {
    $devKey = 'dev-7e3c8000-6531-11ec-b233-31d3fcbe4c0e';
    
    $stmt = $conn->prepare("SELECT * FROM pengaturan WHERE nama_key = 'digiflazz_dev_key'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE pengaturan SET nilai = ? WHERE nama_key = 'digiflazz_dev_key'");
        $stmt->bind_param("s", $devKey);
    } else {
        $stmt = $conn->prepare("INSERT INTO pengaturan (nama_key, nilai) VALUES ('digiflazz_dev_key', ?)");
        $stmt->bind_param("s", $devKey);
    }
    $stmt->execute();
}

echo json_encode([
    'success' => true,
    'message' => 'Testing mode set to: ' . $mode,
    'digiflazz_testing' => $mode,
    'digiflazz_dev_key_set' => ($mode === 'true')
]);