<?php
session_start();
require_once 'config.php';
require_once 'api/spn_helper.php';

cekLogin();

$currentPage = 'pos';
$pageTitle = 'POS Kasir';
$pageIcon = 'fas fa-cash-register';

$conn = koneksi();

function getStoreId($user_id) {
    $conn = koneksi();
    $stmt = $conn->prepare("
        SELECT su.store_id, su.role, s.nama_toko 
        FROM store_users su 
        JOIN stores s ON su.store_id = s.id 
        WHERE su.user_id = ? AND s.status = 'active'
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row;
    }
    return null;
}

$store = getStoreId($_SESSION['user_id']);

if (!$store && !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    setAlert('error', 'Anda belum memiliki toko. Silakan hubungi admin.');
    header("Location: index.php");
    exit;
}

if ($store) {
    $_SESSION['current_store_id'] = $store['store_id'];
    $_SESSION['current_store_name'] = $store['nama_toko'];
    $_SESSION['current_store_role'] = $store['role'];
}

function getProdukPos($store_id, $search = '', $kategori_id = null) {
    $conn = koneksi();
    $sql = "SELECT pp.*, kp.nama_kategori 
            FROM produk_pos pp 
            LEFT JOIN kategori_produk_pos kp ON pp.kategori_id = kp.id 
            WHERE pp.store_id = ? AND pp.status = 'active'";
    
    if ($search) {
        $sql .= " AND (pp.nama_produk LIKE ? OR pp.kode_barcode LIKE ?)";
    }
    
    if ($kategori_id) {
        $sql .= " AND pp.kategori_id = ?";
    }
    
    $sql .= " ORDER BY pp.nama_produk ASC";
    
    $stmt = $conn->prepare($sql);
    if ($search && $kategori_id) {
        $searchParam = "%$search%";
        $stmt->bind_param("iss", $store_id, $searchParam, $searchParam, $kategori_id);
    } else if ($search) {
        $searchParam = "%$search%";
        $stmt->bind_param("iss", $store_id, $searchParam, $searchParam);
    } else if ($kategori_id) {
        $stmt->bind_param("ii", $store_id, $kategori_id);
    } else {
        $stmt->bind_param("i", $store_id);
    }
    $stmt->execute();
    return $stmt->get_result();
}

function getKategoriPos($store_id) {
    $conn = koneksi();
    $stmt = $conn->prepare("SELECT * FROM kategori_produk_pos WHERE store_id = ? AND status = 'active' ORDER BY nama_kategori");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    return $stmt->get_result();
}

$search = $_GET['search'] ?? '';
$kategori_id = $_GET['kategori'] ?? null;
$produkList = getProdukPos($_SESSION['current_store_id'] ?? 0, $search, $kategori_id);
$kategoriList = getKategoriPos($_SESSION['current_store_id'] ?? 0);
?>
<?php include 'layout.php'; ?>

<style>
.pos-container {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 20px;
    height: calc(100vh - 180px);
}

@media (max-width: 1024px) {
    .pos-container {
        grid-template-columns: 1fr;
    }
    .keranjang-panel {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: 50vh;
        z-index: 50;
        border-radius: 20px 20px 0 0;
    }
}

.produk-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
    overflow-y: auto;
    max-height: calc(100vh - 280px);
    padding-right: 8px;
}

.produk-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.produk-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 83, 216, 0.15);
}

.produk-card .harga {
    color: var(--primary);
    font-weight: 700;
    font-size: 16px;
    margin-top: 8px;
}

