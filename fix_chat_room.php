<?php
/**
 * Fix chat room_id - Run once: fix_chat_room.php
 */

require_once 'config.php';

$conn = koneksi();

// Update messages with NULL room_id to set room_id based on sender_id
$sql = "UPDATE chat_messages 
        SET room_id = CONCAT('user_', sender_id) 
        WHERE room_id IS NULL 
        AND sender_role != 'superadmin'";

if ($conn->query($sql)) {
    $affected = $conn->affected_rows;
    echo "Updated $affected messages<br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Also update room_id for superadmin messages in user rooms
$sql2 = "UPDATE chat_messages 
         SET room_id = CONCAT('user_', sender_id) 
         WHERE room_id IS NULL 
         AND sender_role = 'superadmin'";

if ($conn->query($sql2)) {
    $affected2 = $conn->affected_rows;
    echo "Updated $affected2 admin messages<br>";
}

echo "Done!";
