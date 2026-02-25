<?php
$pageTitle = 'Token Listrik';
$pageIcon  = 'fas fa-bolt';
$pageDesc  = 'Token listrik PLN prabayar dengan harga terbaik';

require_once 'config.php';
cekLogin();
$conn = koneksi();
$user_id = $_SESSION['user_id'];
$_SESSION['saldo'] = getSaldo($user_id);

$alert = getAlert();

// Ambil produk token listrik
$produkListrik = $conn->query("SELECT * FROM produk WHERE kategori_id = 3 AND status = 'active' ORDER BY nominal");

// Ambil hasil token dari session (jika ada)
$tokenResult = isset($_SESSION['token_result']) ? $_SESSION['token_result'] : null;
unset($_SESSION['token_result']);

// Proses pembelian
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: listrik.php"); exit;
    }

    $no_meter = preg_replace('/[^0-9]/', '', $_POST['no_meter'] ?? '');
    $produk_id = intval($_POST['produk_id'] ?? 0);

    if (empty($no_meter) || strlen($no_meter) < 11 || strlen($no_meter) > 13) {
        setAlert('error', 'Nomor meter tidak valid! (11-13 digit)');
    } elseif ($produk_id == 0 || $produk_id > 100000) {
        setAlert('error', 'Pilih nominal token yang valid!');
    } else {
        $stmt = $conn->prepare("SELECT * FROM produk WHERE id = ?");
        $stmt->bind_param("i", $produk_id);
        $stmt->execute();
        $produk = $stmt->get_result()->fetch_assoc();

        if (!$produk) {
            setAlert('error', 'Produk tidak ditemukan!');
        } else {
            $harga = $produk['harga_jual'];
            $saldo = getSaldo($user_id);
            if ($saldo < $harga) {
                setAlert('error', 'Saldo tidak mencukupi! Silakan deposit terlebih dahulu.');
            } else {
                $invoice = generateInvoice();
                $saldo_sebelum = $saldo;
                $saldo_sesudah = $saldo - $harga;

                // Generate token (simulasi)
                $token = rand(1000,9999).'-'.rand(1000,9999).'-'.rand(1000,9999).'-'.rand(1000,9999).'-'.rand(1000,9999);
                $keterangan = "Token: " . $token;

                $stmt = $conn->prepare("INSERT INTO transaksi (user_id, produk_id, invoice_no, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan) VALUES (?, ?, ?, 'listrik', ?, ?, ?, 0, ?, ?, ?, 'success', ?)");
                $stmt->bind_param("iissdddds", $user_id, $produk_id, $invoice, $no_meter, $produk['nominal'], $harga, $harga, $saldo_sebelum, $saldo_sesudah, $keterangan);

                if ($stmt->execute()) {
                    updateSaldo($user_id, $harga, 'kurang');
                    $_SESSION['saldo'] = getSaldo($user_id);
                    $_SESSION['token_result'] = [
                        'invoice' => $invoice,
                        'token' => $token,
                        'nominal' => $produk['nominal'],
                        'harga' => $harga
                    ];
                    setAlert('success', 'Pembelian token listrik berhasil!');
                } else {
                    setAlert('error', 'Terjadi kesalahan. Silakan coba lagi.');
                }
            }
        }
    }
    header("Location: listrik.php"); exit;
}

$currentPage = 'listrik';
require_once 'layout.php';
?>

<!-- ═══════════════════════════════════════════
     LISTRIK PAGE - Custom Styles
     ═══════════════════════════════════════════ -->
<style>
/* ── Token Result Card ── */
.token-result-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 24px;
    animation: fadeUp 0.5s ease;
}
.token-result-header {
    text-align: center;
    padding: 24px 20px 16px;
}
.token-result-icon {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, #5fe9b0, #9d7706);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
    color: white; font-size: 24px;
    box-shadow: 0 4px 16px rgba(95,233,176,0.3);
}
.token-result-title {
    font-size: 18px; font-weight: 700; margin-bottom: 4px; color: var(--text);
}
.token-result-invoice {
    font-size: 13px; color: var(--text-secondary);
}

/* Token Display */
.token-display-wrapper {
    background: #f0fdf4;
    border: 1px solid #86efac;
    border-radius: 12px;
    text-align: center;
    padding: 20px;
    margin: 0 20px 16px;
}
.token-display-label {
    font-size: 11px; font-weight: 600; color: #15803d;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin-bottom: 8px;
}
.token-display-value {
    font-family: 'SF Mono', monospace;
    font-size: 22px; font-weight: 700;
    letter-spacing: 2px;
    color: var(--text);
    word-break: break-all;
}
.token-note {
    font-size: 12px; color: var(--text-secondary);
    margin-top: 8px;
    display: flex; align-items: center; justify-content: center; gap: 4px;
}

