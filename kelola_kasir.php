<?php
session_start();
require_once 'config.php';

cekLogin();

$currentPage = 'kelola_kasir';
$pageTitle = 'Kelola Kasir & Toko';
$pageIcon = 'fas fa-store';
$pageDesc = 'Kelola kasir dan pengaturan toko';

$conn = koneksi();

function getUserStore($user_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT su.store_id, su.role, s.nama_toko, s.alamat, s.no_hp, s.email, s.logo, s.qr_code, s.slug
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

function getStoreKasir($store_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT su.*, u.username, u.nama_lengkap, u.email, u.no_hp, u.status as user_status
        FROM store_users su
        JOIN users u ON su.user_id = u.id
        WHERE su.store_id = ? AND su.role != 'owner'
        ORDER BY su.created_at DESC
    ");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    return $stmt->get_result();
}

function getAvailableUsers($store_id) {
    global $conn;
    $stmt = $conn->query("
        SELECT u.id, u.username, u.nama_lengkap, u.email, u.no_hp
        FROM users u
        WHERE u.status = 'active'
        AND u.is_super_admin != 'yes'
        AND u.id NOT IN (
            SELECT user_id FROM store_users WHERE store_id = $store_id
        )
        AND u.id NOT IN (
            SELECT user_id FROM store_users su2 
            JOIN stores s2 ON su2.store_id = s2.id 
            WHERE s2.id != $store_id
        )
        ORDER BY u.nama_lengkap
    ");
    return $stmt;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        
        if ($_POST['action'] == 'update_store') {
            $nama_toko = trim($_POST['nama_toko']);
            $alamat = trim($_POST['alamat']);
            $no_hp = trim($_POST['no_hp']);
            $email = trim($_POST['email']);
            $slug = trim($_POST['slug']);
            
            $stmt = $conn->prepare("
                UPDATE stores 
                SET nama_toko = ?, alamat = ?, no_hp = ?, email = ?, slug = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssssi", $nama_toko, $alamat, $no_hp, $email, $slug, $store_id);
            
            if ($stmt->execute()) {
                setAlert('success', 'Pengaturan toko berhasil diupdate');
                $_SESSION['current_store_name'] = $nama_toko;
            } else {
                setAlert('error', 'Gagal mengupdate pengaturan');
            }
        }
        
        if ($_POST['action'] == 'add_kasir') {
            $user_id = (int)$_POST['user_id'];
            $role = $_POST['role'];
            
            $stmt = $conn->prepare("
                INSERT INTO store_users (store_id, user_id, role)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE role = VALUES(role)
            ");
            $stmt->bind_param("iis", $store_id, $user_id, $role);
            
            if ($stmt->execute()) {
                setAlert('success', 'Kasir berhasil ditambahkan');
            } else {
                setAlert('error', 'Gagal menambahkan kasir');
            }
        }
        
        if ($_POST['action'] == 'update_kasir') {
            $kasir_id = (int)$_POST['kasir_id'];
            $role = $_POST['role'];
            $status = $_POST['status'];
            
            if ($status == 'delete') {
                $stmt = $conn->prepare("DELETE FROM store_users WHERE id = ? AND store_id = ?");
                $stmt->bind_param("ii", $kasir_id, $store_id);
            } else {
                $stmt = $conn->prepare("UPDATE store_users SET role = ? WHERE id = ? AND store_id = ?");
                $stmt->bind_param("sii", $role, $kasir_id, $store_id);
            }
            
            if ($stmt->execute()) {
                setAlert('success', 'Kasir berhasil diupdate');
            } else {
                setAlert('error', 'Gagal mengupdate kasir');
            }
        }
        
        if ($_POST['action'] == 'create_kasir') {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $nama_lengkap = trim($_POST['nama_lengkap']);
            $email = trim($_POST['email']);
            $no_hp = trim($_POST['no_hp']);
            $role = $_POST['role'];
            
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->bind_param("ss", $username, $email);
            $check->execute();
            
            if ($check->get_result()->num_rows > 0) {
                setAlert('error', 'Username atau email sudah terdaftar');
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $force2FA = 'yes';
                
                $stmt = $conn->prepare("
                    INSERT INTO users (username, password, nama_lengkap, email, no_hp, role, status, force_2fa)
                    VALUES (?, ?, ?, ?, ?, 'member', 'active', ?)
                ");
                $stmt->bind_param("ssssss", $username, $password_hash, $nama_lengkap, $email, $no_hp, $force2FA);
                
                if ($stmt->execute()) {
                    $new_user_id = $conn->insert_id;
                    
                    $stmtStoreUser = $conn->prepare("
                        INSERT INTO store_users (store_id, user_id, role)
                        VALUES (?, ?, ?)
                    ");
                    $stmtStoreUser->bind_param("iis", $store_id, $new_user_id, $role);
                    $stmtStoreUser->execute();
                    
                    setAlert('success', 'Kasir berhasil dibuat');
                } else {
                    setAlert('error', 'Gagal membuat kasir');
                }
            }
        }
        
        header("Location: kelola_kasir.php");
        exit;
    }
}

$kasirList = getStoreKasir($store_id);
$userList = getAvailableUsers($store_id);
$alert = getAlert();

$store = getUserStore($_SESSION['user_id']);
?>
<?php include 'layout.php'; ?>

<style>
.tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    border-bottom: 1px solid var(--border);
    padding-bottom: 12px;
}

.tab-btn {
    padding: 10px 20px;
    border: none;
    background: transparent;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s;
}

.tab-btn:hover {
    background: var(--primary-50);
    color: var(--primary);
}

.tab-btn.active {
    background: var(--primary);
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.card {
    background: white;
    border-radius: 12px;
    border: 1px solid var(--border);
    padding: 24px;
    margin-bottom: 20px;
}

.card-header {
    margin-bottom: 20px;
}

.card-header h3 {
    font-size: 16px;
    margin-bottom: 4px;
}

.card-header p {
    font-size: 13px;
    color: var(--text-secondary);
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
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
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

.role-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.role-pos {
    background: #dbeafe;
    color: #1e40af;
}

.role-ppob {
    background: #fce7f3;
    color: #9d174d;
}

.role-all {
    background: #dcfce7;
    color: #166534;
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
    animation: popIn 0.3s;
}

.modal-header {
    margin-bottom: 20px;
}

.modal-header h3 {
    font-size: 18px;
}

.btn-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: var(--text-muted);
    float: right;
}
</style>

<div class="tabs">
    <button class="tab-btn active" onclick="switchTab('settings')">Pengaturan Toko</button>
    <button class="tab-btn" onclick="switchTab('kasir')">Kelola Kasir</button>
</div>

<div id="settings" class="tab-content active">
    <div class="card">
        <div class="card-header">
            <h3>Informasi Toko</h3>
            <p>Pengaturan dasar tentang toko Anda</p>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="update_store">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nama Toko</label>
                    <input type="text" name="nama_toko" value="<?= htmlspecialchars($store['nama_toko'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Slug (URL)</label>
                    <input type="text" name="slug" value="<?= htmlspecialchars($store['slug'] ?? '') ?>" placeholder="nama-toko-anda">
                </div>
            </div>
            
            <div class="form-group">
                <label>Alamat</label>
                <textarea name="alamat" rows="2"><?= htmlspecialchars($store['alamat'] ?? '') ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>No. HP</label>
                    <input type="text" name="no_hp" value="<?= htmlspecialchars($store['no_hp'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($store['email'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <small style="color: var(--text-muted); font-size: 12px;">QRIS Generated secara dinamis per transaksi via API SPNPAY</small>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan Perubahan
            </button>
        </form>
    </div>
</div>

<div id="kasir" class="tab-content">
    <div style="margin-bottom: 20px;">
        <button class="btn btn-primary" onclick="openModal('modalAddKasir')">
            <i class="fas fa-plus"></i> Tambah Kasir
        </button>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>No. HP</th>
                    <th>Role</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while($kasir = $kasirList->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($kasir['nama_lengkap']) ?></td>
                    <td><?= htmlspecialchars($kasir['username']) ?></td>
                    <td><?= htmlspecialchars($kasir['email']) ?></td>
                    <td><?= htmlspecialchars($kasir['no_hp']) ?></td>
                    <td>
                        <span class="role-badge role-<?= str_replace('kasir_', '', $kasir['role']) ?>">
                            <?= str_replace('kasir_', 'Kasir ', $kasir['role']) ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline" onclick="editKasir(<?= htmlspecialchars(json_encode($kasir)) ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteKasir(<?= $kasir['id'] ?>, '<?= htmlspecialchars($kasir['nama_lengkap']) ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                
                <?php if($kasirList->num_rows == 0): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class="fas fa-users" style="font-size: 40px; opacity: 0.3; margin-bottom: 12px; display: block;"></i>
                        <p>Belum ada kasir</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal" id="modalAddKasir">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Tambah Kasir</h3>
            <button class="btn-close" onclick="closeModal('modalAddKasir')">&times;</button>
        </div>
        
        <form method="POST" id="formNew">
            <input type="hidden" name="action" value="create_kasir">
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama_lengkap" required>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label>No. HP</label>
                <input type="text" name="no_hp" required>
            </div>
            
            <div class="form-group">
                <label>Role</label>
                <select name="role" required>
                    <option value="kasir_pos">Kasir POS Saja</option>
                    <option value="kasir_ppob">Kasir PPOB Saja</option>
                    <option value="kasir_all">Kasir POS + PPOB</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-plus"></i> Buat Kasir
            </button>
        </form>
    </div>
</div>

<div class="modal" id="modalEditKasir">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Kasir</h3>
            <button class="btn-close" onclick="closeModal('modalEditKasir')">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="update_kasir">
            <input type="hidden" name="kasir_id" id="editKasirId">
            
            <div class="form-group">
                <label>Role</label>
                <select name="role" id="editKasirRole" required>
                    <option value="kasir_pos">Kasir POS Saja</option>
                    <option value="kasir_ppob">Kasir PPOB Saja</option>
                    <option value="kasir_all">Kasir POS + PPOB</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-save"></i> Simpan
            </button>
        </form>
        
        <hr style="margin: 20px 0; border: none; border-top: 1px solid var(--border);">
        
        <form method="POST" onsubmit="return confirm('Yakin hapus kasir ini?');">
            <input type="hidden" name="action" value="update_kasir">
            <input type="hidden" name="kasir_id" id="deleteKasirId">
            <input type="hidden" name="status" value="delete">
            <button type="submit" class="btn btn-danger" style="width: 100%;">
                <i class="fas fa-trash"></i> Hapus Kasir
            </button>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    
    document.getElementById(tab).classList.add('active');
    event.target.classList.add('active');
}

function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function editKasir(data) {
    document.getElementById('editKasirId').value = data.id;
    document.getElementById('editKasirRole').value = data.role;
    openModal('modalEditKasir');
}

function deleteKasir(id, nama) {
    if (confirm('Yakin hapus kasir "' + nama + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_kasir">
            <input type="hidden" name="kasir_id" value="${id}">
            <input type="hidden" name="status" value="delete">
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
