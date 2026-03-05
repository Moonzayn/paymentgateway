<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$sender_role = $_SESSION['role'] ?? 'kasir';
$store_id = $_SESSION['current_store_id'] ?? null;

if ($sender_role === 'admin' || $sender_role === 'superadmin') {
    $sender_role = 'superadmin';
}

$last_id = (int)($_POST['last_id'] ?? 0);

if ($sender_role === 'superadmin') {
    $target_store_id = $_POST['store_id'] ?? null;

    if ($target_store_id !== null && $target_store_id !== '') {
        $target_store_id = (int)$target_store_id;
        $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE store_id = ? AND id > ? ORDER BY created_at ASC");
        $stmt->bind_param("ii", $target_store_id, $last_id);
    } else {
        // No store_id - get messages for users without store (sender_id = user_id OR messages without store)
        $stmt = $conn->prepare("SELECT * FROM (SELECT * FROM chat_messages WHERE (store_id IS NULL OR store_id = 0) AND id > ? ORDER BY created_at ASC) as t");
        $stmt->bind_param("i", $last_id);
    }
} else {
    // Regular user - show their messages only
    if ($store_id) {
        // User with store - show only messages for their store
        $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE store_id = ? AND id > ? ORDER BY created_at ASC");
        $stmt->bind_param("ii", $store_id, $last_id);
    } else {
        // User without store - show only messages they sent themselves
        // This prevents data leak between users
        $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE sender_id = ? AND id > ? ORDER BY created_at ASC");
        $stmt->bind_param("ii", $user_id, $last_id);
    }
}

$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

$unread_count = 0;
if ($sender_role !== 'superadmin') {
    if ($store_id) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE store_id = ? AND is_read = 0 AND sender_role = 'superadmin'");
        $stmt->bind_param("i", $store_id);
    } else {
        // User without store - no unread (they only see their own messages)
        $unread_count = 0;
    }
    if ($store_id) {
        $stmt->execute();
        $unread = $stmt->get_result()->fetch_assoc();
        $unread_count = $unread['count'] ?? 0;
    }
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE is_read = 0 AND sender_role != 'superadmin'");
    $stmt->execute();
    $unread = $stmt->get_result()->fetch_assoc();
    $unread_count = $unread['count'] ?? 0;
}

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'unread_count' => $unread_count
]);