/* Token Info Grid */
.token-info-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 12px; padding: 0 20px 20px;
}
.token-info-item {
    background: var(--bg); border-radius: 10px; padding: 12px;
}
.token-info-label {
    font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;
}
.token-info-value {
    font-size: 15px; font-weight: 600; color: var(--text);
}

/* Token Actions */
.token-actions {
    display: flex; gap: 12px;
    padding: 16px 20px; border-top: 1px solid var(--border);
}
.btn-copy, .btn-print-token {
    flex: 1; display: flex; align-items: center; justify-content: center;
    gap: 8px; padding: 12px;
    border-radius: 10px; font-size: 14px; font-weight: 600;
    cursor: pointer; transition: all 0.2s ease; border: none;
}
.btn-copy {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    box-shadow: 0 2px 8px rgba(99,83,216,0.25);
}
.btn-copy:hover { box-shadow: 0 4px 16px rgba(99,83,216,0.35); transform: translateY(-1px); }
.btn-print-token {
    background: white; color: var(--text-secondary);
    border: 1px solid var(--border);
}
.btn-print-token:hover { background: var(--bg); color: var(--text); }

/* ── Meter Input Card ── */
.meter-card {
    background: white; border: 1px solid var(--border);
    border-radius: 12px; padding: 20px;
    margin-bottom: 24px;
}
.meter-card-header {
    display: flex; align-items: center; gap: 16px;
    margin-bottom: 16px;
}
.meter-card-icon {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, #fbbf24, #d97706);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 20px; flex-shrink: 0;
}
.meter-card-title { font-size: 16px; font-weight: 600; color: var(--text); }
.meter-card-desc { font-size: 13px; color: var(--text-secondary); }

.meter-input-group { position: relative; margin-bottom: 8px; }
.meter-input-icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: 16px; pointer-events: none;
    transition: color 0.2s;
}
.meter-input-group:focus-within .meter-input-icon { color: var(--primary); }
.meter-input {
    width: 100%; padding: 14px 14px 14px 42px;
    border: 2px solid var(--border); border-radius: 10px;
    font-size: 15px; color: var(--text); background: var(--bg);
    outline: none; transition: all 0.2s ease;
}
.meter-input:focus {
    border-color: var(--primary); background: white;
    box-shadow: 0 0 0 3px rgba(99,83,216,0.08);
}
.meter-input::placeholder { color: var(--text-muted); }
.meter-hint {
    font-size: 12px; color: var(--text-muted);
    display: flex; align-items: center; gap: 4px; margin-top: 6px;
}

