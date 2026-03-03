<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = koneksi();

$stmt = $conn->query("
    SELECT 
        c.store_id,
        s.nama_toko,
        (SELECT message FROM chat_messages WHERE store_id = c.store_id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM chat_messages WHERE store_id = c.store_id ORDER BY created_at DESC LIMIT 1) as last_time,
        (SELECT COUNT(*) FROM chat_messages WHERE store_id = c.store_id AND is_read = 0 AND sender_role != 'superadmin') as unread
    FROM (SELECT DISTINCT store_id FROM chat_messages WHERE store_id IS NOT NULL) c
    LEFT JOIN stores s ON c.store_id = s.id
    ORDER BY last_time DESC
");

$conversations = [];
while ($row = $stmt->fetch_assoc()) {
    $conversations[] = $row;
}

$stmt = $conn->query("SELECT COUNT(*) as total_unread FROM chat_messages WHERE is_read = 0 AND sender_role != 'superadmin'");
$total_unread = $stmt->fetch_assoc()['total_unread'] ?? 0;

echo json_encode([
    'success' => true,
    'conversations' => $conversations,
    'total_unread' => $total_unread
]);
