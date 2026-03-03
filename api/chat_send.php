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
    $store_id = $_POST['store_id'] ?? null;
} else {
    $sender_role = ($_SESSION['role_owner'] ?? false) ? 'owner' : 'kasir';
}

$stmt = $conn->prepare("INSERT INTO chat_messages (store_id, sender_id, sender_role, sender_name, message) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iisss", $store_id, $user_id, $sender_role, $sender_name, $message);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Message sent']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}
