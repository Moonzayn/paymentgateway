<?php
/**
 * Get Notifications for Admin
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['notifications' => [], 'unread' => 0]);
    exit;
}

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

// Only admin can see notifications
if (!in_array($role, ['admin', 'superadmin'])) {
    echo json_encode(['notifications' => [], 'unread' => 0]);
    exit;
}

// Get notifications (deposit pending, new chats, etc)
$sql = "SELECT * FROM (
    -- Deposit pending
    SELECT 'deposit' as type, d.id, d.nominal, u.nama_lengkap as user_name,
           CONCAT('Deposit pending Rp ', FORMAT(d.nominal, 0)) as title,
           CONCAT('Dari: ', u.nama_lengkap) as message,
           d.created_at, 'no' as is_read
    FROM deposit d
    JOIN users u ON d.user_id = u.id
    WHERE d.status = 'pending'

    UNION ALL

    -- Unread chats
    SELECT 'chat' as type, c.id, c.store_id, s.nama_toko as user_name,
           'Chat baru masuk' as title,
           LEFT(c.message, 50) as message,
           c.created_at, IF(c.is_read = 0, 'no', 'yes') as is_read
    FROM chat_messages c
    LEFT JOIN stores s ON c.store_id = s.id
    WHERE c.is_read = 0
    ORDER BY created_at DESC
    LIMIT 10
) t ORDER BY created_at DESC";

$result = $conn->query($sql);

$notifications = [];
$unread = 0;

while ($row = $result->fetch_assoc()) {
    $row['time_ago'] = timeAgo($row['created_at']);
    $notifications[] = $row;
    if ($row['is_read'] === 'no') $unread++;
}

echo json_encode([
    'notifications' => $notifications,
    'unread' => $unread
]);

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return $diff . ' detik lalu';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    return floor($diff / 86400) . ' hari lalu';
}
