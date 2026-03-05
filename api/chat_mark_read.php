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
    $target_store_id = isset($_POST['store_id']) && $_POST['store_id'] !== '' ? (int)$_POST['store_id'] : null;

    if ($target_store_id !== null && $target_store_id > 0) {
        $stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE store_id = ? AND sender_role != 'superadmin'");
        $stmt->bind_param("i", $target_store_id);
    } else {
        // Mark all as read for users without store
        $stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE (store_id IS NULL OR store_id = 0) AND sender_role != 'superadmin'");
    }
} else {
    if ($store_id) {
        $stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE store_id = ? AND sender_role = 'superadmin'");
        $stmt->bind_param("i", $store_id);
    } else {
        // User without store
        $stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE (store_id IS NULL OR store_id = 0) AND sender_role = 'superadmin'");
    }
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark as read']);
}
