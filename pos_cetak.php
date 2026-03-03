<?php
require_once __DIR__ . '/config.php';

$invoice = $_GET['invoice'] ?? '';
$isAjax = isset($_GET['ajax']);

if (!$invoice) {
    if ($isAjax) {
        echo json_encode(['error' => 'Invoice tidak ditemukan']);
        exit;
    }
    die('Invoice tidak ditemukan');
}

$conn = koneksi();
$stmt = $conn->prepare("
    SELECT tp.*, s.nama_toko, s.alamat as store_alamat, s.no_hp as store_hp, u.nama_lengkap
    FROM transaksi_pos tp
    JOIN stores s ON tp.store_id = s.id
    JOIN users u ON tp.user_id = u.id
    WHERE tp.no_invoice = ?
");
$stmt->bind_param("s", $invoice);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows == 0) {
    if ($isAjax) {
        echo json_encode(['error' => 'Invoice tidak ditemukan']);
        exit;
    }
    die('Invoice tidak ditemukan');
}

$transaksi = $result->fetch_assoc();

$stmtDetail = $conn->prepare("
    SELECT * FROM transaksi_pos_detail WHERE transaksi_id = ?
");
$stmtDetail->bind_param("i", $transaksi['id']);
$stmtDetail->execute();
$details = $stmtDetail->get_result();

if ($isAjax) {
    ?>
    <div style="text-align: center; padding: 10px; font-family: monospace; font-size: 12px;">
        <div style="font-size: 14px; font-weight: bold; margin-bottom: 8px;"><?= htmlspecialchars($transaksi['nama_toko']) ?></div>
        <div style="font-size: 11px; color: #666; margin-bottom: 8px;"><?= htmlspecialchars($transaksi['store_alamat'] ?? '-') ?></div>
        <div style="font-size: 11px; color: #666; margin-bottom: 12px;">Telp: <?= htmlspecialchars($transaksi['store_hp'] ?? '-') ?></div>
        
        <div style="border-top: 1px dashed #333; border-bottom: 1px dashed #333; padding: 8px 0; margin-bottom: 8px; text-align: left;">
            <div><strong>Invoice:</strong> <?= htmlspecialchars($invoice) ?></div>
            <div><strong>Tanggal:</strong> <?= date('d-m-Y H:i', strtotime($transaksi['created_at'])) ?></div>
            <div><strong>Kasir:</strong> <?= htmlspecialchars($transaksi['nama_lengkap']) ?></div>
        </div>
        
        <table style="width: 100%; text-align: left; font-size: 11px;">
            <?php while($detail = $details->fetch_assoc()): ?>
            <tr>
                <td style="padding: 4px 0;">
                    <?= htmlspecialchars($detail['nama_item']) ?><br>
                    <span style="color: #666;"><?= $detail['qty'] ?> x <?= number_format($detail['harga_saat_transaksi'], 0, ',', '.') ?></span>
                </td>
                <td style="text-align: right; padding: 4px 0;"><?= number_format($detail['total_harga'], 0, ',', '.') ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
        
        <div style="border-top: 1px dashed #333; padding: 8px 0; margin-top: 8px; text-align: right; font-weight: bold; font-size: 14px;">
            TOTAL: Rp <?= number_format($transaksi['total_bayar'], 0, ',', '.') ?>
        </div>
        
        <div style="padding: 8px 0; text-align: left; font-size: 11px;">
            <div>Metode: <?= strtoupper($transaksi['metode_bayar']) ?></div>
            <?php if($transaksi['metode_bayar'] == 'cash'): ?>
            <div>Uang Diberikan: Rp <?= number_format($transaksi['uang_diberikan'], 0, ',', '.') ?></div>
            <div>Kembalian: Rp <?= number_format($transaksi['kembalian'], 0, ',', '.') ?></div>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 16px; font-size: 10px; color: #666;">
            Terima kasih atas kunjungan Anda!
        </div>
    </div>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Struk - <?= htmlspecialchars($invoice) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 12px; width: 300px; margin: 0 auto; padding: 10px; }
        .header { text-align: center; border-bottom: 1px dashed #333; padding-bottom: 10px; margin-bottom: 10px; }
        .store-name { font-size: 14px; font-weight: bold; }
        .store-address { font-size: 10px; margin-top: 4px; }
        .invoice-info { margin: 10px 0; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 0; text-align: left; }
        .item-name { font-weight: bold; }
        .item-qty { color: #666; }
        .total-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 14px; padding: 4px 0; }
        .payment-info { margin-top: 15px; padding-top: 10px; border-top: 1px dashed #333; }
        .payment-row { display: flex; justify-content: space-between; margin: 4px 0; }
        .footer { text-align: center; margin-top: 20px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <div class="store-name"><?= htmlspecialchars($transaksi['nama_toko']) ?></div>
        <div class="store-address"><?= htmlspecialchars($transaksi['store_alamat'] ?? '-') ?></div>
        <div class="store-address">Telp: <?= htmlspecialchars($transaksi['store_hp'] ?? '-') ?></div>
    </div>
    
    <div class="invoice-info">
        <div><strong>Invoice:</strong> <?= htmlspecialchars($invoice) ?></div>
        <div><strong>Tanggal:</strong> <?= date('d-m-Y H:i', strtotime($transaksi['created_at'])) ?></div>
        <div><strong>Kasir:</strong> <?= htmlspecialchars($transaksi['nama_lengkap']) ?></div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Harga</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $details = $conn->query("SELECT * FROM transaksi_pos_detail WHERE transaksi_id = " . $transaksi['id']);
            while($detail = $details->fetch_assoc()): ?>
            <tr>
                <td><div class="item-name"><?= htmlspecialchars($detail['nama_item']) ?></div></td>
                <td style="text-align: center;"><?= $detail['qty'] ?></td>
                <td style="text-align: right;"><?= number_format($detail['harga_saat_transaksi'], 0, ',', '.') ?></td>
                <td style="text-align: right;"><?= number_format($detail['total_harga'], 0, ',', '.') ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <div class="total-row" style="margin-top: 10px;">
        <span>TOTAL</span>
        <span>Rp <?= number_format($transaksi['total_bayar'], 0, ',', '.') ?></span>
    </div>
    
    <div class="payment-info">
        <div class="payment-row">
            <span>Metode:</span>
            <span><?= strtoupper($transaksi['metode_bayar']) ?></span>
        </div>
        <?php if($transaksi['metode_bayar'] == 'cash'): ?>
        <div class="payment-row">
            <span>Uang Diberikan:</span>
            <span>Rp <?= number_format($transaksi['uang_diberikan'], 0, ',', '.') ?></span>
        </div>
        <div class="payment-row">
            <span>Kembalian:</span>
            <span>Rp <?= number_format($transaksi['kembalian'], 0, ',', '.') ?></span>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <p>Terima kasih atas kunjungan Anda!</p>
    </div>
    
    <script>
        window.onload = function() { window.print(); }
    </script>
</body>
</html>
