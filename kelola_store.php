<?php
session_start();
require_once 'config.php';

cekLogin();
cekAdmin();

$currentPage = 'kelola_store';
$pageTitle = 'Kelola Store';
$pageIcon = 'fas fa-building';
$pageDesc = 'Kelola semua toko yang terdaftar';

$conn = koneksi();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        
        if ($_POST['action'] == 'create_store') {
            $nama_toko = trim($_POST['nama_toko']);
            $slug = trim($_POST['slug']) ?: strtolower(str_replace(' ', '-', preg_replace('/[^a-zA-Z0-9 ]/', '', $nama_toko)));
            $alamat = trim($_POST['alamat']);
            $no_hp = trim($_POST['no_hp']);
            $email = trim($_POST['email']);
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $nama_lengkap = trim($_POST['nama_lengkap']);
            
            $api_key = 'sk_' . bin2hex(random_bytes(16));
            
            $conn->begin_transaction();
            
            try {
                $stmt = $conn->prepare("INSERT INTO stores (nama_toko, slug, alamat, no_hp, email, api_key, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $stmt->bind_param("ssssss", $nama_toko, $slug, $alamat, $no_hp, $email, $api_key);
                $stmt->execute();
                $store_id = $conn->insert_id;
                
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'member';
                $status = 'active';
                $force2FA = 'yes';
                $stmtUser = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, no_hp, role, status, force_2fa) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtUser->bind_param("ssssssss", $username, $password_hash, $nama_lengkap, $email, $no_hp, $role, $status, $force2FA);
                $stmtUser->execute();
                $user_id = $conn->insert_id;
                
                $stmtStoreUser = $conn->prepare("INSERT INTO store_users (store_id, user_id, role) VALUES (?, ?, 'owner')");
                $stmtStoreUser->bind_param("ii", $store_id, $user_id);
                $stmtStoreUser->execute();
                
                $conn->commit();
                setAlert('success', "Toko '$nama_toko' berhasil dibuat. Username: $username");
            } catch (Exception $e) {
                $conn->rollback();
                setAlert('error', 'Gagal membuat toko: ' . $e->getMessage());
            }
        }
        
        if ($_POST['action'] == 'update_store') {
            $store_id = (int)$_POST['store_id'];
            $nama_toko = trim($_POST['nama_toko']);
            $slug = trim($_POST['slug']);
            $alamat = trim($_POST['alamat']);
            $no_hp = trim($_POST['no_hp']);
            $email = trim($_POST['email']);
            $status = $_POST['status'];
            
            $stmt = $conn->prepare("UPDATE stores SET nama_toko = ?, slug = ?, alamat = ?, no_hp = ?, email = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $nama_toko, $slug, $alamat, $no_hp, $email, $status, $store_id);
            
            if ($stmt->execute()) {
                setAlert('success', 'Toko berhasil diupdate');
            } else {
                setAlert('error', 'Gagal mengupdate toko');
            }
        }
        
        if ($_POST['action'] == 'delete_store') {
            $store_id = (int)$_POST['store_id'];
            
            $stmt = $conn->prepare("DELETE FROM stores WHERE id = ?");
            $stmt->bind_param("i", $store_id);
            
            if ($stmt->execute()) {
                setAlert('success', 'Toko berhasil dihapus');
            } else {
                setAlert('error', 'Gagal menghapus toko');
            }
        }
        
        if ($_POST['action'] == 'reset_api_key') {
            $store_id = (int)$_POST['store_id'];
            $api_key = 'sk_' . bin2hex(random_bytes(16));
            
            $stmt = $conn->prepare("UPDATE stores SET api_key = ? WHERE id = ?");
            $stmt->bind_param("si", $api_key, $store_id);
            
            if ($stmt->execute()) {
                setAlert('success', 'API Key berhasil di-reset');
            } else {
                setAlert('error', 'Gagal reset API Key');
            }
        }
        
        header("Location: kelola_store.php");
        exit;
    }
}

$stores = $conn->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM store_users WHERE store_id = s.id) as total_users,
           (SELECT COUNT(*) FROM transaksi_pos WHERE store_id = s.id AND status = 'success' AND DATE(created_at) = CURDATE()) as transaksi_hari_ini,
           (SELECT COALESCE(SUM(total_bayar), 0) FROM transaksi_pos WHERE store_id = s.id AND status = 'success' AND DATE(created_at) = CURDATE()) as pendapatan_hari_ini
    FROM stores s
    ORDER BY s.created_at DESC
");

$alert = getAlert();
?>
<?php include 'layout.php'; ?>

<style>
.action-bar {
    margin-bottom: 24px;
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

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.store-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.store-card {
    background: white;
    border-radius: 12px;
    border: 1px solid var(--border);
    overflow: hidden;
}

.store-header {
    padding: 16px;
    background: var(--primary-50);
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.store-name {
    font-size: 16px;
    font-weight: 700;
}

.store-slug {
    font-size: 12px;
    color: var(--text-secondary);
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

.status-suspended {
    background: #fef3c7;
    color: #92400e;
}

.status-inactive {
    background: #fee2e2;
    color: #991b1b;
}

.store-body {
    padding: 16px;
}

.store-info {
    margin-bottom: 16px;
}

.store-info-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    padding: 6px 0;
    border-bottom: 1px solid var(--border);
}

.store-info-row:last-child {
    border-bottom: none;
}

.store-info-row .label {
    color: var(--text-secondary);
}

.store-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
}

.stat-box {
    background: var(--bg);
    padding: 12px;
    border-radius: 8px;
    text-align: center;
}

.stat-box .value {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
}

.stat-box .label {
    font-size: 11px;
    color: var(--text-secondary);
}

.store-actions {
    display: flex;
    gap: 8px;
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
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    font-size: 18px;
}

.btn-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--text-muted);
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
</style>

