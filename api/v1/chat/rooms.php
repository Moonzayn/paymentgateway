<?php
/**
 * API v1 - Chat Rooms Endpoint
 * Method: GET
 * Header: Authorization: Bearer <api_key>
 *
 * Get all chat rooms/conversations for the user
 */

require_once __DIR__ . '/../config.php';

$currentUser = requireAuth();
$conn = koneksi();

// Check if chat_messages table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'chat_messages'")->num_rows > 0;

if (!$tableExists) {
    apiSuccess([
        'rooms' => []
    ], 'Daftar chat room');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

$userId = $currentUser['user_id'];
$role = $currentUser['role'];

// Get chat rooms based on role
if ($role === 'admin' || $role === 'superadmin') {
    // Admin can see all rooms
    $stmt = $conn->query("
        SELECT DISTINCT room_id,
            CASE
                WHEN room_id LIKE 'user_%' THEN SUBSTRING(room_id, 6)
                ELSE NULL
            END as target_user_id
        FROM chat_messages
        WHERE room_id IS NOT NULL
        ORDER BY (SELECT created_at FROM chat_messages cm2 WHERE cm2.room_id = chat_messages.room_id ORDER BY created_at DESC LIMIT 1) DESC
    ");
} else {
    // Regular users see only their own room
    $userRoomId = 'user_' . $userId;

    // Check if there's a store-based chat
    $stmtStore = $conn->prepare("
        SELECT DISTINCT room_id FROM chat_messages
        WHERE room_id = ? OR store_id IN (
            SELECT store_id FROM store_users WHERE user_id = ?
        )
    ");
    $stmtStore->bind_param("si", $userRoomId, $userId);
    $stmtStore->execute();
    $resultStore = $stmtStore->get_result();

    $rooms = [];
    while ($row = $resultStore->fetch_assoc()) {
        if ($row['room_id']) {
            $rooms[] = $row['room_id'];
        }
    }

    if (empty($rooms)) {
        apiSuccess(['rooms' => []], 'Daftar chat room');
    }

    $roomsList = implode("','", array_map(function($r) use ($conn) {
        return $conn->real_escape_string($r);
    }, $rooms));

    $stmt = $conn->query("
        SELECT room_id FROM chat_messages
        WHERE room_id IN ('$roomsList')
        GROUP BY room_id
        ORDER BY MAX(created_at) DESC
    ");
}

$chatRooms = [];

while ($row = $stmt->fetch_assoc()) {
    $roomId = $row['room_id'];
    $targetUserId = $row['target_user_id'] ?? null;

    // Get target user info
    $displayName = 'Unknown';
    $targetUserId = null;

    if ($roomId && strpos($roomId, 'user_') === 0) {
        $targetUserId = str_replace('user_', '', $roomId);
        $stmtUser = $conn->prepare("SELECT username, nama_lengkap FROM users WHERE id = ?");
        $stmtUser->bind_param("i", $targetUserId);
        $stmtUser->execute();
        $resultUser = $stmtUser->get_result();
        if ($user = $resultUser->fetch_assoc()) {
            $displayName = $user['nama_lengkap'] ?: $user['username'];
        }
    }

    // Get last message
    $stmtMsg = $conn->prepare("SELECT message, created_at, sender_role FROM chat_messages WHERE room_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmtMsg->bind_param("s", $roomId);
    $stmtMsg->execute();
    $resultMsg = $stmtMsg->get_result();
    $lastMsg = $resultMsg->fetch_assoc();

    // Get unread count
    $stmtUnread = $conn->prepare("SELECT COUNT(*) as cnt FROM chat_messages WHERE room_id = ? AND is_read = 0 AND sender_role != 'admin' AND sender_role != 'superadmin'");
    $stmtUnread->bind_param("s", $roomId);
    $stmtUnread->execute();
    $resultUnread = $stmtUnread->get_result();
    $unread = $resultUnread->fetch_assoc();

    $chatRooms[] = [
        'room_id' => $roomId,
        'target_user_id' => $targetUserId ? (int)$targetUserId : null,
        'display_name' => $displayName,
        'last_message' => $lastMsg ? [
            'message' => $lastMsg['message'],
            'sender_role' => $lastMsg['sender_role'],
            'created_at' => $lastMsg['created_at']
        ] : null,
        'unread_count' => (int)$unread['cnt']
    ];
}

$conn->close();

apiSuccess([
    'rooms' => $chatRooms
], 'Daftar chat room');
