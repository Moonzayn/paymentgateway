<?php
// Simple debug test
$rawInput = file_get_contents('php://input');

$log = "=== " . date('Y-m-d H:i:s') . " ===\n";
$log .= "RAW: [" . strlen($rawInput) . "] " . $rawInput . "\n";
$log .= "METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";

file_put_contents('C:\laragon\www\payment\api\debug_log.txt', $log, FILE_APPEND);

if (empty($rawInput)) {
    // Try alternate method
    $rawInput = file_get_contents('php://input');
}

echo json_encode([
    'success' => true,
    'raw' => $rawInput,
    'method' => $_SERVER['REQUEST_METHOD']
]);