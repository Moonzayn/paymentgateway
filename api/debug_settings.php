<?php
/**
 * Check Digiflazz settings
 */

error_reporting(0);
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config.php';

$conn = koneksi();
$stmt = $conn->prepare("SELECT nama_key, nilai FROM pengaturan WHERE nama_key LIKE 'digiflazz%'");
$stmt->execute();
$result = $stmt->get_result();

$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['nama_key']] = $row['nama_key'] === 'digiflazz_api_key' || $row['nama_key'] === 'digiflazz_dev_key' 
        ? substr($row['nilai'], 0, 15) . '...' 
        : $row['nilai'];
}

echo json_encode([
    'digiflazz_username' => getPengaturan('digiflazz_username') ?: 'pehaduD7V7ro',
    'digiflazz_testing' => getPengaturan('digiflazz_testing'),
    'digiflazz_api_key' => substr(getPengaturan('digiflazz_api_key') ?: '', 0, 15) . '...',
    'digiflazz_dev_key' => substr(getPengaturan('digiflazz_dev_key') ?: '', 0, 15) . '...',
    'hardcoded_dev_key' => 'dev-7e3c8000-6531-11ec-b233-31d3fcbe4c0e',
], JSON_PRETTY_PRINT);