.produk-card .nama {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.produk-card .stok {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 4px;
}

.keranjang-panel {
    background: white;
    border-radius: 16px;
    border: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.keranjang-header {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.keranjang-items {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
}

.keranjang-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: var(--primary-50);
    border-radius: 10px;
    margin-bottom: 8px;
}

.keranjang-item .info {
    flex: 1;
}

.keranjang-item .nama {
    font-size: 13px;
    font-weight: 600;
}

.keranjang-item .harga {
    font-size: 12px;
    color: var(--text-secondary);
}

.keranjang-item .qty-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.keranjang-item .qty-btn {
    width: 24px;
    height: 24px;
    border: none;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

.keranjang-item .qty {
    font-weight: 600;
    min-width: 20px;
    text-align: center;
}

.keranjang-item .total {
    font-weight: 700;
    color: var(--primary);
    min-width: 70px;
    text-align: right;
}

.keranjang-item .delete-btn {
    background: none;
    border: none;
    color: var(--error);
    cursor: pointer;
    padding: 4px;
}

.keranjang-footer {
    padding: 16px;
    border-top: 1px solid var(--border);
    background: var(--bg);
}

.keranjang-total {
    display: flex;
    justify-content: space-between;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 16px;
}

.keranjang-total .label {
    color: var(--text);
}

.keranjang-total .amount {
    color: var(--primary);
    font-size: 24px;
}

.bayar-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.bayar-btn {
    padding: 14px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.bayar-btn.qris {
    background: var(--primary);
    color: white;
}

.bayar-btn.qris:hover {
    background: var(--primary-light);
}

.bayar-btn.cash {
    background: var(--success);
    color: white;
}

.bayar-btn.cash:hover {
    background: #16a34a;
}

.bayar-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 100;
    align-items: center;
    justify-content: center;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 16px;
    padding: 24px;
    width: 90%;
    max-width: 400px;
    animation: popIn 0.3s;
}

.modal-header {
    text-align: center;
    margin-bottom: 20px;
}

.modal-header h3 {
    font-size: 18px;
    margin-bottom: 4px;
}

.modal-header p {
    color: var(--text-secondary);
    font-size: 14px;
}

.qris-display {
    text-align: center;
    padding: 20px;
}

.qris-display img {
    max-width: 250px;
    border-radius: 12px;
}

.qris-timer {
    margin-top: 16px;
    font-size: 14px;
    color: var(--text-secondary);
}

.qris-timer .time {
    font-weight: 700;
    color: var(--error);
}

.cash-input {
    margin-bottom: 20px;
}

.cash-input label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
}

.cash-input input {
    width: 100%;
    padding: 14px;
    border: 2px solid var(--border);
    border-radius: 10px;
    font-size: 18px;
    font-weight: 600;
    text-align: right;
}

.cash-input input:focus {
    outline: none;
    border-color: var(--primary);
}

.cash-result {
    display: flex;
    justify-content: space-between;
    padding: 12px;
    background: var(--primary-50);
    border-radius: 10px;
    margin-bottom: 16px;
}

.cash-result .label {
    font-weight: 600;
}

.cash-result .amount {
    font-weight: 700;
    font-size: 18px;
    color: var(--success);
}

.manual-input {
    margin-bottom: 16px;
}

.manual-input input,
.manual-input textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
}

.manual-input input:focus,
.manual-input textarea:focus {
    outline: none;
    border-color: var(--primary);
}

.manual-input label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
}

.manual-input textarea {
    resize: vertical;
    min-height: 60px;
}

.empty-cart {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.empty-cart i {
    font-size: 48px;
    margin-bottom: 12px;
    opacity: 0.3;
}

.search-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
}

.search-bar input {
    flex: 1;
    padding: 12px 16px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
}

.search-bar input:focus {
    outline: none;
    border-color: var(--primary);
}

.search-bar .scan-btn {
    padding: 12px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
}

.store-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: var(--primary-50);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: var(--primary);
    margin-left: 12px;
}
</style>

