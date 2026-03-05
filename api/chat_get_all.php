<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = koneksi();

// Get all distinct room_ids and store_ids
$stmt = $conn->query("
    SELECT DISTINCT room_id, store_id FROM chat_messages
    WHERE room_id IS NOT NULL
    UNION
    SELECT DISTINCT NULL as room_id, store_id FROM chat_messages
    WHERE store_id IS NOT NULL
");

$conversations = [];
$total_unread = 0;
$display_name = '';

while ($row = $stmt->fetch_assoc()) {
    $room_id = $row['room_id'];
    $store_id = $row['store_id'];

    $user_name = 'Unknown User';
    $user_id = null;

    if ($room_id) {
        $user_id = str_replace('user_', '', $room_id);
        $userStmt = $conn->prepare("SELECT username, nama_lengkap FROM users WHERE id = ?");
        $userStmt->bind_param("i", $user_id);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $display_name = 'Unknown User';
        if ($userResult->num_rows > 0) {
            $user = $userResult->fetch_assoc();
            $display_name = $user['username'];
        }
        
        $msgStmt = $conn->prepare("SELECT message, created_at FROM chat_messages WHERE room_id = ? ORDER BY created_at DESC LIMIT 1");
        $msgStmt->bind_param("s", $room_id);
        
        $unreadStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM chat_messages WHERE room_id = ? AND is_read = 0 AND sender_role != 'superadmin'");
        $unreadStmt->bind_param("s", $room_id);
    } elseif ($store_id) {
        $storeStmt = $conn->prepare("SELECT s.nama_toko, u.username FROM stores s LEFT JOIN store_users su ON s.id = su.store_id LEFT JOIN users u ON su.user_id = u.id WHERE s.id = ?");
        $storeStmt->bind_param("i", $store_id);
        $storeStmt->execute();
        $storeResult = $storeStmt->get_result();
        $display_name = 'Toko';
        if ($storeResult->num_rows > 0) {
            $store = $storeResult->fetch_assoc();
            $store_name = $store['nama_toko'];
            $username = $store['username'] ?? '';
            $display_name = $username ? "$username - $store_name" : $store_name;
        }
        
        $msgStmt = $conn->prepare("SELECT message, created_at FROM chat_messages WHERE store_id = ? ORDER BY created_at DESC LIMIT 1");
        $msgStmt->bind_param("i", $store_id);
        
        $unreadStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM chat_messages WHERE store_id = ? AND is_read = 0 AND sender_role != 'superadmin'");
        $unreadStmt->bind_param("i", $store_id);
    } else {
        continue;
    }

    $msgStmt->execute();
    $msgResult = $msgStmt->get_result();

    $last_message = '';
    $last_time = null;
    if ($msgResult->num_rows > 0) {
        $msg = $msgResult->fetch_assoc();
        $last_message = $msg['message'];
        $last_time = $msg['created_at'];
    }

    $unreadStmt->execute();
    $unreadResult = $unreadStmt->get_result();
    $unread = $unreadResult->fetch_assoc()['cnt'] ?? 0;
    $total_unread += $unread;

    $conversations[] = [
        'room_id' => $room_id,
        'store_id' => $store_id,
        'user_id' => $user_id,
        'user_name' => $display_name,
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