<div class="action-bar">
    <button class="btn btn-primary" onclick="openModal('modalTambah')">
        <i class="fas fa-plus"></i> Tambah Toko
    </button>
</div>

<div class="store-grid">
    <?php while($store = $stores->fetch_assoc()): ?>
    <div class="store-card">
        <div class="store-header">
            <div>
                <div class="store-name"><?= htmlspecialchars($store['nama_toko']) ?></div>
                <div class="store-slug"><?= htmlspecialchars($store['slug'] ?? '-') ?></div>
            </div>
            <span class="status-badge status-<?= $store['status'] ?>"><?= ucfirst($store['status']) ?></span>
        </div>
        
        <div class="store-body">
            <div class="store-stats">
                <div class="stat-box">
                    <div class="value"><?= $store['transaksi_hari_ini'] ?></div>
                    <div class="label">Transaksi Hari Ini</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?= rupiah($store['pendapatan_hari_ini']) ?></div>
                    <div class="label">Pendapatan Hari Ini</div>
                </div>
            </div>
            
            <div class="store-info">
                <div class="store-info-row">
                    <span class="label">User</span>
                    <span><?= $store['total_users'] ?> orang</span>
                </div>
                <div class="store-info-row">
                    <span class="label">No. HP</span>
                    <span><?= htmlspecialchars($store['no_hp'] ?? '-') ?></span>
                </div>
                <div class="store-info-row">
                    <span class="label">Email</span>
                    <span><?= htmlspecialchars($store['email'] ?? '-') ?></span>
                </div>
                <div class="store-info-row">
                    <span class="label">API Key</span>
                    <span style="font-family: monospace; font-size: 11px;"><?= substr($store['api_key'], 0, 12) ?>...</span>
                </div>
            </div>
            
            <div class="store-actions">
                <button class="btn btn-sm btn-outline" onclick="editStore(<?= htmlspecialchars(json_encode($store)) ?>)">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-sm btn-outline" onclick="resetApiKey(<?= $store['id'] ?>, '<?= htmlspecialchars($store['nama_toko']) ?>')">
                    <i class="fas fa-key"></i> Reset API
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteStore(<?= $store['id'] ?>, '<?= htmlspecialchars($store['nama_toko']) ?>')">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
    
    <?php if($stores->num_rows == 0): ?>
    <div style="grid-column: 1/-1; text-align: center; padding: 60px; color: var(--text-muted);">
        <i class="fas fa-building" style="font-size: 60px; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
        <p>Belum ada toko</p>
        <button class="btn btn-primary" style="margin-top: 16px;" onclick="openModal('modalTambah')">
            <i class="fas fa-plus"></i> Tambah Toko Pertama
        </button>
    </div>
    <?php endif; ?>
</div>

<div class="modal" id="modalTambah">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Tambah Toko Baru</h3>
            <button class="btn-close" onclick="closeModal('modalTambah')">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="create_store">
            
            <div class="form-group">
                <label>Nama Toko</label>
                <input type="text" name="nama_toko" required placeholder="Contoh: Minimarket Sejahtera">
            </div>
            
            <div class="form-group">
                <label>Slug (URL)</label>
                <input type="text" name="slug" placeholder="auto-generate jika kosong">
            </div>
            
            <div class="form-group">
                <label>Alamat</label>
                <input type="text" name="alamat" placeholder="Alamat toko">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>No. HP</label>
                    <input type="text" name="no_hp" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
            </div>
            
            <hr style="margin: 20px 0; border: none; border-top: 1px solid var(--border);">
            
            <h4 style="margin-bottom: 16px; font-size: 14px;">Akun Owner</h4>
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label>Nama Lengkap Owner</label>
                <input type="text" name="nama_lengkap" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-plus"></i> Buat Toko
            </button>
        </form>
    </div>
</div>

<div class="modal" id="modalEdit">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Toko</h3>
            <button class="btn-close" onclick="closeModal('modalEdit')">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="update_store">
            <input type="hidden" name="store_id" id="editStoreId">
            
            <div class="form-group">
                <label>Nama Toko</label>
                <input type="text" name="nama_toko" id="editNamaToko" required>
            </div>
            
            <div class="form-group">
                <label>Slug</label>
                <input type="text" name="slug" id="editSlug">
            </div>
            
            <div class="form-group">
                <label>Alamat</label>
                <input type="text" name="alamat" id="editAlamat">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>No. HP</label>
                    <input type="text" name="no_hp" id="editNoHp">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="editEmail">
                </div>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="editStatus">
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-save"></i> Simpan
            </button>
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

function editStore(data) {
    document.getElementById('editStoreId').value = data.id;
    document.getElementById('editNamaToko').value = data.nama_toko;
    document.getElementById('editSlug').value = data.slug || '';
    document.getElementById('editAlamat').value = data.alamat || '';
    document.getElementById('editNoHp').value = data.no_hp || '';
    document.getElementById('editEmail').value = data.email || '';
    document.getElementById('editStatus').value = data.status;
    openModal('modalEdit');
}

function resetApiKey(id, nama) {
    if (confirm('Reset API Key untuk "' + nama + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="reset_api_key">
            <input type="hidden" name="store_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteStore(id, nama) {
    if (confirm('Yakin HAPUS toko "' + nama + '"? Semua data akan hilang!')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_store">
            <input type="hidden" name="store_id" value="${id}">
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