<div class="pos-container">
    <div class="produk-panel">
        <div class="search-bar">
            <input type="text" id="searchProduk" placeholder="Cari produk atau scan barcode..." value="<?= htmlspecialchars($search) ?>">
            <button class="scan-btn" onclick="doSearch()">
                <i class="fas fa-search"></i>
            </button>
            <button class="scan-btn" onclick="openManualModal()" style="background: var(--success);">
                <i class="fas fa-plus"></i> Manual
            </button>
        </div>
        
        <div class="kategori-tabs" style="display: flex; gap: 8px; margin-bottom: 16px; overflow-x: auto; padding-bottom: 8px;">
            <a href="pos.php" class="kategori-btn <?= !$kategori_id ? 'active' : '' ?>" style="padding: 8px 16px; border: none; background: <?= !$kategori_id ? 'var(--primary)' : 'white' ?>; color: <?= !$kategori_id ? 'white' : 'var(--text)' ?>; border-radius: 20px; font-size: 13px; font-weight: 600; white-space: nowrap; text-decoration: none;">Semua</a>
            <?php 
            $kategoriList = getKategoriPos($_SESSION['current_store_id'] ?? 0);
            while($kat = $kategoriList->fetch_assoc()): ?>
            <a href="pos.php?kategori=<?= $kat['id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="kategori-btn <?= $kategori_id == $kat['id'] ? 'active' : '' ?>" style="padding: 8px 16px; border: 1px solid var(--border); background: <?= $kategori_id == $kat['id'] ? 'var(--primary)' : 'white' ?>; color: <?= $kategori_id == $kat['id'] ? 'white' : 'var(--text)' ?>; border-radius: 20px; font-size: 13px; font-weight: 600; white-space: nowrap; text-decoration: none;"><?= htmlspecialchars($kat['nama_kategori']) ?></a>
            <?php endwhile; ?>
        </div>

        <div class="produk-grid" id="produkGrid">
            <?php while($produk = $produkList->fetch_assoc()): ?>
            <div class="produk-card" onclick="tambahKeKeranjang(<?= $produk['id'] ?>, '<?= htmlspecialchars($produk['nama_produk']) ?>', <?= $produk['harga_jual'] ?>, <?= $produk['stok'] ?>)">
                <div class="nama"><?= htmlspecialchars($produk['nama_produk']) ?></div>
                <div class="harga"><?= rupiah($produk['harga_jual']) ?></div>
                <div class="stok">Stok: <?= $produk['stok'] ?></div>
            </div>
            <?php endwhile; ?>
            
            <?php if($produkList->num_rows == 0): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-muted);">
                <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 12px; opacity: 0.3;"></i>
                <p>Belum ada produk POS</p>
                <p style="font-size: 13px;">Silakan tambah produk di Kelola Produk POS</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="keranjang-panel">
        <div class="keranjang-header">
            <div>
                <h3 style="font-size: 16px; margin: 0;">Keranjang</h3>
                <span class="store-badge">
                    <i class="fas fa-store"></i>
                    <?= htmlspecialchars($_SESSION['current_store_name'] ?? 'Toko') ?>
                </span>
            </div>
            <button onclick="clearKeranjang()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 13px;">
                <i class="fas fa-trash"></i> Clear
            </button>
        </div>
        
        <div class="keranjang-items" id="keranjangItems">
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <p>Keranjang kosong</p>
            </div>
        </div>
        
        <div class="keranjang-footer">
            <div class="keranjang-total">
                <span class="label">Total</span>
                <span class="amount" id="totalBayar">Rp 0</span>
            </div>
            <div class="bayar-buttons">
                <button class="bayar-btn qris" onclick="bayarQRIS()" id="btnQRIS" disabled>
                    <i class="fas fa-qrcode"></i> QRIS
                </button>
                <button class="bayar-btn cash" onclick="bayarCash()" id="btnCash" disabled>
                    <i class="fas fa-money-bill-wave"></i> Cash
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="modalManual">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Tambah Manual</h3>
            <p>Masukkan nominal dan nama produk</p>
        </div>
        <div class="manual-input">
            <label>Nama Produk</label>
            <input type="text" id="manualNama" placeholder="Contoh: Gas 3kg">
        </div>
        <div class="manual-input">
            <label>Harga (Rp)</label>
            <input type="number" id="manualHarga" placeholder="0">
        </div>
        <button class="btn-primary" style="width: 100%;" onclick="tambahManual()">
            <i class="fas fa-plus"></i> Tambah ke Keranjang
        </button>
        <button onclick="closeModal('modalManual')" style="width: 100%; margin-top: 10px; padding: 12px; background: transparent; border: 1px solid var(--border); border-radius: 10px; cursor: pointer;">Batal</button>
    </div>
