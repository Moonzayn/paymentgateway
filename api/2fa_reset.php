<?php
/**
 * API: Reset 2FA User (by Admin)
 */

session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

// Only superadmin and admin can reset 2FA
if (!in_array($role, ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya admin yang dapat mereset 2FA.']);
    exit;
}

$action = $_GET['action'] ?? '';
$conn = koneksi();

switch ($action) {
    case 'reset':
        $target_user_id = intval($_POST['user_id'] ?? 0);

        if (!$target_user_id) {
            echo json_encode(['success' => false, 'message' => 'User ID diperlukan']);
            exit;
        }

        // Check if target user exists
        $stmt = $conn->prepare("SELECT id, username, nama_lengkap FROM users WHERE id = ?");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$targetUser = $result->fetch_assoc()) {
            echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
            exit;
        }

        // Check if 2FA exists for this user
        $stmt = $conn->prepare("SELECT id, enabled FROM user_2fa WHERE user_id = ?");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'User ini tidak memiliki 2FA aktif']);
            exit;
        }

        // Reset 2FA - delete the record
        $stmt = $conn->prepare("DELETE FROM user_2fa WHERE user_id = ?");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();

        // Also reset force_2fa
        $stmt = $conn->prepare("UPDATE users SET force_2fa = 'no' WHERE id = ?");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();

        // Log the action
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        error_log("[" . date('Y-m-d H:i:s') . "] 2FA RESET: Admin $current_user_id reset 2FA for user $target_user_id ({$targetUser['username']}) from IP $ip");

        echo json_encode([
            'success' => true,
            'message' => '2FA berhasil di-reset untuk user ' . htmlspecialchars($targetUser['nama_lengkap'])
        ]);
        break;

    case 'enable_force':
        $target_user_id = intval($_POST['user_id'] ?? 0);

        if (!$target_user_id) {
            echo json_encode(['success' => false, 'message' => 'User ID diperlukan']);
            exit;
        }

        // Check if target user exists
        $stmt = $conn->prepare("SELECT id, username, nama_lengkap FROM users WHERE id = ?");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$targetUser = $result->fetch_assoc()) {
            echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
            exit;
        }

        // Set force_2fa
        $stmt = $conn->prepare("UPDATE users SET force_2fa = 'yes' WHERE id = ?");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => '2FA wajib diaktifkan untuk user ' . htmlspecialchars($targetUser['nama_lengkap'])
        ]);
        break;

    case 'disable_force':
        $target_user_id = intval($_POST['user_id'] ?? 0);

        if (!$target_user_id) {
            echo json_encode(['success' => false, 'message' => 'User ID diperlukan']);
            exit;
        }

        // Unset force_2fa
        $stmt = $conn->prepare("UPDATE users SET force_2fa = 'no' WHERE id = ?");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => '2FA wajib dinonaktifkan untuk user'
        ]);
        break;

    case 'list':
        // Get all users with 2FA status
        $search = $_GET['search'] ?? '';
        $page = intval($_GET['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $where = "WHERE 1=1";
        if ($search) {
            $searchTerm = "%$search%";
            $where .= " AND (u.username LIKE ? OR u.nama_lengkap LIKE ?)";
        }

        $countQuery = "SELECT COUNT(*) as total FROM users u $where";
        $query = "SELECT u.id, u.username, u.nama_lampilkan, u.role, u.force_2fa,
                   (SELECT COUNT(*) FROM user_2fa WHERE user_id = u.id AND enabled = 'yes') as has_2fa
                   FROM users u
                   $where
                   ORDER BY u.id DESC
                   LIMIT ? OFFSET ?";

        if ($search) {
            $stmt = $conn->prepare($countQuery);
            $stmt->bind_param("ss", $searchTerm, $searchTerm);
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];

            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssii", $searchTerm, $searchTerm, $limit, $offset);
        } else {
            $result = $conn->query($countQuery);
            $total = $result->fetch_assoc()['total'];

            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $limit, $offset);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }

        echo json_encode([
            'success' => true,
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
