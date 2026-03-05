<?php
/**
 * Mark all notifications as read
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$conn = koneksi();

// Mark all chats as read
$conn->query("UPDATE chat_messages SET is_read = 1 WHERE is_read = 0");

echo json_encode(['success' => true]);
