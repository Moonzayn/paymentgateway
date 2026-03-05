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
    $target_user_id = $_POST['user_id'] ?? null;
    
    if ($target_user_id) {
        $room_id = 'user_' . $target_user_id;
        $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE room_id = ? AND id > ? ORDER BY created_at ASC");
        $stmt->bind_param("si", $room_id, $last_id);
    } elseif ($store_id) {
        $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE store_id = ? AND id > ? ORDER BY created_at ASC");
        $stmt->bind_param("ii", $store_id, $last_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE room_id IS NULL AND store_id IS NULL AND id > ? ORDER BY created_at ASC");
        $stmt->bind_param("i", $last_id);
    }
} else {
    $room_id = 'user_' . $user_id;
    if ($store_id) {
        $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE store_id = ? AND id > ? ORDER BY created_at ASC");
        $stmt->bind_param("ii", $store_id, $last_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE room_id = ? AND id > ? ORDER BY created_at ASC");
        $stmt->bind_param("si", $room_id, $last_id);
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
        $stmt->execute();
        $unread = $stmt->get_result()->fetch_assoc();
        $unread_count = $unread['count'] ?? 0;
    } else {
        $room_id = 'user_' . $user_id;
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE room_id = ? AND is_read = 0 AND sender_role = 'superadmin'");
        $stmt->bind_param("s", $room_id);
        $stmt->execute();
        $unread = $stmt->get_result()->fetch_assoc();
        $unread_count = $unread['count'] ?? 0;
    }
} else {
    $target_user_id = $_POST['user_id'] ?? null;
    if ($target_user_id) {
        $room_id = 'user_' . $target_user_id;
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE room_id = ? AND is_read = 0 AND sender_role != 'superadmin'");
        $stmt->bind_param("s", $room_id);
    } elseif ($store_id) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE store_id = ? AND is_read = 0 AND sender_role != 'superadmin'");
        $stmt->bind_param("i", $store_id);
    } else {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM chat_messages WHERE room_id IS NULL AND store_id IS NULL AND is_read = 0 AND sender_role != 'superadmin'");
        $unread = $stmt->fetch_assoc();
        echo json_encode([
            'success' => true,
            'messages' => $messages,
            'unread_count' => $unread['count'] ?? 0
        ]);
        $conn->close();
        exit;
    }
    $stmt->execute();
    $unread = $stmt->get_result()->fetch_assoc();
    $unread_count = $unread['count'] ?? 0;
}

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'unread_count' => $unread_count
]);
