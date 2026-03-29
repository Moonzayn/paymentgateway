<?php
header('Content-Type: text/plain');
require_once '../config.php';

$conn = koneksi();

// Clear all expired locks
$conn->query("DELETE FROM transaction_locks WHERE expires_at < NOW()");
echo "Cleared expired locks\n";

// Show active locks
$result = $conn->query("SELECT * FROM transaction_locks WHERE expires_at > NOW()");
echo "\nActive locks:\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "No active locks\n";
}

// Also clear rate limits for testing
$conn->query("DELETE FROM rate_limits");
echo "\nCleared rate limits\n";

echo "\nDone! You can try again now.\n";
