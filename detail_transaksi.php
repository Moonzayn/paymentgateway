<?php
require_once 'config.php';
require_once 'digiflazz.php';
cekLogin();

$conn = koneksi();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$_SESSION['saldo'] = getSaldo($user_id);

// Get transaction ID
$transaksi_id = intval($_GET['id'] ?? 0);

if ($transaksi_id == 0) {
    setAlert('error', 'Transaksi tidak ditemukan.');
    header("Location: riwayat.php"); exit;
}

// Get transaction
$stmt = $conn->prepare("
    SELECT t.*, p.nama_produk, p.provider, p.kode_produk, p.nominal as produk_nominal,
           u.nama_lengkap, u.username
    FROM transaksi t
    LEFT JOIN produk p ON t.produk_id = p.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
");
$stmt->bind_param("i", $transaksi_id);
$stmt->execute();
$result = $stmt->get_result();
$trx = $result->fetch_assoc();

if (!$trx) {
    setAlert('error', 'Transaksi tidak ditemukan.');
    header("Location: riwayat.php"); exit;
}

// Check ownership (admin can view all)
if ($role != 'admin' && $trx['user_id'] != $user_id) {
    setAlert('error', 'Anda tidak memiliki akses ke transaksi ini.');
    header("Location: riwayat.php"); exit;
}

// Handle check status action
$checkResult = null;
if (isset($_POST['check_status']) && $_POST['csrf_token'] === ($_SESSION['csrf_token'] ?? '')) {
    // Only check for pulsa/pasca transactions with ref_id
    if (!empty($trx['ref_id']) && in_array($trx['jenis_transaksi'], ['pulsa', 'kuota'])) {
        $df = new DigiflazzAPI();
        $customerNo = formatPhoneForDigiflazz($trx['no_tujuan']);
        $kodeProduk = $trx['kode_produk'] ?? '';

        if (!empty($kodeProduk)) {
            $checkResult = $df->buyPulsa($kodeProduk, $customerNo, $trx['ref_id']);

            // Update status if changed
            if ($checkResult['status'] !== $trx['status']) {
                $newStatus = $checkResult['status'];
                $newKeterangan = $checkResult['message'];
                $newSn = $checkResult['sn'] ?? null;

                $stmt = $conn->prepare("UPDATE transaksi SET status = ?, keterangan = ?, server_id = ?, api_response = ?, updated_at = NOW() WHERE id = ?");
                $newApiResponse = json_encode($checkResult);
                $stmt->bind_param("ssssi", $newStatus, $newKeterangan, $newSn, $newApiResponse, $transaksi_id);
                $stmt->execute();

                // Refresh data
                $stmt = $conn->prepare("SELECT t.*, p.nama_produk, p.provider, p.kode_produk, p.nominal as produk_nominal, u.nama_lengkap FROM transaksi t LEFT JOIN produk p ON t.produk_id = p.id LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ?");
                $stmt->bind_param("i", $transaksi_id);
                $stmt->execute();
                $trx = $stmt->get_result()->fetch_assoc();

                setAlert('success', 'Status transaksi berhasil diperbarui.');
                header("Location: detail_transaksi.php?id=" . $transaksi_id); exit;
            }
        }
    }
}

// Layout variables
$pageTitle   = 'Detail Transaksi';
$pageIcon    = 'fas fa-receipt';
$pageDesc    = 'Detail transaksi #' . htmlspecialchars($trx['no_invoice']);
$currentPage = 'riwayat';
$alert = getAlert();

include 'layout.php';
?>

<style>
.detail-container {
    max-width: 800px;
    margin: 0 auto;
}
.detail-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.detail-header {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border);
}
.detail-header.success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
.detail-header.pending { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
.detail-header.failed { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
.detail-header.refund { background: linear-gradient(135deg, #6b7280, #4b5563); color: white; }
.detail-body { padding: 1.5rem; }
.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1rem 0;
    border-bottom: 1px solid #f1f5f9;
    gap: 1rem;
}
.detail-row:last-child { border-bottom: none; }
.detail-label { font-size: 0.813rem; color: var(--text-muted); font-weight: 500; min-width: 140px; }
.detail-value { font-size: 0.875rem; color: var(--text); font-weight: 600; text-align: right; flex: 1; }
.detail-value.mono { font-family: 'Courier New', monospace; }
.detail-section-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 1.5rem 0 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--border);
}
.sn-box {
    background: #f8fafc;
    border: 2px dashed #e2e8f0;
    border-radius: 0.75rem;
    padding: 1.25rem;
    text-align: center;
}
.sn-text { font-family: 'Courier New', monospace; font-size: 1.25rem; font-weight: 700; color: var(--primary); letter-spacing: 0.05em; word-break: break-all; }
.copy-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--primary);
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
    margin-top: 0.5rem;
    border-radius: 0.5rem;
    transition: all 0.2s ease;
}
.copy-btn:hover { background: var(--primary-50); }
.copy-feedback { font-size: 0.75rem; color: #10b981; margin-top: 0.5rem; opacity: 0; transition: opacity 0.3s; }
.copy-feedback.show { opacity: 1; }
.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 0.75rem;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    border: none;
}
.action-btn.primary { background: var(--primary); color: white; }
.action-btn.primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
.action-btn.secondary { background: #f1f5f9; color: #475569; }
.action-btn.secondary:hover { background: #e2e8f0; }
.action-btn.warning { background: #fef3c7; color: #92400e; border: 1px solid #fbbf24; }
.action-btn.warning:hover { background: #fde68a; }
.status-pending-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    background: #fef3c7;
    color: #92400e;
    padding: 0.375rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.8rem;
    font-weight: 600;
}
.pending-warning {
    background: #fef3c7;
    border: 1px solid #fbbf24;
    border-radius: 0.75rem;
    padding: 1rem;
    margin-top: 1rem;
}
.api-response-box {
    background: #1e293b;
    border-radius: 0.75rem;
    padding: 1rem;
    margin-top: 0.75rem;
    overflow-x: auto;
}
.api-response-box pre {
    color: #e2e8f0;
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    white-space: pre-wrap;
    word-break: break-all;
    margin: 0;
}
@media (max-width: 640px) {
    .detail-row { flex-direction: column; gap: 0.25rem; }
    .detail-value { text-align: left; }
}
</style>

<div class="detail-container">
    <!-- Back Button -->
    <a href="riwayat.php" class="action-btn secondary" style="margin-bottom:1rem;">
        <i class="fas fa-arrow-left"></i> Kembali ke Riwayat
    </a>

    <div class="detail-card">
        <!-- Header -->
        <div class="detail-header <?= $trx['status'] ?>">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <i class="fas fa-<?= $trx['status'] === 'success' ? 'check-circle' : ($trx['status'] === 'pending' ? 'clock' : ($trx['status'] === 'failed' ? 'times-circle' : 'undo')) ?>" style="font-size:2rem;"></i>
                <div>
                    <h2 style="font-size:1.125rem;font-weight:700;"><?= ucfirst($trx['status']) ?></h2>
                    <p style="font-size:0.813rem;opacity:0.9;"><?= htmlspecialchars($trx['no_invoice']) ?></p>
                </div>
            </div>
            <div style="text-align:right;">
                <p style="font-size:0.75rem;opacity:0.8;">Total Bayar</p>
                <p style="font-size:1.5rem;font-weight:700;"><?= rupiah($trx['total_bayar']) ?></p>
            </div>
        </div>

        <div class="detail-body">
            <!-- Primary Data Section -->
            <div class="detail-section-title">Data Transaksi</div>

            <div class="detail-row">
                <span class="detail-label">No. Invoice</span>
                <span class="detail-value mono"><?= htmlspecialchars($trx['no_invoice']) ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Ref ID (Digiflazz)</span>
                <span class="detail-value mono"><?= htmlspecialchars($trx['ref_id'] ?? '-') ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Tanggal & Waktu</span>
                <span class="detail-value"><?= date('d/m/Y H:i:s', strtotime($trx['created_at'])) ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Terakhir Diperbarui</span>
                <span class="detail-value"><?= date('d/m/Y H:i:s', strtotime($trx['updated_at'])) ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Jenis Transaksi</span>
                <span class="detail-value"><?= ucfirst($trx['jenis_transaksi']) ?></span>
            </div>

            <!-- Product Data Section -->
            <div class="detail-section-title">Data Produk</div>

            <div class="detail-row">
                <span class="detail-label">Produk</span>
                <span class="detail-value"><?= htmlspecialchars($trx['nama_produk'] ?? ucfirst($trx['jenis_transaksi'])) ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Provider</span>
                <span class="detail-value"><?= htmlspecialchars($trx['provider'] ?? '-') ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Kode Produk</span>
                <span class="detail-value mono"><?= htmlspecialchars($trx['kode_produk'] ?? '-') ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Nominal</span>
                <span class="detail-value" style="color:var(--primary);"><?= rupiah($trx['nominal']) ?></span>
            </div>

            <!-- Customer Data Section -->
            <div class="detail-section-title">Data Pelanggan</div>

            <div class="detail-row">
                <span class="detail-label">No. Tujuan</span>
                <span class="detail-value mono"><?= htmlspecialchars($trx['no_tujuan']) ?></span>
            </div>

            <?php if ($trx['customer_id']): ?>
            <div class="detail-row">
                <span class="detail-label">ID Pelanggan</span>
                <span class="detail-value mono"><?= htmlspecialchars($trx['customer_id']) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($role == 'admin'): ?>
            <!-- Admin Data Section -->
            <div class="detail-section-title">Data User (Admin)</div>

            <div class="detail-row">
                <span class="detail-label">User</span>
                <span class="detail-value"><?= htmlspecialchars($trx['nama_lengkap']) ?> (<?= htmlspecialchars($trx['username']) ?>)</span>
            </div>

            <div class="detail-row">
                <span class="detail-label">User ID</span>
                <span class="detail-value">#<?= $trx['user_id'] ?></span>
            </div>
            <?php endif; ?>

            <!-- Financial Data Section -->
            <div class="detail-section-title">Data Keuangan</div>

            <div class="detail-row">
                <span class="detail-label">Harga</span>
                <span class="detail-value"><?= rupiah($trx['harga']) ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Biaya Admin</span>
                <span class="detail-value"><?= rupiah($trx['biaya_admin']) ?></span>
            </div>

            <div class="detail-row" style="background:#f8fafc;margin:0.5rem -1.5rem;padding:1rem 1.5rem;border-radius:0.5rem;">
                <span class="detail-label" style="font-weight:700;">Total Bayar</span>
                <span class="detail-value" style="font-size:1.125rem;color:var(--primary);"><?= rupiah($trx['total_bayar']) ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Saldo Sebelum</span>
                <span class="detail-value"><?= rupiah($trx['saldo_sebelum']) ?></span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Saldo Sesudah</span>
                <span class="detail-value"><?= rupiah($trx['saldo_sesudah']) ?></span>
            </div>

            <!-- SN Section -->
            <?php if (!empty($trx['server_id'])): ?>
            <div class="detail-section-title">Serial Number (SN)</div>
            <div class="sn-box">
                <p class="sn-text" id="snText"><?= htmlspecialchars($trx['server_id']) ?></p>
                <button class="copy-btn" onclick="copySN()">
                    <i class="fas fa-copy"></i> Salin SN
                </button>
                <p class="copy-feedback" id="copyFeedback">SN berhasil disalin!</p>
            </div>
            <?php endif; ?>

            <!-- Status & Message -->
            <div class="detail-section-title">Status</div>

            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span id="statusBadge">
                    <?php if ($trx['status'] === 'success'): ?>
                        <span class="badge status-success"><i class="fas fa-check-circle"></i> SUKSES</span>
                    <?php elseif ($trx['status'] === 'pending'): ?>
                        <span class="status-pending-badge"><i class="fas fa-clock"></i> MENUNGGU</span>
                    <?php elseif ($trx['status'] === 'failed'): ?>
                        <span class="badge status-failed"><i class="fas fa-times-circle"></i> GAGAL</span>
                    <?php else: ?>
                        <span class="badge" style="background:#f1f5f9;color:#64748b;"><i class="fas fa-undo"></i> <?= ucfirst($trx['status']) ?></span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Keterangan</span>
                <span class="detail-value" style="text-align:left;flex:unset;"><?= htmlspecialchars($trx['keterangan']) ?></span>
            </div>

            <!-- Pending Warning -->
            <?php if ($trx['status'] === 'pending'): ?>
            <div class="pending-warning">
                <div style="display:flex;align-items:flex-start;gap:0.75rem;">
                    <i class="fas fa-exclamation-triangle" style="color:#d97706;margin-top:2px;font-size:1.25rem;"></i>
                    <div>
                        <p style="font-size:0.875rem;font-weight:600;color:#92400e;margin-bottom:0.25rem;">Transaksi Pending</p>
                        <p style="font-size:0.813rem;color:#92400e;line-height:1.5;">
                            Pulsa/token akan dikirim dalam 1x24 jam. Jika setelah <strong>H+1</strong> belum masuk,
                            silakan hubungi admin dengan membawa <strong>No. Invoice: <?= htmlspecialchars($trx['no_invoice']) ?></strong>.
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- API Response (Admin Only) -->
            <?php if ($role == 'admin' && !empty($trx['api_response'])): ?>
            <div class="detail-section-title">API Response (Debug)</div>
            <div class="api-response-box">
                <pre><?= htmlspecialchars(json_encode(json_decode($trx['api_response']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: $trx['api_response']) ?></pre>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div style="display:flex;flex-wrap:wrap;gap:0.75rem;margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border);">
                <?php if (in_array($trx['jenis_transaksi'], ['pulsa', 'kuota']) && !empty($trx['ref_id'])): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <button type="submit" name="check_status" value="1" class="action-btn warning">
                        <i class="fas fa-sync-alt"></i> Cek Status ke Digiflazz
                    </button>
                </form>
                <?php endif; ?>

                <a href="riwayat.php" class="action-btn secondary">
                    <i class="fas fa-list"></i> Lihat Riwayat
                </a>
            </div>
        </div>
    </div>
</div>

<script>
let autoCheckInterval = null;

function copySN() {
    const sn = document.getElementById('snText').textContent;
    navigator.clipboard.writeText(sn).then(() => {
        const feedback = document.getElementById('copyFeedback');
        feedback.classList.add('show');
        setTimeout(() => feedback.classList.remove('show'), 2000);
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = sn;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        const feedback = document.getElementById('copyFeedback');
        feedback.classList.add('show');
        setTimeout(() => feedback.classList.remove('show'), 2000);
    });
}

function checkStatusAuto() {
    const transaksiId = <?= $trx['id'] ?>;
    const refId = '<?= htmlspecialchars($trx['ref_id'] ?? '') ?>';
    const kodeProduk = '<?= htmlspecialchars($trx['kode_produk'] ?? '') ?>';

    if (!refId || !kodeProduk) return;

    const statusBadge = document.getElementById('statusBadge');
    if (statusBadge) {
        statusBadge.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Cek...';
    }

    fetch(`api_check_status.php?ids=${transaksiId}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.results && data.results.length > 0) {
            const result = data.results[0];
            if (result.status !== '<?= $trx['status'] ?>') {
                // Status changed - reload page
                showToast('Status transaksi berubah menjadi: ' + result.status.toUpperCase(), result.status === 'success' ? 'success' : 'info');
                setTimeout(() => window.location.reload(), 1500);
            } else if (result.is_suspect) {
                // Still pending but suspect
                if (statusBadge) {
                    statusBadge.innerHTML = '<span class="status-pending-badge"><i class="fas fa-exclamation-triangle"></i> SUSPECT (H+1)</span>';
                }
            }
        }

        // Reset badge
        if (statusBadge && statusBadge.textContent.includes('Cek...')) {
            <?php if ($trx['status'] === 'pending'): ?>
            statusBadge.innerHTML = '<span class="status-pending-badge"><i class="fas fa-clock"></i> MENUNGGU</span>';
            <?php elseif ($trx['status'] === 'success'): ?>
            statusBadge.innerHTML = '<span class="badge status-success"><i class="fas fa-check-circle"></i> SUKSES</span>';
            <?php else: ?>
            statusBadge.innerHTML = '<span class="badge status-failed"><i class="fas fa-times-circle"></i> GAGAL</span>';
            <?php endif; ?>
        }
    })
    .catch(err => {
        console.error('Auto check error:', err);
        if (statusBadge && statusBadge.textContent.includes('Cek...')) {
            <?php if ($trx['status'] === 'pending'): ?>
            statusBadge.innerHTML = '<span class="status-pending-badge"><i class="fas fa-clock"></i> MENUNGGU</span>';
            <?php endif; ?>
        }
    });
}

// Auto-refresh pending transactions every 30 seconds
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($trx['status'] === 'pending' && !empty($trx['ref_id'])): ?>
    autoCheckInterval = setInterval(checkStatusAuto, 30000);
    window.addEventListener('beforeunload', () => {
        if (autoCheckInterval) clearInterval(autoCheckInterval);
    });
    <?php endif; ?>
});
</script>

<?php include 'layout_footer.php'; $conn->close(); ?>
