<?php
header('Content-Type: text/plain');
$conn = new mysqli('localhost', 'root', '', 'db_ppob');

// Check chat_rooms table structure
echo "=== chat_rooms table ===\n";
$result = $conn->query("DESCRIBE chat_rooms");
while ($row = $result->fetch_assoc()) {
    echo "{$row['Field']} - {$row['Type']}\n";
}

echo "\n=== Check chat_get_all.php ===\n";
$sql = file_get_contents('../api/chat_get_all.php');
echo "Looking for: room_id\n";

// Try to find the issue
preg_match_all("/chat_rooms\.(\w+)/", $sql, $matches);
echo "Columns used: " . implode(', ', $matches[1]) . "\n";