</div>

<div class="modal" id="modalQRIS">
    <div class="modal-content" style="max-width: 360px; padding: 0; border-radius: 24px; overflow: hidden; background: #fff; box-shadow: 0 24px 80px rgba(0,0,0,0.15);">
        <div style="padding: 32px 24px 24px; text-align: center;">
            <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-qrcode" style="font-size: 28px; color: white;"></i>
            </div>
            <h3 style="font-size: 20px; font-weight: 600; color: #1d1d1f; margin-bottom: 4px;">Scan QRIS</h3>
            <p style="font-size: 13px; color: #86868b;">Pindai dengan aplikasi banco</p>
        </div>
        
        <div style="padding: 0 24px 24px;">
            <div style="background: #f5f5f7; border-radius: 20px; padding: 20px; margin-bottom: 16px;">
                <img id="qrisImage" src="" alt="QRIS" style="width: 180px; height: 180px; display: block; margin: 0 auto; border-radius: 8px;">
            </div>
            
            <div style="display: flex; justify-content: center; align-items: center; gap: 6px; margin-bottom: 4px;">
                <div style="width: 8px; height: 8px; background: #ff3b30; border-radius: 50%; animation: pulse 1s infinite;"></div>
                <span style="font-size: 12px; color: #86868b;">Berlaku hingga</span>
            </div>
            <div id="qrisTimer" style="font-size: 28px; font-weight: 600; color: #1d1d1f; letter-spacing: 1px; font-variant-numeric: tabular-nums;">05:00</div>
            
            <div style="margin-top: 16px; padding: 16px; background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%); border-radius: 16px; color: white;">
                <div style="font-size: 11px; opacity: 0.8;">Total Pembayaran</div>
                <div id="qrisAmount" style="font-size: 24px; font-weight: 700;"></div>
            </div>
        </div>
        
        <div style="padding: 0 24px 24px;">
            <button onclick="cancelQRIS()" style="width: 100%; padding: 14px; background: #f5f5f7; color: #007AFF; border: none; border-radius: 14px; cursor: pointer; font-weight: 600; font-size: 15px; transition: background 0.2s;">
                Batal
            </button>
        </div>
    </div>
    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</div>

<div class="modal" id="modalCash">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Pembayaran Cash</h3>
            <p>Total: <span id="cashTotal" style="font-weight: 700; color: var(--primary);"></span></p>
        </div>
        <div class="cash-input">
            <label>Uang Diberikan (Rp)</label>
            <input type="number" id="uangDiberikan" placeholder="0" onkeyup="hitungkembalian()">
        </div>
        <div class="cash-result">
            <span class="label">Kembalian</span>
            <span class="amount" id="kembalian">Rp 0</span>
        </div>
        <button class="btn-primary" style="width: 100%;" onclick="prosesCash()" id="btnProsesCash">
            <i class="fas fa-check"></i> Proses Pembayaran
        </button>
        <button onclick="closeModal('modalCash')" style="width: 100%; margin-top: 10px; padding: 12px; background: transparent; border: 1px solid var(--border); border-radius: 10px; cursor: pointer;">Batal</button>
    </div>
</div>

