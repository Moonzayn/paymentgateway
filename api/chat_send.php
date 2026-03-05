<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$message = trim($_POST['message'] ?? '');

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$store_id = $_SESSION['current_store_id'] ?? null;
$sender_name = $_SESSION['nama'] ?? 'User';
$sender_role = $_SESSION['role'] ?? 'kasir';

if ($sender_role === 'admin' || $sender_role === 'superadmin') {
    $sender_role = 'superadmin';
    $target_user_id = $_POST['user_id'] ?? null;
    $room_id = $target_user_id ? 'user_' . $target_user_id : null;
} else {
    $sender_role = ($_SESSION['role_owner'] ?? false) ? 'owner' : 'kasir';
    $room_id = 'user_' . $user_id;
}

if ($store_id === 0) {
    $store_id = null;
}

$stmt = $conn->prepare("INSERT INTO chat_messages (store_id, sender_id, sender_role, sender_name, message, room_id) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iissss", $store_id, $user_id, $sender_role, $sender_name, $message, $room_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Message sent']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}
