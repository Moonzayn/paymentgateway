<?php
header('Content-Type: text/plain');
$conn = new mysqli('localhost', 'root', '', 'db_ppob');

// Check table structure
echo "=== Table Structure ===\n";
$result = $conn->query("DESCRIBE produk");
while ($row = $result->fetch_assoc()) {
    echo "{$row['Field']} - {$row['Type']} - Default: {$row['Default']}\n";
}

echo "\n=== All Products (First 10) ===\n";
$result = $conn->query("SELECT * FROM produk LIMIT 10");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
