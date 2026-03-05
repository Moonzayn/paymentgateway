<?php
/**
 * Get Notifications for Admin
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['notifications' => [], 'unread' => 0, 'chat_unread' => 0]);
    exit;
}

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

// Only admin can see notifications
if (!in_array($role, ['admin', 'superadmin'])) {
    echo json_encode(['notifications' => [], 'unread' => 0, 'chat_unread' => 0]);
    exit;
}

// Get deposit pending
$depositSql = "SELECT 'deposit' as type, d.id, d.nominal, u.nama_lengkap as user_name,
           CONCAT('Deposit pending Rp ', FORMAT(d.nominal, 0)) as title,
           CONCAT('Dari: ', u.nama_lengkap) as message,
           d.created_at, 'no' as is_read
    FROM deposit d
    JOIN users u ON d.user_id = u.id
    WHERE d.status = 'pending'
    ORDER BY d.created_at DESC LIMIT 5";

$depositResult = $conn->query($depositSql);

// Get chat unread count (for stores where user has access)
$chatUnread = 0;
$storeIds = [];

$storeResult = $conn->query("SELECT store_id FROM store_users WHERE user_id = $user_id");
while ($s = $storeResult->fetch_assoc()) {
    $storeIds[] = $s['store_id'];
}

if (!empty($storeIds)) {
    $storeList = implode(',', $storeIds);
    $chatSql = "SELECT COUNT(*) as total FROM chat_messages WHERE store_id IN ($storeList) AND is_read = 0 AND sender_role != 'superadmin'";
    $chatResult = $conn->query($chatSql);
    $chatUnread = $chatResult->fetch_assoc()['total'] ?? 0;
}

$notifications = [];
$unread = 0;

while ($row = $depositResult->fetch_assoc()) {
    $row['time_ago'] = timeAgo($row['created_at']);
    $notifications[] = $row;
    if ($row['is_read'] === 'no') $unread++;
}

// Add chat notification if there are unread chats
if ($chatUnread > 0) {
    $notifications[] = [
        'type' => 'chat',
        'id' => 0,
        'title' => 'Chat baru',
        'message' => "$chatUnread pesan belum dibaca",
        'created_at' => date('Y-m-d H:i:s'),
        'is_read' => 'no',
        'time_ago' => 'Baru saja'
    ];
    $unread += $chatUnread;
}

echo json_encode([
    'notifications' => $notifications,
    'unread' => $unread,
    'chat_unread' => $chatUnread
]);

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return $diff . ' detik lalu';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    return floor($diff / 86400) . ' hari lalu';
}
