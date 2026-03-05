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
    $target_user_id = $_POST['user_id'] ?? null;

    if ($target_user_id) {
        $room_id = 'user_' . $target_user_id;
        $stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE room_id = ? AND sender_role != 'superadmin'");
        $stmt->bind_param("s", $room_id);
    } elseif ($store_id) {
        $stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE store_id = ? AND sender_role != 'superadmin'");
        $stmt->bind_param("i", $store_id);
    } else {
        $stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE room_id IS NULL AND store_id IS NULL AND sender_role != 'superadmin'");
    }
} else {
    if ($store_id) {
        $stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE store_id = ? AND sender_role = 'superadmin'");
        $stmt->bind_param("i", $store_id);
    } else {
        $room_id = 'user_' . $user_id;
        $stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE room_id = ? AND sender_role = 'superadmin'");
        $stmt->bind_param("s", $room_id);
    }
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark as read']);
}
