<?php
/**
 * API v1 - Notifications Endpoint
 * Method: GET (list) / PUT (mark read) / POST (mark all read)
 * Header: Authorization: Bearer <api_key>
 */

require_once __DIR__ . '/../config.php';

$currentUser = requireAuth();
$conn = koneksi();

// Check if notifications table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0;

if (!$tableExists) {
    // Return empty notifications if table doesn't exist
    apiSuccess([
        'notifications' => [],
        'pagination' => [
            'page' => 1,
            'limit' => 20,
            'total' => 0,
            'total_pages' => 0
        ],
        'unread_count' => 0,
        'note' => 'Table notifications not found'
    ], 'Daftar notifikasi');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get notifications with pagination
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;

    // Get total count
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
    $stmtCount->bind_param("i", $currentUser['user_id']);
    $stmtCount->execute();
    $resultCount = $stmtCount->get_result();
    $totalRow = $resultCount->fetch_assoc();
    $total = $totalRow['total'];

    // Get notifications
    $stmt = $conn->prepare("
        SELECT * FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $currentUser['user_id'], $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    $unreadCount = 0;

    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'unread') {
            $unreadCount++;
        }
        $notifications[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'type' => $row['type'],
            'status' => $row['status'],
            'data' => $row['data'] ? json_decode($row['data'], true) : null,
            'created_at' => $row['created_at']
        ];
    }

    $conn->close();

    apiSuccess([
        'notifications' => $notifications,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ],
        'unread_count' => $unreadCount
    ], 'Daftar notifikasi');

} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Mark single notification as read
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = (int)($input['id'] ?? 0);

    if (!$notificationId) {
        apiError('Notification ID diperlukan', 'VALIDATION_ERROR');
    }

    $stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notificationId, $currentUser['user_id']);
    $stmt->execute();

    $conn->close();

    apiSuccess(null, 'Notifikasi ditandai sudah dibaca');

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark all notifications as read
    $stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE user_id = ? AND status = 'unread'");
    $stmt->bind_param("i", $currentUser['user_id']);
    $stmt->execute();

    $conn->close();

    apiSuccess(null, 'Semua notifikasi ditandai sudah dibaca');

} else {
    apiError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}
