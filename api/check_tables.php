<?php
header('Content-Type: text/plain');
$conn = new mysqli('localhost', 'root', '', 'db_ppob');

// Check if transaction_locks table exists
$result = $conn->query("SHOW TABLES LIKE 'transaction_locks'");
if ($result->num_rows == 0) {
    echo "Table transaction_locks does NOT exist!\n";
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
        echo "Created successfully!\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
} else {
    echo "Table transaction_locks exists\n";
}

// Check rate_limits table
$result = $conn->query("SHOW TABLES LIKE 'rate_limits'");
if ($result->num_rows == 0) {
    echo "\nTable rate_limits does NOT exist!\n";
    echo "Creating table...\n";
    $sql = "CREATE TABLE rate_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        endpoint VARCHAR(100) NOT NULL,
        requests_count INT DEFAULT 1,
        window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_endpoint (ip_address, endpoint)
    )";
    if ($conn->query($sql)) {
        echo "Created successfully!\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
} else {
    echo "\nTable rate_limits exists\n";
}

echo "\nDone!\n";
