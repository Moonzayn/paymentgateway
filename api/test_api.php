<?php
/**
 * Debug: Test API Connection
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'success' => true, 
    'message' => 'API is working!',
    'time' => date('Y-m-d H:i:s')
]);