<div class="modal" id="modalSuccess">
    <div class="modal-content" style="max-width: 340px; padding: 0; border-radius: 24px; overflow: hidden; background: #fff; box-shadow: 0 24px 80px rgba(0,0,0,0.15);">
        <div style="padding: 32px 24px; text-align: center; background: linear-gradient(180deg, #d1fae5 0%, #a7f3d0 100%);">
            <div style="width: 72px; height: 72px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);">
                <i class="fas fa-check" style="font-size: 36px; color: #10b981;"></i>
            </div>
            <h3 style="font-size: 22px; font-weight: 600; color: #1d1d1f; margin-bottom: 4px;">Pembayaran Berhasil</h3>
            <p style="font-size: 13px; color: #86868b;">Terima kasih telah belanja</p>
        </div>
        
        <div style="padding: 24px;">
            <p id="successInvoice" style="font-size: 13px; color: #86868b; background: #f5f5f7; padding: 14px; border-radius: 12px; margin-bottom: 20px; font-family: 'SF Mono', Menlo, monospace;"></p>
            <div style="display: flex; gap: 10px;">
                <button onclick="cetakStruk()" style="flex: 1; padding: 14px; background: #007AFF; color: white; border: none; border-radius: 14px; cursor: pointer; font-weight: 600; font-size: 15px;">
                    <i class="fas fa-print" style="margin-right: 6px;"></i> Cetak
                </button>
                <button onclick="document.getElementById('modalSuccess').classList.remove('show'); resetPOS();" style="flex: 1; padding: 14px; background: #f5f5f7; color: #007AFF; border: none; border-radius: 14px; cursor: pointer; font-weight: 600; font-size: 15px;">
                    Baru
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="modalReceipt">
    <div class="modal-content" style="max-width: 340px; max-height: 85vh; overflow-y: auto; border-radius: 24px; padding: 0; background: #fff; box-shadow: 0 24px 80px rgba(0,0,0,0.15);">
        <div style="padding: 20px 24px; border-bottom: 1px solid #f5f5f7; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 17px; font-weight: 600; color: #1d1d1f;">Struk Pembayaran</h3>
            <button onclick="closeReceipt()" style="background:none;border:none;font-size:24px;cursor:pointer;color: #86868b;">&times;</button>
        </div>
        <div id="receiptContent" style="padding: 20px 24px; text-align: center;"></div>
        <div style="padding: 16px 24px 24px; display:flex;gap:10px; border-top: 1px solid #f5f5f7;">
            <button onclick="printReceipt()" style="flex:1; padding: 14px; background: #007AFF; color: white; border: none; border-radius: 14px; cursor: pointer; font-weight: 600; font-size: 15px;">
                <i class="fas fa-print" style="margin-right: 6px;"></i> Print
            </button>
            <button onclick="closeReceipt()" style="flex:1; padding: 14px; background: #f5f5f7; color: #007AFF; border: none; border-radius: 14px; cursor: pointer; font-weight: 600; font-size: 15px;">
                Tutup
            </button>
        </div>
    </div>
</div>

<script>
let keranjang = [];
let currentInvoice = '';
let qrisPollingInterval = null;

