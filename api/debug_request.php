<?php
/**
 * Debug: Full request logging
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Log to file
$logFile = __DIR__ . '/debug_log.txt';
$log = date('Y-m-d H:i:s') . " - IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
$log .= "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
$log .= "Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'none') . "\n";
$log .= "Input: " . file_get_contents('php://input') . "\n";
$log .= "GET: " . json_encode($_GET) . "\n";
$log .= "POST: " . json_encode($_POST) . "\n";
$log .= "Session: " . json_encode($_SESSION ?? ['no_session' => true]) . "\n";
$log .= "-----------------------------------\n";

file_put_contents($logFile, $log, FILE_APPEND);

// Return debug info
echo json_encode([
    'success' => true,
    'message' => 'Request received!',
    'debug' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none',
        'input' => file_get_contents('php://input'),
        'remote_addr' => $_SERVER['REMOTE_ADDR'],
        'http_host' => $_SERVER['HTTP_HOST'] ?? 'none'
    ]
]);
