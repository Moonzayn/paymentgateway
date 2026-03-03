<?php
require_once 'config.php';

$conn = koneksi();

echo "<h3>Debug: Check Transactions</h3>";

$transaksi = $conn->query("SELECT * FROM transaksi_pos ORDER BY created_at DESC LIMIT 10");

if ($transaksi->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Invoice</th><th>Store</th><th>User</th><th>Total</th><th>Metode</th><th>Status</th><th>Created</th></tr>";
    while ($row = $transaksi->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['no_invoice'] . "</td>";
        echo "<td>" . $row['store_id'] . "</td>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . $row['total_bayar'] . "</td>";
        echo "<td>" . $row['metode_bayar'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Tidak ada transaksi";
}

echo "<h3>Debug: Check User Session</h3>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'N/A') . "<br>";
echo "Store ID: " . ($_SESSION['current_store_id'] ?? 'N/A') . "<br>";
echo "Store Role: " . ($_SESSION['current_store_role'] ?? 'N/A') . "<br>";
echo "Is Super Admin: " . ($_SESSION['is_super_admin'] ?? 'N/A') . "<br>";
