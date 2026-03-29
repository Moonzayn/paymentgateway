<?php
session_start();
require_once 'config.php';

cekLogin();

$currentPage = 'kelola_produk_pos';
$pageTitle = 'Kelola Produk POS';
$pageIcon = 'fas fa-boxes';
$pageDesc = 'Kelola produk minimarket untuk POS';

$conn = koneksi();

$conn = koneksi();

function getUserStore($user_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT su.store_id, su.role, s.nama_toko 
        FROM store_users su 
        JOIN stores s ON su.store_id = s.id 
        WHERE su.user_id = ?
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
$_SESSION['current_store_name'] = $store['nama_toko'];

function getKategoriPos($store_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM kategori_produk_pos WHERE store_id = ? ORDER BY nama_kategori");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    return $stmt->get_result();
}

function getProdukPos($store_id, $kategori_id = null) {
    global $conn;
    $sql = "SELECT pp.*, kp.nama_kategori 
            FROM produk_pos pp 
            LEFT JOIN kategori_produk_pos kp ON pp.kategori_id = kp.id 
            WHERE pp.store_id = ?";
    
    if ($kategori_id) {
        $sql .= " AND pp.kategori_id = ?";
    }
    
    $sql .= " ORDER BY pp.nama_produk ASC";
    
    $stmt = $conn->prepare($sql);
    if ($kategori_id) {
        $stmt->bind_param("ii", $store_id, $kategori_id);
    } else {
        $stmt->bind_param("i", $store_id);
    }
    $stmt->execute();
    return $stmt->get_result();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add_kategori') {
            $nama_kategori = trim($_POST['nama_kategori']);
            $icon = $_POST['icon'] ?? 'fas fa-tag';
            
            $stmt = $conn->prepare("INSERT INTO kategori_produk_pos (store_id, nama_kategori, icon) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $store_id, $nama_kategori, $icon);
            
            if ($stmt->execute()) {
                setAlert('success', 'Kategori berhasil ditambahkan');
            } else {
                setAlert('error', 'Gagal menambahkan kategori');
            }
        }
        
        if ($_POST['action'] == 'add_produk') {
            $kategori_id = $_POST['kategori_id'] ?: null;
            $kode_barcode = trim($_POST['kode_barcode']);
            $nama_produk = trim($_POST['nama_produk']);
            $harga_jual = (int)$_POST['harga_jual'];
            $harga_modal = (int)$_POST['harga_modal'];
            $stok = (int)$_POST['stok'];
            
            $stmt = $conn->prepare("
                INSERT INTO produk_pos (store_id, kategori_id, kode_barcode, nama_produk, harga_jual, harga_modal, stok)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissiii", $store_id, $kategori_id, $kode_barcode, $nama_produk, $harga_jual, $harga_modal, $stok);
            
            if ($stmt->execute()) {
                setAlert('success', 'Produk berhasil ditambahkan');
            } else {
                setAlert('error', 'Gagal menambahkan produk');
            }
        }
        
        if ($_POST['action'] == 'edit_produk') {
            $produk_id = (int)$_POST['produk_id'];
            $kategori_id = $_POST['kategori_id'] ?: null;
            $kode_barcode = trim($_POST['kode_barcode']);
            $nama_produk = trim($_POST['nama_produk']);
            $harga_jual = (int)$_POST['harga_jual'];
            $harga_modal = (int)$_POST['harga_modal'];
            $stok = (int)$_POST['stok'];
            $status = $_POST['status'];
            
            $stmt = $conn->prepare("
                UPDATE produk_pos 
                SET kategori_id = ?, kode_barcode = ?, nama_produk = ?, harga_jual = ?, harga_modal = ?, stok = ?, status = ?
                WHERE id = ? AND store_id = ?
            ");
            $stmt->bind_param("issiiisii", $kategori_id, $kode_barcode, $nama_produk, $harga_jual, $harga_modal, $stok, $status, $produk_id, $store_id);
            
            if ($stmt->execute()) {
                setAlert('success', 'Produk berhasil diupdate');
            } else {
                setAlert('error', 'Gagal mengupdate produk');
            }
        }
        
        if ($_POST['action'] == 'delete_produk') {
            $produk_id = (int)$_POST['produk_id'];
            
            $stmt = $conn->prepare("DELETE FROM produk_pos WHERE id = ? AND store_id = ?");
            $stmt->bind_param("ii", $produk_id, $store_id);
            
            if ($stmt->execute()) {
                setAlert('success', 'Produk berhasil dihapus');
            } else {
                setAlert('error', 'Gagal menghapus produk');
            }
        }
        
        if ($_POST['action'] == 'import_csv') {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
                $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
                $row = 0;
                $success = 0;
                $failed = 0;
                
                while (($data = fgetcsv($file, 1000, ',')) !== FALSE) {
                    $row++;
                    if ($row == 1) continue;
                    
                    $kode_barcode = trim($data[0] ?? '');
                    $nama_produk = trim($data[1] ?? '');
                    $nama_kategori = trim($data[2] ?? '');
                    $harga_jual = (int)($data[3] ?? 0);
                    $harga_modal = (int)($data[4] ?? 0);
                    $stok = (int)($data[5] ?? 0);
                    
                    if (empty($nama_produk) || $harga_jual <= 0) {
                        $failed++;
                        continue;
                    }
                    
                    $kategori_id = null;
                    if (!empty($nama_kategori)) {
                        $stmtKat = $conn->prepare("SELECT id FROM kategori_produk_pos WHERE store_id = ? AND nama_kategori = ?");
                        $stmtKat->bind_param("is", $store_id, $nama_kategori);
                        $stmtKat->execute();
                        $resultKat = $stmtKat->get_result();
                        if ($kat = $resultKat->fetch_assoc()) {
                            $kategori_id = $kat['id'];
                        } else {
                            $nama_kategori_escape = $conn->real_escape_string($nama_kategori);
                            $conn->query("INSERT INTO kategori_produk_pos (store_id, nama_kategori, icon) VALUES ($store_id, '$nama_kategori_escape', 'fas fa-tag')");
                            $kategori_id = $conn->insert_id;
                        }
                    }
                    
                    $stmt = $conn->prepare("
                        INSERT INTO produk_pos (store_id, kategori_id, kode_barcode, nama_produk, harga_jual, harga_modal, stok)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("iissiii", $store_id, $kategori_id, $kode_barcode, $nama_produk, $harga_jual, $harga_modal, $stok);
                    
                    if ($stmt->execute()) {
                        $success++;
                    } else {
                        $failed++;
                    }
                }
                
                fclose($file);
                setAlert('success', "Import selesai! Berhasil: $success, Gagal: $failed");
            } else {
                setAlert('error', 'Gagal upload file CSV');
            }
        }
        
        header("Location: kelola_produk_pos.php");
        exit;
    }
}

$kategoriList = getKategoriPos($store_id);
$kategori_id = $_GET['kategori'] ?? null;
$produkList = getProdukPos($store_id, $kategori_id);
$alert = getAlert();
?>
<?php include 'layout.php'; ?>

<style>
.action-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
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

.btn-primary:hover {
    background: var(--primary-light);
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-danger {
    background: var(--error);
    color: white;
}

.btn-outline {
    background: white;
    border: 1px solid var(--border);
    color: var(--text);
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.filter-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    overflow-x: auto;
    padding-bottom: 8px;
}

.filter-tabs .tab {
    padding: 8px 16px;
    border: 1px solid var(--border);
    background: white;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
}

.filter-tabs .tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
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

td {
    font-size: 14px;
}

tr:hover {
    background: var(--primary-50);
}

.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.status-active {
    background: #dcfce7;
    color: #166534;
}

.status-inactive {
    background: #fee2e2;
    color: #991b1b;
}

.stok-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.stok-ada {
    background: #dcfce7;
    color: #166534;
}

.stok-habis {
    background: #fee2e2;
    color: #991b1b;
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
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    animation: popIn 0.3s;
}

.modal-header {
    margin-bottom: 20px;
}

.modal-header h3 {
    font-size: 18px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 6px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.download-template {
    margin-top: 10px;
    font-size: 13px;
    color: var(--text-secondary);
}

.download-template a {
    color: var(--primary);
    text-decoration: none;
}

.download-template a:hover {
    text-decoration: underline;
}
</style>

<div class="action-bar">
    <button class="btn btn-primary" onclick="openModal('modalProduk')">
        <i class="fas fa-plus"></i> Tambah Produk
    </button>
    <button class="btn btn-success" onclick="openModal('modalKategori')">
        <i class="fas fa-tag"></i> Tambah Kategori
    </button>
    <button class="btn btn-outline" onclick="openModal('modalImport')">
        <i class="fas fa-file-import"></i> Import CSV
    </button>
</div>

<div class="filter-tabs">
    <a href="?kategori=" class="tab <?= !$kategori_id ? 'active' : '' ?>">Semua</a>
    <?php while($kat = $kategoriList->fetch_assoc()): ?>
    <a href="?kategori=<?= $kat['id'] ?>" class="tab <?= $kategori_id == $kat['id'] ? 'active' : '' ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></a>
    <?php endwhile; ?>
    <?php $kategoriList = getKategoriPos($store_id); ?>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama Produk</th>
                <th>Kategori</th>
                <th>Harga Jual</th>
                <th>Harga Modal</th>
                <th>Stok</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php while($produk = $produkList->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($produk['kode_barcode'] ?? '-') ?></td>
                <td><?= htmlspecialchars($produk['nama_produk']) ?></td>
                <td><?= htmlspecialchars($produk['nama_kategori'] ?? '-') ?></td>
                <td><?= rupiah($produk['harga_jual']) ?></td>
                <td><?= rupiah($produk['harga_modal']) ?></td>
                <td>
                    <span class="stok-badge <?= $produk['stok'] > 0 ? 'stok-ada' : 'stok-habis' ?>">
                        <?= $produk['stok'] ?>
                    </span>
                </td>
                <td>
                    <span class="status-badge <?= $produk['status'] == 'active' ? 'status-active' : 'status-inactive' ?>">
                        <?= $produk['status'] == 'active' ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline" onclick="editProduk(<?= htmlspecialchars(json_encode($produk)) ?>)">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteProduk(<?= $produk['id'] ?>, '<?= htmlspecialchars($produk['nama_produk']) ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
            
            <?php if($produkList->num_rows == 0): ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="fas fa-box-open" style="font-size: 40px; opacity: 0.3; margin-bottom: 12px; display: block;"></i>
                    <p>Belum ada produk</p>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal" id="modalProduk">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalProdukTitle">Tambah Produk</h3>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_produk">
            <input type="hidden" name="produk_id" id="editProdukId">
            
            <div class="form-group">
                <label>Kategori</label>
                <select name="kategori_id" id="editKategoriId">
                    <option value="">- Pilih Kategori -</option>
                    <?php while($kat = $kategoriList->fetch_assoc()): ?>
                    <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                    <?php endwhile; ?>
                    <?php $kategoriList = getKategoriPos($store_id); ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Kode Barcode</label>
                <input type="text" name="kode_barcode" id="editKodeBarcode" placeholder="Opsional">
            </div>
            
            <div class="form-group">
                <label>Nama Produk</label>
                <input type="text" name="nama_produk" id="editNamaProduk" required placeholder="Contoh: Indomie Goreng">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Harga Jual (Rp)</label>
                    <input type="number" name="harga_jual" id="editHargaJual" required min="0">
                </div>
                <div class="form-group">
                    <label>Harga Modal (Rp)</label>
                    <input type="number" name="harga_modal" id="editHargaModal" min="0" value="0">
                </div>
            </div>
            
            <div class="form-group">
                <label>Stok Awal</label>
                <input type="number" name="stok" id="editStok" min="0" value="0">
            </div>
            
            <div class="form-group" id="editStatusGroup" style="display: none;">
                <label>Status</label>
                <select name="status" id="editStatus">
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-save"></i> Simpan
            </button>
            <button type="button" onclick="closeModal('modalProduk')" class="btn btn-outline" style="width: 100%; margin-top: 10px;">Batal</button>
        </form>
    </div>
</div>

<div class="modal" id="modalKategori">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Tambah Kategori</h3>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_kategori">
            
            <div class="form-group">
                <label>Nama Kategori</label>
                <input type="text" name="nama_kategori" required placeholder="Contoh: Mi Instan">
            </div>
            
            <div class="form-group">
                <label>Icon (FontAwesome)</label>
                <input type="text" name="icon" value="fas fa-tag" placeholder="fas fa-tag">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-save"></i> Simpan
            </button>
            <button type="button" onclick="closeModal('modalKategori')" class="btn btn-outline" style="width: 100%; margin-top: 10px;">Batal</button>
        </form>
    </div>
</div>

<div class="modal" id="modalImport">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Import CSV</h3>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import_csv">
            
            <div class="form-group">
                <label>Pilih File CSV</label>
                <input type="file" name="csv_file" accept=".csv" required>
            </div>
            
            <div class="download-template">
                <p>Format CSV:</p>
                <code>kode_barcode,nama_produk,kategori,harga_jual,harga_modal,stok</code>
                <br><br>
                <a href="template_import_produk_pos.csv" download>Download Template CSV</a>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 16px;">
                <i class="fas fa-file-import"></i> Import
            </button>
            <button type="button" onclick="closeModal('modalImport')" class="btn btn-outline" style="width: 100%; margin-top: 10px;">Batal</button>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function editProduk(data) {
    document.getElementById('modalProdukTitle').textContent = 'Edit Produk';
    document.querySelector('#modalProduk input[name="action"]').value = 'edit_produk';
    document.getElementById('editProdukId').value = data.id;
    document.getElementById('editKategoriId').value = data.kategori_id || '';
    document.getElementById('editKodeBarcode').value = data.kode_barcode || '';
    document.getElementById('editNamaProduk').value = data.nama_produk;
    document.getElementById('editHargaJual').value = data.harga_jual;
    document.getElementById('editHargaModal').value = data.harga_modal;
    document.getElementById('editStok').value = data.stok;
    document.getElementById('editStatus').value = data.status;
    document.getElementById('editStatusGroup').style.display = 'block';
    
    openModal('modalProduk');
}

function deleteProduk(id, nama) {
    if (confirm('Yakin hapus produk "' + nama + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_produk">
            <input type="hidden" name="produk_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
    });
});
</script>

<?php include 'layout_footer.php'; ?>
