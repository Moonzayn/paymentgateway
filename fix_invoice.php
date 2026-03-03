<?php
require_once 'config.php';

$conn = koneksi();

echo "<h3>Fix Invoice Numbers</h3>";

$trx = $conn->query("SELECT id, no_invoice FROM transaksi_pos WHERE no_invoice = '0' OR no_invoice = ''");

if ($trx->num_rows > 0) {
    while ($row = $trx->fetch_assoc()) {
        $new_invoice = 'POS-' . date('YmdHis', strtotime($row['id'] . ' seconds')) . rand(100, 999);
        $conn->query("UPDATE transaksi_pos SET no_invoice = '$new_invoice' WHERE id = " . $row['id']);
        echo "Updated ID " . $row['id'] . " to: $new_invoice<br>";
    }
} else {
    echo "All invoices are already correct!";
}

echo "<h3>Current Transactions</h3>";
$trx = $conn->query("SELECT * FROM transaksi_pos ORDER BY id DESC LIMIT 5");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Invoice</th><th>Total</th><th>Metode</th></tr>";
while ($row = $trx->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td><a href='pos_cetak.php?invoice=" . $row['no_invoice'] . "' target='_blank'>" . $row['no_invoice'] . "</a></td>";
    echo "<td>" . $row['total_bayar'] . "</td>";
    echo "<td>" . $row['metode_bayar'] . "</td>";
    echo "</tr>";
}
echo "</table>";
