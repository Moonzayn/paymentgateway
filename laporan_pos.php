<?php
session_start();
require_once 'config.php';

cekLogin();

$currentPage = 'laporan_pos';
$pageTitle = 'Laporan POS';
$pageIcon = 'fas fa-chart-line';
$pageDesc = 'Laporan penjualan POS';

$conn = koneksi();

function getUserStore($user_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT su.store_id, su.role, s.nama_toko 
        FROM store_users su 
        JOIN stores s ON su.store_id = s.id 
        WHERE su.user_id = ? AND s.status = 'active'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row;
    }
    return null;
}

$store = getUserStore($_SESSION['user_id']);

if (!$store && !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    setAlert('error', 'Anda belum memiliki toko');
    header("Location: index.php");
    exit;
}

$store_id = $store['store_id'] ?? 0;
$store_role = $store['role'] ?? 'owner';

if (!in_array($store_role, ['owner', 'admin'])) {
    setAlert('error', 'Anda tidak memiliki akses');
    header("Location: index.php");
    exit;
}

$_SESSION['current_store_id'] = $store_id;

$tanggal_dari = $_GET['dari'] ?? '';
$tanggal_sampai = $_GET['sampai'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_invoice = $_GET['search'] ?? '';

$where = "WHERE tp.store_id = " . intval($store_id);

if ($tanggal_dari && $tanggal_sampai) {
    $where .= " AND DATE(tp.created_at) BETWEEN '" . $conn->real_escape_string($tanggal_dari) . "' AND '" . $conn->real_escape_string($tanggal_sampai) . "'";
}

if ($status_filter) {
    $where .= " AND tp.status = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($search_invoice) {
    $where .= " AND tp.no_invoice LIKE '%" . $conn->real_escape_string($search_invoice) . "%'";
}

$summary = $conn->query("
    SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(tp.total_bayar), 0) as total_pendapatan,
        COALESCE(SUM(tp.total_item), 0) as total_item,
        COALESCE(SUM(CASE WHEN tp.metode_bayar = 'qris' THEN tp.total_bayar ELSE 0 END), 0) as total_qris,
        COALESCE(SUM(CASE WHEN tp.metode_bayar = 'cash' THEN tp.total_bayar ELSE 0 END), 0) as total_cash
    FROM transaksi_pos tp
    " . $where . " AND tp.status = 'success'
");
$summaryResult = $summary->fetch_assoc();

$transaksiList = $conn->query("
    SELECT tp.*, u.nama_lengkap as kasir
    FROM transaksi_pos tp
    JOIN users u ON tp.user_id = u.id
    $where
    ORDER BY tp.created_at DESC
    LIMIT 100
");

$alert = getAlert();
?>
<?php include 'layout.php'; ?>

<style>
.filter-bar {
    background: white;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid var(--border);
    margin-bottom: 24px;
}

.filter-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
}

.filter-group input,
.filter-group select {
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: var(--primary);
}

.btn {
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-outline {
    background: white;
    border: 1px solid var(--border);
    color: var(--text);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid var(--border);
}

.stat-card .label {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.stat-card .value {
    font-size: 24px;
    font-weight: 700;
    color: var(--text);
}

.stat-card .value.primary {
    color: var(--primary);
}

.stat-card .value.success {
    color: var(--success);
}

.stat-card .sub {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 4px;
}

.table-container {
    background: white;
    border-radius: 12px;
    border: 1px solid var(--border);
    overflow: hidden;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--border);
}

th {
    background: var(--bg);
    font-weight: 600;
    font-size: 13px;
    color: var(--text-secondary);
}

.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.status-success {
    background: #dcfce7;
    color: #166534;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-failed {
    background: #fee2e2;
    color: #991b1b;
}

.status-cancelled {
    background: #f3f4f6;
    color: #6b7280;
}

.metode-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.metode-qris {
    background: #dbeafe;
    color: #1e40af;
}

.metode-cash {
    background: #dcfce7;
    color: #166534;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.btn-outline {
    background: white;
    border: 1px solid var(--border);
    color: var(--text);
}

.modal {
    display: none;
}

.modal.show {
    display: flex;
}
</style>

<div class="filter-bar">
    <form class="filter-form" method="GET">
        <div class="filter-group">
            <label>Cari Invoice</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search_invoice) ?>" placeholder="No. Invoice..." style="width: 180px;">
        </div>
        
        <div class="filter-group">
            <label>Dari Tanggal</label>
            <input type="date" name="dari" value="<?= htmlspecialchars($tanggal_dari) ?>">
        </div>
        
        <div class="filter-group">
            <label>Sampai Tanggal</label>
            <input type="date" name="sampai" value="<?= htmlspecialchars($tanggal_sampai) ?>">
        </div>
        
        <div class="filter-group">
            <label>Status</label>
            <select name="status">
                <option value="">Semua</option>
                <option value="success" <?= $status_filter == 'success' ? 'selected' : '' ?>>Success</option>
                <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="failed" <?= $status_filter == 'failed' ? 'selected' : '' ?>>Failed</option>
                <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-search"></i> Filter
        </button>
        
        <a href="laporan_pos.php" class="btn btn-outline">
            <i class="fas fa-list"></i> Tampilkan Semua
        </a>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Total Transaksi</div>
        <div class="value"><?= $summaryResult['total_transaksi'] ?? 0 ?></div>
        <div class="sub">Transaksi berhasil</div>
    </div>
    
    <div class="stat-card">
        <div class="label">Total Pendapatan</div>
        <div class="value primary"><?= rupiah($summaryResult['total_pendapatan'] ?? 0) ?></div>
        <div class="sub"><?= $summaryResult['total_item'] ?? 0 ?> item terjual</div>
    </div>
    
    <div class="stat-card">
        <div class="label">QRIS</div>
        <div class="value success"><?= rupiah($summaryResult['total_qris'] ?? 0) ?></div>
        <div class="sub">Pembayaran QRIS</div>
    </div>
    
    <div class="stat-card">
        <div class="label">Cash</div>
        <div class="value"><?= rupiah($summaryResult['total_cash'] ?? 0) ?></div>
        <div class="sub">Pembayaran Tunai</div>
    </div>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Tanggal</th>
                <th>Kasir</th>
                <th>Item</th>
                <th>Total</th>
                <th>Metode</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php while($trx = $transaksiList->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($trx['no_invoice']) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($trx['created_at'])) ?></td>
                <td><?= htmlspecialchars($trx['kasir']) ?></td>
                <td><?= $trx['total_item'] ?></td>
                <td><?= rupiah($trx['total_bayar']) ?></td>
                <td>
                    <span class="metode-badge metode-<?= $trx['metode_bayar'] ?>">
                        <?= strtoupper($trx['metode_bayar']) ?>
                    </span>
                </td>
                <td>
                    <span class="status-badge status-<?= $trx['status'] ?>">
                        <?= ucfirst($trx['status']) ?>
                    </span>
                </td>
                <td>
                    <button onclick="viewDetail('<?= $trx['no_invoice'] ?>')" class="btn btn-sm btn-outline" title="Lihat Detail">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="cetakInvoice('<?= $trx['no_invoice'] ?>')" class="btn btn-sm btn-outline" title="Cetak">
                        <i class="fas fa-print"></i>
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
            
            <?php if($transaksiList->num_rows == 0): ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="fas fa-receipt" style="font-size: 40px; opacity: 0.3; margin-bottom: 12px; display: block;"></i>
                    <p>Tidak ada transaksi</p>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal" id="modalReceipt" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
    <div class="modal-content" style="max-width: 340px; padding: 0; border-radius: 24px; overflow: hidden; background: #fff; box-shadow: 0 24px 80px rgba(0,0,0,0.15);">
        <div style="padding: 20px 24px; border-bottom: 1px solid #f5f5f7; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 17px; font-weight: 600; color: #1d1d1f;">Struk Pembayaran</h3>
            <button onclick="closeReceipt()" style="background:none;border:none;font-size:24px;cursor:pointer;color: #86868b;">&times;</button>
        </div>
        <div id="receiptContent" style="padding: 20px 24px; text-align: center;"></div>
        <div style="padding: 16px 24px 24px; border-top: 1px solid #f5f5f7; display:flex;gap:10px;">
            <button onclick="printReceipt()" style="flex:1; padding: 14px; background: #007AFF; color: white; border: none; border-radius: 14px; cursor: pointer; font-weight: 600; font-size: 15px;">
                <i class="fas fa-print" style="margin-right: 6px;"></i> Print
            </button>
            <button onclick="closeReceipt()" style="flex:1; padding: 14px; background: #f5f5f7; color: #007AFF; border: none; border-radius: 14px; cursor: pointer; font-weight: 600; font-size: 15px;">
                Tutup
            </button>
        </div>
    </div>
</div>

<div class="modal" id="modalDetail" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
    <div class="modal-content" style="max-width: 400px; padding: 0; border-radius: 24px; overflow: hidden; background: #fff; box-shadow: 0 24px 80px rgba(0,0,0,0.15);">
        <div style="padding: 20px 24px; border-bottom: 1px solid #f5f5f7; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 17px; font-weight: 600; color: #1d1d1f;">Detail Transaksi</h3>
            <button onclick="closeDetail()" style="background:none;border:none;font-size:24px;cursor:pointer;color: #86868b;">&times;</button>
        </div>
        <div id="detailContent" style="padding: 20px 24px; max-height: 60vh; overflow-y: auto;"></div>
        <div style="padding: 16px 24px 24px; border-top: 1px solid #f5f5f7;">
            <button onclick="cetakFromDetail()" style="width: 100%; padding: 14px; background: #007AFF; color: white; border: none; border-radius: 14px; cursor: pointer; font-weight: 600; font-size: 15px;">
                <i class="fas fa-print" style="margin-right: 6px;"></i> Cetak Struk
            </button>
        </div>
    </div>
</div>

<script>
let currentInvoice = '';

function viewDetail(invoice) {
    currentInvoice = invoice;
    fetch('api/pos_get_detail.php?invoice=' + invoice)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const trx = data.transaksi;
                const items = data.items;
                
                let html = `
                    <div style="background: #f5f5f7; padding: 16px; border-radius: 12px; margin-bottom: 16px;">
                        <div style="font-size: 13px; color: #86868b; margin-bottom: 4px;">Invoice</div>
                        <div style="font-size: 15px; font-weight: 600; color: #1d1d1f; font-family: 'SF Mono', monospace;">${trx.no_invoice}</div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div style="background: #f5f5f7; padding: 12px; border-radius: 12px;">
                            <div style="font-size: 11px; color: #86868b;">Tanggal</div>
                            <div style="font-size: 13px; font-weight: 600; color: #1d1d1f;">${trx.tanggal}</div>
                        </div>
                        <div style="background: #f5f5f7; padding: 12px; border-radius: 12px;">
                            <div style="font-size: 11px; color: #86868b;">Kasir</div>
                            <div style="font-size: 13px; font-weight: 600; color: #1d1d1f;">${trx.kasir}</div>
                        </div>
                        <div style="background: #f5f5f7; padding: 12px; border-radius: 12px;">
                            <div style="font-size: 11px; color: #86868b;">Metode</div>
                            <div style="font-size: 13px; font-weight: 600; color: #1d1d1f; text-transform: uppercase;">${trx.metode_bayar}</div>
                        </div>
                        <div style="background: #f5f5f7; padding: 12px; border-radius: 12px;">
                            <div style="font-size: 11px; color: #86868b;">Status</div>
                            <div style="font-size: 13px; font-weight: 600; color: ${trx.status === 'success' ? '#10b981' : '#ff3b30'}; text-transform: capitalize;">${trx.status}</div>
                        </div>
                    </div>
                    
                    <div style="font-size: 13px; font-weight: 600; color: #1d1d1f; margin-bottom: 12px;">Items</div>
                    <div style="border: 1px solid #f5f5f7; border-radius: 12px; overflow: hidden;">
                `;
                
                items.forEach(item => {
                    html += `
                        <div style="padding: 12px; border-bottom: 1px solid #f5f5f7; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 14px; font-weight: 500; color: #1d1d1f;">${item.nama_item}</div>
                                <div style="font-size: 12px; color: #86868b;">${item.qty} x Rp ${parseInt(item.harga_saat_transaksi).toLocaleString('id-ID')}</div>
                            </div>
                            <div style="font-size: 14px; font-weight: 600; color: #1d1d1f;">Rp ${parseInt(item.total_harga).toLocaleString('id-ID')}</div>
                        </div>
                    `;
                });
                
                html += `
                    </div>
                    
                    <div style="background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%); padding: 16px; border-radius: 16px; color: white; margin-top: 16px;">
                        <div style="font-size: 12px; opacity: 0.8;">Total Pembayaran</div>
                        <div style="font-size: 24px; font-weight: 700;">Rp ${parseInt(trx.total_bayar).toLocaleString('id-ID')}</div>
                    </div>
                    
                    ${trx.metode_bayar === 'cash' ? `
                    <div style="margin-top: 12px; padding: 12px; background: #f5f5f7; border-radius: 12px;">
                        <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                            <span style="color: #86868b;">Uang Diberikan</span>
                            <span style="font-weight: 600;">Rp ${parseInt(trx.uang_diberikan).toLocaleString('id-ID')}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px;">
                            <span style="color: #86868b;">Kembalian</span>
                            <span style="font-weight: 600; color: #10b981;">Rp ${parseInt(trx.kembalian).toLocaleString('id-ID')}</span>
                        </div>
                    </div>
                    ` : ''}
                `;
                
                document.getElementById('detailContent').innerHTML = html;
                document.getElementById('modalDetail').style.display = 'flex';
            } else {
                alert('Gagal memuat detail: ' + data.message);
            }
        })
        .catch(err => {
            alert('Error: ' + err);
        });
}

function closeDetail() {
    document.getElementById('modalDetail').style.display = 'none';
}

function cetakFromDetail() {
    closeDetail();
    cetakInvoice(currentInvoice);
}

function cetakInvoice(invoice) {
    currentInvoice = invoice;
    fetch('pos_cetak.php?invoice=' + invoice + '&ajax=1')
        .then(response => response.text())
        .then(html => {
            document.getElementById('receiptContent').innerHTML = html;
            document.getElementById('modalReceipt').style.display = 'flex';
        })
        .catch(err => {
            alert('Gagal memuat struk: ' + err);
        });
}

function printReceipt() {
    const printContent = document.getElementById('receiptContent').innerHTML;
    const printWindow = window.open('', '', 'width=400,height=600');
    printWindow.document.write('<html><head><title>Struk</title>');
    printWindow.document.write('<style>body{font-family:monospace;font-size:12px;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(printContent);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
    printWindow.onafterprint = function() {
        printWindow.close();
    };
}

function closeReceipt() {
    document.getElementById('modalReceipt').style.display = 'none';
}
</script>

<?php include 'layout_footer.php'; ?>