/* ── Token Nominal Card ── */
.nominal-card {
    background: white; border: 1px solid var(--border);
    border-radius: 12px; padding: 20px;
    margin-bottom: 24px;
}
.nominal-card-title {
    font-size: 16px; font-weight: 600; margin-bottom: 16px;
    display: flex; align-items: center; gap: 8px; color: var(--text);
}
.nominal-card-title i { color: #d97706; }

.nominal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

/* ── Product Card (Token) ── */
.token-product-card {
    background: white;
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}
.token-product-card:hover {
    border-color: #d97706;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(217,119,6,0.1);
}
.token-product-card.selected {
    border-color: #d97706;
    background: #fffbeb;
    box-shadow: 0 4px 12px rgba(217,119,6,0.15);
}
.token-product-card .card-check {
    position: absolute; top: 8px; right: 8px;
    width: 20px; height: 20px;
    background: #d97706; border-radius: 50%;
    display: none; align-items: center; justify-content: center;
    color: white; font-size: 10px;
}
.token-product-card.selected .card-check { display: flex; }

.token-product-top {
    display: flex; align-items: flex-start; justify-content: space-between;
}
.token-product-nominal {
    font-size: 16px; font-weight: 700; color: var(--text);
}
.token-product-type {
    font-size: 11px; color: var(--text-secondary); margin-top: 4px;
    display: flex; align-items: center; gap: 4px;
}
.token-product-pricing {
    border-top: 1px solid var(--border);
    padding-top: 12px; margin-top: 12px;
}
.token-product-price {
    font-size: 16px; font-weight: 700; color: #d97706;
}
.token-product-admin {
    font-size: 11px; color: var(--text-muted); margin-top: 2px;
}

/* ── Placeholder ── */
.listrik-placeholder {
    background: white; border: 1px solid var(--border);
    border-radius: 12px; padding: 48px 20px;
    text-align: center; margin-bottom: 24px;
}
.placeholder-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: #fef3c7; color: #d97706;
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; margin: 0 auto 16px;
}
.placeholder-title { font-size: 16px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
.placeholder-text { font-size: 13px; color: var(--text-muted); }

/* ── Sticky Summary ── */
.listrik-summary {
    position: fixed; bottom: 20px;
    left: 50%; transform: translateX(-50%);
    width: calc(100% - 48px); max-width: 500px;
    background: white; border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: 0 -4px 24px rgba(0,0,0,0.1);
    padding: 16px;
    z-index: 100;
    display: none;
    animation: slideUp 0.35s cubic-bezier(0.4,0,0.2,1);
}
@keyframes slideUp {
    from { opacity: 0; transform: translateX(-50%) translateY(20px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
}
.summary-content {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 12px;
}
.summary-left {}
.summary-label { font-size: 13px; color: var(--text-secondary); margin-bottom: 4px; }
.summary-product-name { font-size: 14px; font-weight: 600; color: var(--text); }
.summary-right { text-align: right; }
.summary-price-label { font-size: 13px; color: var(--text-secondary); margin-bottom: 4px; }
.summary-price-value { font-size: 20px; font-weight: 700; color: #d97706; }

.summary-actions {
    display: flex; gap: 12px;
}
.btn-cancel {
    flex: 1; padding: 10px;
    border: 1px solid var(--border); border-radius: 10px;
    background: white; color: var(--text-secondary);
    font-size: 14px; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.btn-cancel:hover { background: #fee2e2; color: #dc2626; border-color: #fecaca; }
.btn-submit-token {
    flex: 1; padding: 10px;
    border: none; border-radius: 10px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white; font-size: 14px; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    box-shadow: 0 2px 8px rgba(217,119,6,0.3);
}
.btn-submit-token:hover {
    box-shadow: 0 4px 16px rgba(217,119,6,0.4);
    transform: translateY(-1px);
}

/* ── Toast ── */
.toast-msg {
    position: fixed; top: 80px; right: 20px; z-index: 9999;
    min-width: 280px; max-width: 400px;
    padding: 14px 16px; border-radius: 10px;
    display: flex; align-items: center; gap: 10px;
    font-size: 14px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    animation: toastIn 0.4s ease;
}
.toast-msg.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.toast-msg.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.toast-msg.info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
@keyframes toastIn {
    from { opacity: 0; transform: translateX(30px); }
    to { opacity: 1; transform: translateX(0); }
}

/* ── Responsive ── */
@media (max-width: 768px) {
    .listrik-summary {
        bottom: 0; left: 0; right: 0;
        transform: none; width: 100%;
        max-width: 100%; border-radius: 16px 16px 0 0;
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .token-info-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ═══════════════════════════════════════════
     LISTRIK PAGE - Content
     ═══════════════════════════════════════════ -->

<!-- Token Result (if purchase successful) -->
<?php if ($tokenResult): ?>
<div class="token-result-card">
    <div class="token-result-header">
        <div class="token-result-icon">
            <i class="fas fa-bolt"></i>
        </div>
        <h3 class="token-result-title">Token Listrik Berhasil Dibeli!</h3>
        <p class="token-result-invoice">Invoice: <?= $tokenResult['invoice'] ?></p>
    </div>

    <!-- Token Number -->
    <div class="token-display-wrapper">
        <p class="token-display-label">Token Listrik (20 Digit)</p>
        <div class="token-display-value" id="tokenValue"><?= $tokenResult['token'] ?? '' ?></div>
        <p class="token-note">
            <i class="fas fa-info-circle"></i>
            Token hanya muncul sekali, harap dicatat
        </p>
    </div>

    <!-- Token Info -->
    <div class="token-info-grid">
        <div class="token-info-item">
            <div class="token-info-label">Nominal Token</div>
            <div class="token-info-value"><?= rupiah($tokenResult['nominal'] ?? 0) ?></div>
        </div>
        <div class="token-info-item">
            <div class="token-info-label">Total Pembayaran</div>
            <div class="token-info-value"><?= rupiah($tokenResult['harga'] ?? 0) ?></div>
        </div>
    </div>

    <!-- Actions -->
    <div class="token-actions">
        <button class="btn-copy" onclick="copyToken('<?= $tokenResult['token'] ?? '' ?>')">
            <i class="fas fa-copy"></i> Salin Token
        </button>
        <button class="btn-print-token" onclick="printToken()">
            <i class="fas fa-print"></i> Cetak
        </button>
    </div>
</div>
<?php endif; ?>

<!-- Purchase Form -->
<form method="POST" action="" id="formListrik" onsubmit="return validateForm(event)">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <input type="hidden" name="produk_id" id="produk_id" value="">

    <!-- Meter Number Input -->
    <div class="meter-card">
        <div class="meter-card-header">
            <div class="meter-card-icon">
                <i class="fas fa-bolt"></i>
            </div>
            <div>
                <div class="meter-card-title">PLN Prabayar</div>
                <div class="meter-card-desc">Masukkan nomor meter pelanggan PLN prabayar</div>
            </div>
        </div>

        <label style="display:block;font-weight:500;margin-bottom:8px;">Nomor Meter / ID Pelanggan</label>
        <div class="meter-input-group">
            <div class="meter-input-icon">
                <i class="fas fa-hashtag"></i>
            </div>
            <input type="text" name="no_meter" id="no_meter"
                   class="meter-input"
                   placeholder="Contoh: 12345678901"
                   required maxlength="13" pattern="[0-9]*"
                   oninput="this.value = this.value.replace(/[^0-9]/g, ''); detectMeter();">
        </div>
        <p class="meter-hint">
            <i class="fas fa-info-circle"></i>
            Masukkan 11-13 digit nomor meter yang tertera pada token listrik sebelumnya
        </p>
    </div>

    <!-- Placeholder (shown initially) -->
    <div class="listrik-placeholder" id="tokenPlaceholder">
        <div class="placeholder-icon">
            <i class="fas fa-bolt"></i>
        </div>
        <p class="placeholder-title">Masukkan nomor meter terlebih dahulu</p>
        <p class="placeholder-text">Pilih nominal akan muncul setelah nomor diisi</p>
    </div>

    <!-- Token Nominal (shown after input) -->
    <div class="nominal-card" id="tokenSection" style="display:none;">
        <h3 class="nominal-card-title">
            <i class="fas fa-bolt"></i> Pilih Nominal Token Listrik
        </h3>
        <div class="nominal-grid">
            <?php
            $produkListrik->data_seek(0);
            while ($p = $produkListrik->fetch_assoc()):
                $harga = $p['harga_jual'];
                $nominal = $p['nominal'];
                $admin = 2500;
                $hargaTanpaAdmin = $harga - $admin;
            ?>
            <div class="token-product-card"
                 onclick="selectProduct(<?= $p['id'] ?>, 'Token <?= rupiah($nominal) ?>', <?= $harga ?>)">
                <div class="card-check"><i class="fas fa-check"></i></div>
                <div class="token-product-top">
                    <div>
                        <div class="token-product-nominal"><?= rupiah($nominal) ?></div>
                        <div class="token-product-type">
                            <i class="fas fa-bolt" style="color:#d97706;"></i> Token Listrik
                        </div>
                    </div>
                </div>
                <div class="token-product-pricing">
                    <div class="token-product-price"><?= rupiah($harga) ?></div>
                    <div class="token-product-admin">
                        Harga: <?= rupiah($hargaTanpaAdmin) ?> | Admin: <?= rupiah($admin) ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Sticky Summary -->
    <div class="listrik-summary" id="summarySection">
        <div class="summary-content">
            <div class="summary-left">
                <div class="summary-label">Token yang dipilih</div>
                <div class="summary-product-name" id="selectedProduct">-</div>
            </div>
            <div class="summary-right">
                <div class="summary-price-label">Total Pembayaran</div>
                <div class="summary-price-value" id="totalBayar">Rp 0</div>
            </div>
        </div>
        <div class="summary-actions">
            <button type="button" class="btn-cancel" onclick="resetSelection()">
                <i class="fas fa-times"></i> Batal
            </button>
            <button type="submit" class="btn-submit-token">
                <i class="fas fa-bolt"></i> Beli Token
            </button>
        </div>
    </div>
</form>

<!-- ═══════════════════════════════════════════
     LISTRIK PAGE - JavaScript
     ═══════════════════════════════════════════ -->
<script>
// ══════════ DETECT METER ══════════
function detectMeter() {
    const noMeter = document.getElementById('no_meter').value;
    const placeholder = document.getElementById('tokenPlaceholder');
    const section = document.getElementById('tokenSection');

    if (noMeter.length >= 11) {
        placeholder.style.display = 'none';
        section.style.display = 'block';
    } else {
        placeholder.style.display = 'block';
        section.style.display = 'none';
    }
}

// ══════════ SELECT PRODUCT ══════════
function selectProduct(id, nama, harga) {
    // Remove previous selections
    document.querySelectorAll('.token-product-card').forEach(el => {
        el.classList.remove('selected');
    });

    // Select current
    const card = event.currentTarget;
    card.classList.add('selected');

    // Update form
    document.getElementById('produk_id').value = id;
    document.getElementById('selectedProduct').textContent = nama;
    document.getElementById('totalBayar').textContent = formatRupiah(harga);

    // Show summary
    document.getElementById('summarySection').style.display = 'block';
}

function resetSelection() {
    document.querySelectorAll('.token-product-card').forEach(el => {
        el.classList.remove('selected');
    });
    document.getElementById('produk_id').value = '';
    document.getElementById('selectedProduct').textContent = '-';
    document.getElementById('totalBayar').textContent = 'Rp 0';
    document.getElementById('summarySection').style.display = 'none';
}

// ══════════ FORMAT ══════════
function formatRupiah(num) {
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// ══════════ TOAST ══════════
function showToast(message, type = 'info') {
    const existing = document.getElementById('toastNotif');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'toastNotif';
    toast.className = 'toast-msg ' + type;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i><span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// ══════════ FORM VALIDATION ══════════
function validateForm(e) {
    const noMeter = document.getElementById('no_meter').value;
    const produkId = document.getElementById('produk_id').value;
    const selectedProduct = document.getElementById('selectedProduct').textContent;
    const totalBayar = document.getElementById('totalBayar').textContent;

    if (!noMeter || noMeter.length < 11) {
        e.preventDefault();
        showToast('Masukkan nomor meter yang valid (11-13 digit)', 'error');
        return false;
    }

    if (!produkId) {
        e.preventDefault();
        showToast('Pilih nominal token terlebih dahulu', 'error');
        return false;
    }

    if (!confirm(`Konfirmasi Pembelian Token Listrik\n\nNo. Meter: ${noMeter}\nNominal: ${selectedProduct}\nTotal: ${totalBayar}\n\nLanjutkan?`)) {
        e.preventDefault();
        return false;
    }

    return true;
}

// ══════════ COPY TOKEN ══════════
function copyToken(token) {
    const cleanToken = token.replace(/-/g, '');
    navigator.clipboard.writeText(cleanToken);

    const button = event.target.closest('button');
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i> Token Disalin!';
    button.style.background = '#16a34a';

    setTimeout(() => {
        button.innerHTML = originalHTML;
        button.style.background = '';
    }, 2000);
}

// ══════════ PRINT TOKEN ══════════
function printToken() {
    const printContent = `
        <div style="padding:20px;font-family:Arial,sans-serif;">
            <h2 style="text-align:center;color:#5fe9b0;">TOKEN LISTRIK PLN</h2>
            <p style="text-align:center;color:#666;">Invoice: <?= $tokenResult['invoice'] ?? '' ?></p>
            <div style="background:#fff;padding:16px;border:2px dashed #5fe9b0;border-radius:8px;margin:20px 0;text-align:center;">
                <p style="color:#999;margin-bottom:8px;">Token Listrik (20 Digit)</p>
                <p style="font-size:24px;font-weight:bold;letter-spacing:2px;"><?= $tokenResult['token'] ?? '' ?></p>
            </div>
            <div style="display:flex;justify-content:space-between;margin:20px 0;">
                <div>
                    <p style="color:#666;">Nominal:</p>
                    <p style="font-weight:bold;"><?= rupiah($tokenResult['nominal'] ?? 0) ?></p>
                </div>
                <div>
                    <p style="color:#666;">Total Bayar:</p>
                    <p style="font-weight:bold;"><?= rupiah($tokenResult['harga'] ?? 0) ?></p>
                </div>
            </div>
            <p style="text-align:center;color:#999;font-size:12px;margin-top:30px;">
                Dicetak dari PPOB Express pada <?= date('d/m/Y H:i:s') ?>
            </p>
        </div>`;

    const printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Token Listrik</title></head><body>');
    printWindow.document.write(printContent);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php
require_once 'layout_footer.php';
$conn->close();
?>