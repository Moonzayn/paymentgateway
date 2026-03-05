<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = koneksi();

// Get all distinct store_ids (including NULL to show users without store)
$stmt = $conn->query("
    SELECT DISTINCT store_id FROM chat_messages
");

$conversations = [];
$total_unread = 0;

while ($row = $stmt->fetch_assoc()) {
    $store_id = $row['store_id'];

    // Get store name
    $store_name = 'Unknown Store';
    if ($store_id) {
        $storeStmt = $conn->prepare("SELECT nama_toko FROM stores WHERE id = ?");
        $storeStmt->bind_param("i", $store_id);
        $storeStmt->execute();
        $storeResult = $storeStmt->get_result();
        if ($storeResult->num_rows > 0) {
            $store = $storeResult->fetch_assoc();
            $store_name = $store['nama_toko'];
        }
    } else {
        // For NULL store_id, get the sender name
        $senderStmt = $conn->query("SELECT sender_name FROM chat_messages WHERE store_id IS NULL ORDER BY created_at DESC LIMIT 1");
        if ($senderStmt->num_rows > 0) {
            $sender = $senderStmt->fetch_assoc();
            $store_name = $sender['sender_name'] . ' (No Store)';
        }
    }

    // Get last message
    if ($store_id) {
        $msgStmt = $conn->prepare("SELECT message, created_at FROM chat_messages WHERE store_id = ? ORDER BY created_at DESC LIMIT 1");
        $msgStmt->bind_param("i", $store_id);
    } else {
        $msgStmt = $conn->query("SELECT message, created_at FROM chat_messages WHERE store_id IS NULL ORDER BY created_at DESC LIMIT 1");
    }

    if ($store_id) {
        $msgStmt->execute();
        $msgResult = $msgStmt->get_result();
    } else {
        $msgResult = $msgStmt;
    }

    $last_message = '';
    $last_time = null;
    if ($msgResult->num_rows > 0) {
        $msg = $msgResult->fetch_assoc();
        $last_message = $msg['message'];
        $last_time = $msg['created_at'];
    }

    // Get unread count for this conversation
    if ($store_id) {
        $unreadStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM chat_messages WHERE store_id = ? AND is_read = 0 AND sender_role != 'superadmin'");
        $unreadStmt->bind_param("i", $store_id);
        $unreadStmt->execute();
        $unreadResult = $unreadStmt->get_result();
    } else {
        $unreadResult = $conn->query("SELECT COUNT(*) as cnt FROM chat_messages WHERE store_id IS NULL AND is_read = 0 AND sender_role != 'superadmin'");
    }

    $unread = $unreadResult->fetch_assoc()['cnt'] ?? 0;
    $total_unread += $unread;

    $conversations[] = [
        'store_id' => $store_id,
        'nama_toko' => $store_name,
        'last_message' => $last_message,
        'last_time' => $last_time,
        'unread' => $unread
    ];
}

// Sort by last_time descending
usort($conversations, function($a, $b) {
    if (!$a['last_time']) return 1;
    if (!$b['last_time']) return -1;
    return strtotime($b['last_time']) - strtotime($a['last_time']);
});

echo json_encode([
    'success' => true,
    'conversations' => $conversations,
    'total_unread' => $total_unread
]);