function openManualModal() {
    document.getElementById('modalManual').classList.add('show');
    document.getElementById('manualNama').focus();
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function closeReceipt() {
    document.getElementById('modalReceipt').classList.remove('show');
}

function tambahManual() {
    const nama = document.getElementById('manualNama').value.trim();
    const harga = parseInt(document.getElementById('manualHarga').value);
    
    if (!nama || !harga || harga <= 0) {
        alert('Masukkan nama produk dan harga yang valid!');
        return;
    }
    
    keranjang.push({
        id: 'manual_' + Date.now(),
        nama: nama,
        harga: harga,
        qty: 1,
        isManual: true,
        stok: 9999
    });
    
    document.getElementById('manualNama').value = '';
    document.getElementById('manualHarga').value = '';
    closeModal('modalManual');
    renderKeranjang();
}

function tambahKeKeranjang(id, nama, harga, stok) {
    const existing = keranjang.find(item => item.id === id);
    
    if (existing) {
        if (existing.qty < stok || existing.isManual) {
            existing.qty++;
        } else {
            alert('Stok tidak cukup!');
            return;
        }
    } else {
        keranjang.push({
            id: id,
            nama: nama,
            harga: harga,
            qty: 1,
            isManual: false,
            stok: stok
        });
    }
    
    renderKeranjang();
}

function updateQty(id, change) {
    const item = keranjang.find(i => i.id === id);
    if (!item) return;
    
    const newQty = item.qty + change;
    
    if (newQty <= 0) {
        hapusItem(id);
        return;
    }
    
    if (!item.isManual && newQty > item.stok) {
        alert('Stok tidak cukup!');
        return;
    }
    
    item.qty = newQty;
    renderKeranjang();
}

function hapusItem(id) {
    keranjang = keranjang.filter(item => item.id !== id);
    renderKeranjang();
}

function clearKeranjang() {
    if (keranjang.length > 0 && !confirm('Yakin kosongkan keranjang?')) return;
    keranjang = [];
    renderKeranjang();
}

function renderKeranjang() {
    const container = document.getElementById('keranjangItems');
    
    if (keranjang.length === 0) {
        container.innerHTML = `
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <p>Keranjang kosong</p>
            </div>
        `;
        document.getElementById('btnQRIS').disabled = true;
        document.getElementById('btnCash').disabled = true;
        document.getElementById('totalBayar').textContent = 'Rp 0';
        return;
    }
    
    let html = '';
    let total = 0;
    
    keranjang.forEach(item => {
        const itemTotal = item.harga * item.qty;
        total += itemTotal;
        
        html += `
            <div class="keranjang-item">
                <div class="info">
                    <div class="nama">${item.nama}</div>
                    <div class="harga">${formatRupiah(item.harga)} x ${item.qty}</div>
                </div>
                <div class="qty-controls">
                    <button class="qty-btn" onclick="updateQty('${item.id}', -1)">-</button>
                    <span class="qty">${item.qty}</span>
                    <button class="qty-btn" onclick="updateQty('${item.id}', 1)">+</button>
                </div>
                <div class="total">${formatRupiah(itemTotal)}</div>
                <button class="delete-btn" onclick="hapusItem('${item.id}')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
    });
    
    container.innerHTML = html;
    document.getElementById('totalBayar').textContent = formatRupiah(total);
    document.getElementById('btnQRIS').disabled = false;
    document.getElementById('btnCash').disabled = false;
}

function formatRupiah(angka) {
    return 'Rp ' + angka.toLocaleString('id-ID');
}

function hitungkembalian() {
    const total = getTotal();
    const uang = parseInt(document.getElementById('uangDiberikan').value) || 0;
    const kembalian = uang - total;
    
    document.getElementById('kembalian').textContent = formatRupiah(Math.max(0, kembalian));
    
    const btn = document.getElementById('btnProsesCash');
    btn.disabled = uang < total;
}

function getTotal() {
    return keranjang.reduce((sum, item) => sum + (item.harga * item.qty), 0);
}

async function bayarQRIS() {
    const total = getTotal();
    if (total <= 0) return;
    
    document.getElementById('qrisAmount').textContent = formatRupiah(total);
    document.getElementById('modalQRIS').classList.add('show');
    
    const reference = 'POS-' + Date.now();
    currentInvoice = reference;
    
    try {
        const formData = new FormData();
        formData.append('reference', reference);
        formData.append('amount', total);
        
        const response = await fetch('api/pos_qris_create.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success && result.qris_image) {
            document.getElementById('qrisImage').src = result.qris_image;
            
            let minutes = 30;
            let seconds = 0;
            
            if (qrisPollingInterval) clearInterval(qrisPollingInterval);
            
            qrisPollingInterval = setInterval(async () => {
                seconds--;
                if (seconds < 0) {
                    seconds = 59;
                    minutes--;
                }
                
                document.getElementById('qrisTimer').textContent = 
                    String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                
                if (minutes < 0) {
                    clearInterval(qrisPollingInterval);
                    alert('QRIS kadaluarsa! Silakan coba lagi.');
                    closeModal('modalQRIS');
                    return;
                }
                
                const statusResponse = await fetch('api/pos_qris_status.php?reference=' + reference);
                const statusResult = await statusResponse.json();
                
                if (statusResult.paid) {
                    clearInterval(qrisPollingInterval);
                    simpanTransaksi('qris', reference, result.qris_string);
                }
            }, 1000);
        } else {
            alert('Gagal generate QRIS: ' + (result.message || 'Unknown error'));
            closeModal('modalQRIS');
        }
    } catch (error) {
        alert('Error: ' + error.message);
        closeModal('modalQRIS');
    }
}

async function cancelQRIS() {
    if (qrisPollingInterval) {
        clearInterval(qrisPollingInterval);
        qrisPollingInterval = null;
    }
    closeModal('modalQRIS');
}

function bayarCash() {
    const total = getTotal();
    document.getElementById('cashTotal').textContent = formatRupiah(total);
    document.getElementById('uangDiberikan').value = '';
    document.getElementById('kembalian').textContent = 'Rp 0';
    document.getElementById('btnProsesCash').disabled = true;
    document.getElementById('modalCash').classList.add('show');
    document.getElementById('uangDiberikan').focus();
}

async function prosesCash() {
    const total = getTotal();
    const uang = parseInt(document.getElementById('uangDiberikan').value);
    
    if (uang < total) {
        alert('Uang tidak cukup!');
        return;
    }
    
    const reference = 'POS-' + Date.now();
    currentInvoice = reference;
    
    await simpanTransaksi('cash', reference, null, uang);
}

async function simpanTransaksi(metode, reference, qrisString = null, uangDiberikan = 0) {
    try {
        const formData = new FormData();
        formData.append('metode_bayar', metode);
        formData.append('reference', reference);
        formData.append('qris_string', qrisString || '');
        formData.append('uang_diberikan', uangDiberikan);
        formData.append('items', JSON.stringify(keranjang));
        
        const response = await fetch('api/pos_simpan.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            currentInvoice = result.invoice;
            
            if (metode === 'qris') {
                closeModal('modalQRIS');
            } else {
                closeModal('modalCash');
            }
            
            document.getElementById('successInvoice').textContent = 'Invoice: ' + result.invoice;
            document.getElementById('modalSuccess').classList.add('show');
            
            if (qrisPollingInterval) {
                clearInterval(qrisPollingInterval);
                qrisPollingInterval = null;
            }
        } else {
            alert('Gagal menyimpan transaksi: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

function resetPOS() {
    keranjang = [];
    currentInvoice = '';
    document.getElementById('modalReceipt').classList.remove('show');
    renderKeranjang();
}

function cetakStruk() {
    if (!currentInvoice) {
        alert('Invoice tidak ditemukan');
        return;
    }
    
    fetch('pos_cetak.php?invoice=' + currentInvoice + '&ajax=1')
        .then(response => response.text())
        .then(html => {
            document.getElementById('receiptContent').innerHTML = html;
            document.getElementById('modalReceipt').classList.add('show');
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
    document.getElementById('modalReceipt').classList.remove('show');
}

function doSearch() {
    const search = document.getElementById('searchProduk').value;
    const urlParams = new URLSearchParams(window.location.search);
    const kategori = urlParams.get('kategori');
    let url = 'pos.php?';
    if (search) url += 'search=' + encodeURIComponent(search);
    if (kategori) {
        if (search) url += '&';
        url += 'kategori=' + kategori;
    }
    window.location.href = url;
}

document.getElementById('searchProduk').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        doSearch();
    }
});

renderKeranjang();
</script>

<?php include 'layout_footer.php'; ?>
