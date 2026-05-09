<?php
/**
 * API: Get Price List Postpaid / Pascabayar dari Digiflazz
 * Method: GET
 * Usage: /api/price-list.php
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/digiflazz.php';

try {
    $df = new DigiflazzAPI();
    $result = $df->priceList('passthrough');

    // Digiflazz passthrough returns raw product list
    echo json_encode([
        'success' => $result['success'] ?? false,
        'data' => $result['data'] ?? [],
        'rc' => $result['rc'] ?? 'UNKNOWN',
        'message' => $result['message'] ?? 'Gagal mengambil price list',
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'data' => [],
        'message' => 'Error: ' . $e->getMessage()
    ]);
}