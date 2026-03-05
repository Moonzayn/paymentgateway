<?php
/**
 * Mark notification as read
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$conn = koneksi();
$id = intval($_GET['id'] ?? 0);
$type = $_GET['type'] ?? '';

if ($id > 0 && in_array($type, ['deposit', 'chat'])) {
    if ($type === 'deposit') {
        // Already handled by deposit system
    } elseif ($type === 'chat') {
        $conn->query("UPDATE chat_messages SET is_read = 1 WHERE id = $id");
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
