<?php
/**
 * Direct lock test
 */
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config.php';

$conn = koneksi();
$lockKey = 'test_lock_' . time();
$userId = 1;

echo "Testing lock directly...\n";
echo "Lock key: $lockKey\n\n";

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'transaction_locks'");
if ($result->num_rows == 0) {
    echo "ERROR: Table transaction_locks does not exist!\n";
    echo "Creating table...\n";

    $sql = "CREATE TABLE transaction_locks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lock_key VARCHAR(255) NOT NULL,
        user_id INT NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_lock (lock_key)
    )";

    if ($conn->query($sql)) {
        echo "Table created successfully!\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
} else {
    echo "Table exists.\n";
}

// Show table structure
echo "\nTable structure:\n";
$result = $conn->query("DESCRIBE transaction_locks");
while ($row = $result->fetch_assoc()) {
    echo "  {$row['Field']} - {$row['Type']}\n";
}

// Try to insert directly
echo "\nTrying direct insert...\n";
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 seconds'));
$stmt = $conn->prepare("INSERT INTO transaction_locks (lock_key, user_id, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("sis", $lockKey, $userId, $expiresAt);

if ($stmt->execute()) {
    echo "Insert successful!\n";
} else {
    echo "Insert failed: " . $stmt->error . "\n";
}

// Check the lock
echo "\nChecking lock...\n";
$stmt = $conn->prepare("SELECT * FROM transaction_locks WHERE lock_key = ?");
$stmt->bind_param("s", $lockKey);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "Lock found:\n";
    print_r($row);
} else {
    echo "Lock NOT found!\n";
}

// Delete test lock
$conn->query("DELETE FROM transaction_locks WHERE lock_key = '$lockKey'");
echo "\nTest lock deleted.\n";
