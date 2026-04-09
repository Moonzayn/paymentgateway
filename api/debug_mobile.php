<?php
/**
 * Debug: Test Mobile API
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

// Just test if config and DB connection works
$conn = koneksi();

echo json_encode([
    'success' => true,
    'message' => 'API is working',
    'db_connected' => ($conn !== null),
    'session_user_id' => $_SESSION['user_id'] ?? 'not set'
]);
