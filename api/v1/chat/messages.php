<?php
/**
 * API v1 - Chat Messages Endpoint
 * Method: GET (list messages) / POST (send message)
 * Header: Authorization: Bearer <api_key>
 *
 * Get or send chat messages
 */

require_once __DIR__ . '/../config.php';

$currentUser = requireAuth();
$conn = koneksi();

// Check if chat_messages table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'chat_messages'")->num_rows > 0;

if (!$tableExists) {
    apiError('Chat feature not available', 'CHAT_NOT_AVAILABLE', 503);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get messages from a room
    $roomId = $_GET['room_id'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    if (empty($roomId)) {
        apiError('Room ID diperlukan', 'VALIDATION_ERROR');
    }

    // Verify user has access to this room
    $userId = $currentUser['user_id'];
    $role = $currentUser['role'];

    if ($role !== 'admin' && $role !== 'superadmin') {
        $userRoomId = 'user_' . $userId;
        if ($roomId !== $userRoomId) {
            // Check if user is part of this store's chat
            $storeId = str_replace('store_', '', $roomId);
            if (!is_numeric($storeId)) {
                apiError('Akses ditolak', 'ACCESS_DENIED', 403);
            }

            $stmtCheck = $conn->prepare("SELECT id FROM store_users WHERE user_id = ? AND store_id = ?");
            $stmtCheck->bind_param("ii", $userId, $storeId);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();

            if ($resultCheck->num_rows === 0) {
                apiError('Akses ditolak', 'ACCESS_DENIED', 403);
            }
        }
    }

    // Get total count
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM chat_messages WHERE room_id = ?");
    $stmtCount->bind_param("s", $roomId);
    $stmtCount->execute();
    $resultCount = $stmtCount->get_result();
    $totalRow = $resultCount->fetch_assoc();
    $total = $totalRow['total'];

    // Get messages
    $stmt = $conn->prepare("
        SELECT * FROM chat_messages
        WHERE room_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("sii", $roomId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'message' => $row['message'],
            'sender_role' => $row['sender_role'],
            'is_read' => $row['is_read'] === 1,
            'created_at' => $row['created_at']
        ];
    }

    // Mark messages as read
    $stmtRead = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE room_id = ? AND is_read = 0");
    $stmtRead->bind_param("s", $roomId);
    $stmtRead->execute();

    $conn->close();

    apiSuccess([
        'messages' => array_reverse($messages),
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ], 'Daftar pesan');

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Send a message
    $input = json_decode(file_get_contents('php://input'), true);

    $roomId = $input['room_id'] ?? '';
    $message = trim($input['message'] ?? '');

    if (empty($roomId) || empty($message)) {
        apiError('Room ID dan pesan diperlukan', 'VALIDATION_ERROR');
    }

    $userId = $currentUser['user_id'];
    $role = $currentUser['role'];

    // Verify user has access to this room
    if ($role !== 'admin' && $role !== 'superadmin') {
        $userRoomId = 'user_' . $userId;
        if ($roomId !== $userRoomId) {
            apiError('Akses ditolak', 'ACCESS_DENIED', 403);
        }
    }

    // Insert message
    $stmt = $conn->prepare("
        INSERT INTO chat_messages (room_id, sender_id, sender_role, message, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    $stmt->bind_param("siss", $roomId, $userId, $role, $message);
    $stmt->execute();

    $messageId = $conn->insert_id;

    $conn->close();

    apiSuccess([
        'id' => $messageId,
        'room_id' => $roomId,
        'message' => $message,
        'sender_role' => $role,
        'created_at' => date('Y-m-d H:i:s')
    ], 'Pesan terkirim');

} else {
    apiError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}
