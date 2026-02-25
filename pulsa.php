<?php
require_once 'config.php';
cekLogin();
$conn = koneksi();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$_SESSION['saldo'] = getSaldo($user_id);

// Ambil produk pulsa
$produkPulsa = $conn->query("SELECT * FROM produk WHERE kategori_id = 1 AND status = 'active' ORDER BY provider, nominal");
$produkByProvider = [];
while ($row = $produkPulsa->fetch_assoc()) {
    $produkByProvider[$row['provider']][] = $row;
}

// Proses pembelian
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Rate limiting: max 10 purchases per minute
    if (!checkRateLimit('purchase_pulsa', 10, 60)) {
        setAlert('error', 'Terlalu banyak permintaan. Silakan tunggu sebentar.');
        header("Location: pulsa.php"); exit;
    }
    
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        setAlert('error', 'Sesi tidak valid. Silakan refresh halaman.');
        header("Location: pulsa.php"); exit;
    }
    $no_hp = preg_replace('/[^0-9]/', '', $_POST['no_hp'] ?? '');
    $produk_id = intval($_POST['produk_id'] ?? 0);

    if (empty($no_hp) || strlen($no_hp) < 10 || strlen($no_hp) > 15) {
        setAlert('error', 'Nomor HP tidak valid! (10-15 digit)');
    } elseif ($produk_id == 0 || $produk_id > 100000) {
        setAlert('error', 'Pilih nominal pulsa yang valid!');
    } else {
        $stmt = $conn->prepare("SELECT * FROM produk WHERE id = ?");
        $stmt->bind_param("i", $produk_id);
        $stmt->execute();
        $produk = $stmt->get_result()->fetch_assoc();

        if (!$produk) {
            setAlert('error', 'Produk tidak ditemukan!');
        } else {
            $saldo = getSaldo($user_id);
            $harga = $produk['harga_jual'];
            if ($saldo < $harga) {
                setAlert('error', 'Saldo tidak mencukupi! Silakan deposit terlebih dahulu.');
            } else {
                $invoice = generateInvoice();
                $saldo_sebelum = $saldo;
                $saldo_sesudah = $saldo - $harga;
                $stmt = $conn->prepare("INSERT INTO transaksi (user_id, produk_id, invoice_no, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan) VALUES (?, ?, ?, 'pulsa', ?, ?, ?, 0, ?, ?, ?, 'success', 'Pembelian pulsa berhasil')");
                $stmt->bind_param("iissddddd", $user_id, $produk_id, $invoice, $no_hp, $produk['nominal'], $harga, $harga, $saldo_sebelum, $saldo_sesudah);
                if ($stmt->execute()) {
                    updateSaldo($user_id, $harga, 'kurang');
                    $_SESSION['saldo'] = getSaldo($user_id);
                    setAlert('success', 'Pembelian pulsa berhasil! Invoice: ' . $invoice);
                } else {
                    setAlert('error', 'Terjadi kesalahan. Silakan coba lagi.');
                }
            }
        }
    }
    header("Location: pulsa.php"); exit;
}

// ═══ Set Layout Variables ═══
$pageTitle   = 'Isi Pulsa';
$pageIcon    = 'fas fa-mobile-alt';
$pageDesc    = 'Isi pulsa semua operator dengan harga terbaik';
$currentPage = 'pulsa';
$alert       = getAlert();

// Include layout header (sidebar + header + opens <main>)
include 'layout.php';
?>

<!-- ═══════════════════════════════════════════
     PULSA PAGE - Custom Styles
     ═══════════════════════════════════════════ -->
<style>
/* ── Page Grid ── */
.pulsa-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}
@media (min-width: 1024px) {
    .pulsa-grid { grid-template-columns: 1fr 380px; }
    .pulsa-form-col { order: 1; }
    .pulsa-summary-col { order: 2; position: sticky; top: 5rem; align-self: start; }
}

/* ── Step Indicator ── */
.steps { display: flex; align-items: center; margin-bottom: 1.5rem; }
.step { display: flex; align-items: center; gap: 0.5rem; font-size: 0.813rem; color: var(--text-muted); font-weight: 500; }
.step.active { color: var(--primary); }
.step.done { color: var(--success); }
.step-num {
    width: 1.75rem; height: 1.75rem; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700;
    border: 2px solid var(--border); background: white; color: var(--text-muted);
}
.step.active .step-num { border-color: var(--primary); background: var(--primary); color: white; }
.step.done .step-num { border-color: var(--success); background: var(--success); color: white; }
.step-line { width: 2rem; height: 2px; background: var(--border); margin: 0 0.5rem; }
.step-line.active { background: var(--primary); }
.step-line.done { background: var(--success); }
@media (max-width: 640px) { .step span { display: none; } .step-line { width: 1.5rem; } }

/* ── Phone Input ── */
.phone-wrap { position: relative; }
.phone-input {
    width: 100%; padding: 1rem 1rem 1rem 3.25rem;
    border: 2px solid var(--border); border-radius: 0.875rem;
    font-size: 1.125rem; font-weight: 600; letter-spacing: 0.03em;
    background: #f8fafc; transition: all 0.2s ease; color: var(--text);
}
.phone-input:focus {
    outline: none; border-color: var(--primary); background: white;
    box-shadow: 0 0 0 4px rgba(99,83,216,0.08);
}
.phone-input::placeholder { font-weight: 400; color: var(--text-muted); letter-spacing: 0; }
.phone-icon {
    position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: 1.1rem;
}
.phone-input.input-error { border-color: var(--error) !important; }
.phone-input.input-error:focus { box-shadow: 0 0 0 4px rgba(239,68,68,0.1) !important; }

/* ── Provider Badge ── */
.provider-badge {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.375rem 0.875rem; border-radius: 2rem;
    font-size: 0.8rem; font-weight: 600;
    animation: popIn 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
.badge-telkomsel { background: linear-gradient(135deg, #e2001a, #8b418b); color: white; }
.badge-indosat   { background: linear-gradient(135deg, #ff9600, #cc5500); color: white; }
.badge-xl        { background: linear-gradient(135deg, #009a0e, #00883b); color: white; }
.badge-tri       { background: linear-gradient(135deg, #ded142, #8b418b); color: white; }
.badge-smartfren { background: linear-gradient(135deg, #ffcc00, #6e8b00); color: #333; }
.badge-axis      { background: linear-gradient(135deg, #7c3aed, #6d28d9); color: white; }

/* ── Product Cards ── */
.product-card {
    cursor: pointer; transition: all 0.2s ease;
    border: 2px solid var(--border); border-radius: 1rem;
    padding: 1.25rem; background: white; position: relative; overflow: hidden;
}
.product-card:hover {
    border-color: var(--primary-light);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99,83,216,0.12);
}
.product-card.selected { border-color: var(--primary); background: var(--primary-50); }
.product-card .check-mark {
    position: absolute; top: 0.75rem; right: 0.75rem;
    width: 1.5rem; height: 1.5rem; background: var(--primary); border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 0.7rem;
    opacity: 0; transform: scale(0);
    transition: all 0.2s cubic-bezier(0.34,1.56,0.64,1);
}
.product-card.selected .check-mark { opacity: 1; transform: scale(1); }

/* ── Provider Section ── */
.provider-section { display: none; }
.provider-section.active { display: block; animation: fadeUp 0.35s ease; }

/* ── Nominal Placeholder ── */
.nominal-placeholder {
    text-align: center; padding: 2.5rem 1rem;
}
.nominal-placeholder .placeholder-icon {
    width: 64px; height: 64px; border-radius: 50%;
    background: var(--primary-50); color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.75rem; margin: 0 auto 1rem;
}

/* ── Summary Card ── */
.summary-card {
    border-radius: 1rem; overflow: hidden;
    border: 1px solid var(--border); background: white;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
}
.summary-head {
    background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
    padding: 1.25rem 1.5rem; color: white;
}
.summary-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.875rem 1.5rem; border-bottom: 1px solid #f1f5f9;
}
.summary-row:last-of-type { border-bottom: none; }
.summary-total {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.5rem; background: #f8fafc; border-top: 2px solid var(--border);
}

/* ── CTA Button ── */
.btn-cta {
    display: flex; align-items: center; justify-content: center; gap: 0.625rem;
    width: 100%; padding: 0.875rem; border-radius: 0.875rem;
    font-weight: 600; font-size: 0.95rem; color: white;
    background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
    border: none; cursor: pointer; transition: all 0.25s ease;
    box-shadow: 0 4px 14px rgba(99,83,216,0.3);
}
.btn-cta:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,83,216,0.4); }
.btn-cta:active { transform: scale(0.98); }

/* ── Promo Card ── */
.promo-box {
    background: linear-gradient(135deg, #fef9c3, #fde68a);
    border: 1px solid #fbbf24; border-radius: 1rem;
    padding: 1.25rem; position: relative; overflow: hidden;
}
.promo-box::after { content: '🎉'; position: absolute; top: -8px; right: 10px; font-size: 2rem; opacity: 0.3; }

/* ── Toast ── */
.toast-notif {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 1rem 1.25rem; border-radius: 0.875rem;
    animation: slideDown 0.4s ease;
}
.toast-notif.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.toast-notif.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
</style>

<!-- ═══════════════════════════════════════════
     PULSA PAGE - Content
     ═══════════════════════════════════════════ -->

<!-- Step Indicator -->
<div class="steps" id="stepIndicator">
    <div class="step active" id="step1">
        <div class="step-num">1</div><span>Nomor HP</span>
    </div>
    <div class="step-line" id="stepLine1"></div>
    <div class="step" id="step2">
        <div class="step-num">2</div><span>Pilih Pulsa</span>
    </div>
    <div class="step-line" id="stepLine2"></div>
    <div class="step" id="step3">
        <div class="step-num">3</div><span>Konfirmasi</span>
    </div>
</div>

<form method="POST" action="" id="formPulsa">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <input type="hidden" name="produk_id" id="produk_id" value="">

    <div class="pulsa-grid">

        <!-- ═══ LEFT: Form Column ═══ -->
        <div class="pulsa-form-col" style="display:flex;flex-direction:column;gap:1.25rem;">

            <!-- Card: Nomor HP -->
            <div class="card" style="padding:1.5rem;">
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
                    <i class="fas fa-phone" style="color:var(--primary);"></i>
                    <h3 style="font-weight:600;font-size:0.95rem;">Masukkan Nomor Handphone</h3>
                </div>

                <div class="phone-wrap">
                    <i class="fas fa-mobile-alt phone-icon"></i>
                    <input type="tel" name="no_hp" id="no_hp" class="phone-input"
                           placeholder="Contoh: 08123456789" maxlength="15" required
                           oninput="detectProvider(this.value)">
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:0.75rem;">
                    <div id="providerDisplay" class="hidden">
                        <span class="provider-badge" id="providerBadge">
                            <i class="fas fa-check-circle" style="font-size:0.7rem;"></i>
                            <span id="providerText"></span>
                        </span>
                    </div>
                    <p style="font-size:0.75rem;color:var(--text-muted);">
                        <i class="fas fa-info-circle" style="margin-right:4px;"></i>Pulsa dikirim 1-5 menit
                    </p>
                </div>
            </div>

            <!-- Card: Pilih Nominal -->
            <div class="card" style="padding:1.5rem;">
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
                    <i class="fas fa-tags" style="color:var(--primary);"></i>
                    <h3 style="font-weight:600;font-size:0.95rem;">Pilih Nominal</h3>
                </div>

                <!-- Placeholder (sebelum nomor diisi) -->
                <div id="nominalPlaceholder" class="nominal-placeholder">
                    <div class="placeholder-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <p style="font-weight:500;color:var(--text-secondary);">Masukkan nomor HP terlebih dahulu</p>
                    <p style="font-size:0.813rem;color:var(--text-muted);margin-top:0.25rem;">Pilih nominal akan muncul setelah nomor diisi</p>
                </div>

                <!-- Provider Sections (muncul setelah provider terdeteksi) -->
                <?php foreach ($produkByProvider as $provider => $produkList): ?>
                <div class="provider-section" data-provider="<?= strtolower($provider) ?>">
                    <p style="font-size:0.75rem;font-weight:500;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.75rem;"><?= $provider ?></p>
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.75rem;">
                        <?php foreach ($produkList as $p): ?>
                        <div class="product-card"
                             onclick="selectProduct(<?= $p['id'] ?>, '<?= rupiah($p['nominal']) ?> Pulsa', <?= $p['harga_jual'] ?>)">
                            <div class="check-mark"><i class="fas fa-check"></i></div>
                            <p style="font-size:1.125rem;font-weight:700;color:var(--text);margin-bottom:0.25rem;"><?= rupiah($p['nominal']) ?></p>
                            <div style="display:flex;align-items:center;justify-content:space-between;padding-top:0.5rem;border-top:1px solid var(--border);margin-top:0.5rem;">
                                <span style="font-size:0.813rem;font-weight:700;color:var(--primary);"><?= rupiah($p['harga_jual']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Promo (Mobile) -->
            <div class="promo-box" style="display:block;" id="promoMobile">
                <div style="display:flex;align-items:flex-start;gap:0.5rem;">
                    <i class="fas fa-gift" style="color:#d97706;margin-top:2px;"></i>
                    <div>
                        <p style="font-size:0.813rem;font-weight:600;color:#92400e;margin-bottom:0.25rem;">Promo Spesial!</p>
                        <ul style="font-size:0.75rem;color:#a16207;list-style:none;padding:0;">
                            <li>• Cashback 5% min. pembelian Rp 50.000</li>
                            <li>• Bonus SMS untuk pembelian Rp 100.000</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>

        <!-- ═══ RIGHT: Summary Column ═══ -->
        <div class="pulsa-summary-col" style="display:flex;flex-direction:column;gap:1rem;">

            <!-- Summary Card -->
            <div class="summary-card">
                <div class="summary-head">
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <i class="fas fa-receipt" style="opacity:0.8;"></i>
                        <h4 style="font-weight:600;">Ringkasan Pembelian</h4>
                    </div>
                </div>

                <div class="summary-row">
                    <span style="font-size:0.813rem;color:var(--text-secondary);">Nomor HP</span>
                    <span id="summaryNoHP" style="font-size:0.813rem;font-weight:600;font-family:monospace;">-</span>
                </div>
                <div class="summary-row">
                    <span style="font-size:0.813rem;color:var(--text-secondary);">Provider</span>
                    <span id="summaryProvider" style="font-size:0.813rem;font-weight:600;">-</span>
                </div>
                <div class="summary-row">
                    <span style="font-size:0.813rem;color:var(--text-secondary);">Produk</span>
                    <span id="summaryProduct" style="font-size:0.813rem;font-weight:600;text-align:right;">Belum dipilih</span>
                </div>
                <div class="summary-total">
                    <span style="font-weight:600;color:var(--text-secondary);">Total Bayar</span>
                    <span id="summaryTotal" style="font-size:1.25rem;font-weight:700;color:var(--primary);">Rp 0</span>
                </div>
                <div style="padding:1rem 1.5rem;">
                    <button type="submit" form="formPulsa" class="btn-cta">
                        <i class="fas fa-bolt"></i> Beli Pulsa Sekarang
                    </button>
                    <p style="font-size:0.75rem;color:var(--text-muted);text-align:center;margin-top:0.625rem;">
                        <i class="fas fa-lock" style="margin-right:4px;"></i>Dipotong dari saldo Anda
                    </p>
                </div>
            </div>

            <!-- Info Card (Desktop) -->
            <div class="card" style="padding:1rem;display:none;" id="infoDesktop">
                <div style="display:flex;align-items:flex-start;gap:0.75rem;">
                    <div style="width:2rem;height:2rem;border-radius:0.5rem;background:var(--primary-50);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-clock" style="color:var(--primary);font-size:0.875rem;"></i>
                    </div>
                    <div>
                        <p style="font-size:0.813rem;font-weight:500;color:var(--text);">Pengiriman Instan</p>
                        <p style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">Pulsa akan dikirimkan dalam 1-5 menit setelah pembayaran.</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</form>

<!-- ═══════════════════════════════════════════
     PULSA PAGE - JavaScript
     ═══════════════════════════════════════════ -->
<script>
// ── Provider Detection ──
function detectProvider(phoneNumber) {
    const prefix = phoneNumber.substring(0, 4);
    let provider = '', badgeClass = '';

    if (['0811','0812','0813','0821','0822','0823','0851','0852','0853'].some(p => prefix.startsWith(p)))
        { provider = 'Telkomsel'; badgeClass = 'badge-telkomsel'; }
    else if (['0814','0815','0816','0855','0856','0857','0858'].some(p => prefix.startsWith(p)))
        { provider = 'Indosat'; badgeClass = 'badge-indosat'; }
    else if (['0817','0818','0819','0859','0877','0878'].some(p => prefix.startsWith(p)))
        { provider = 'XL'; badgeClass = 'badge-xl'; }
    else if (['0895','0896','0897','0898','0899'].some(p => prefix.startsWith(p)))
        { provider = 'Tri'; badgeClass = 'badge-tri'; }
    else if (['0881','0882','0883','0884','0885','0886','0887','0888','0889'].some(p => prefix.startsWith(p)))
        { provider = 'Smartfren'; badgeClass = 'badge-smartfren'; }

    const providerDisplay = document.getElementById('providerDisplay');
    const providerBadge   = document.getElementById('providerBadge');
    const providerText    = document.getElementById('providerText');
    const summaryNoHP     = document.getElementById('summaryNoHP');
    const summaryProvider = document.getElementById('summaryProvider');

    if (provider) {
        providerDisplay.classList.remove('hidden');
        providerText.textContent = provider;
        providerBadge.className = 'provider-badge ' + badgeClass;
        summaryProvider.textContent = provider;
        summaryNoHP.textContent = phoneNumber;

        // Show matching provider section, hide placeholder
        document.getElementById('nominalPlaceholder').style.display = 'none';
        document.querySelectorAll('.provider-section').forEach(s => s.style.display = 'none');
        const section = document.querySelector(`.provider-section[data-provider="${provider.toLowerCase()}"]`);
        if (section) section.style.display = 'block';

        updateSteps(1);
    } else {
        providerDisplay.classList.add('hidden');
        summaryProvider.textContent = '-';
        summaryNoHP.textContent = '-';
        document.getElementById('nominalPlaceholder').style.display = 'block';
        document.querySelectorAll('.provider-section').forEach(s => s.style.display = 'none');
    }
}

// ── Format Rupiah ──
function formatRupiah(num) {
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// ── Select Product ──
function selectProduct(id, nama, harga) {
    document.querySelectorAll('.product-card').forEach(el => el.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    document.getElementById('produk_id').value = id;
    document.getElementById('summaryProduct').textContent = nama;
    document.getElementById('summaryTotal').textContent = formatRupiah(harga);
    updateSteps(2);
}

// ── Step Indicator ──
function updateSteps(completedUpTo) {
    const s1 = document.getElementById('step1');
    const s2 = document.getElementById('step2');
    const s3 = document.getElementById('step3');
    const l1 = document.getElementById('stepLine1');
    const l2 = document.getElementById('stepLine2');

    [s1,s2,s3].forEach(s => s.className = 'step');
    [l1,l2].forEach(l => l.className = 'step-line');

    if (completedUpTo >= 0) s1.classList.add('active');
    if (completedUpTo >= 1) { s1.className = 'step done'; l1.classList.add('done'); s2.classList.add('active'); }
    if (completedUpTo >= 2) { s2.className = 'step done'; l2.classList.add('done'); s3.classList.add('active'); }
}

// ── Auto-format phone ──
document.getElementById('no_hp').addEventListener('input', function(e) {
    let val = e.target.value.replace(/\D/g, '');
    if (val.length > 15) val = val.substring(0, 15);
    e.target.value = val;
});

// ── Remove error on input ──
document.querySelectorAll('input').forEach(input => {
    input.addEventListener('input', function() { this.classList.remove('input-error'); });
});

// ── Form Validation ──
document.getElementById('formPulsa').addEventListener('submit', function(e) {
    const noHP = document.getElementById('no_hp').value.trim();
    const produkId = document.getElementById('produk_id').value;
    let isValid = true, errorMsg = '', errorEl = null;

    if (!noHP || noHP.length < 10) {
        errorMsg = 'Nomor HP tidak valid (minimal 10 digit)';
        isValid = false;
        errorEl = document.getElementById('no_hp');
    } else if (!produkId || produkId == 0) {
        errorMsg = 'Silakan pilih nominal pulsa';
        isValid = false;
        errorEl = document.querySelector('.product-card');
    }

    if (!isValid) {
        e.preventDefault();
        if (errorEl) {
            errorEl.classList.add('input-error');
            errorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (errorEl.tagName === 'INPUT') errorEl.focus();
        }
        showToast(errorMsg, 'error');
    } else {
        const sp = document.getElementById('summaryProvider').textContent;
        const sprod = document.getElementById('summaryProduct').textContent;
        const stotal = document.getElementById('summaryTotal').textContent;
        if (!confirm(`Konfirmasi Pembelian Pulsa\n\nNomor HP: ${noHP}\nProvider: ${sp}\nProduk: ${sprod}\nTotal: ${stotal}\n\nLanjutkan?`)) {
            e.preventDefault();
        }
    }
});

// ── Toast ──
function showToast(message, type) {
    const existing = document.getElementById('toastNotif');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.id = 'toastNotif';
    toast.className = `toast-notif ${type}`;
    toast.style.cssText = 'position:fixed;top:80px;right:20px;z-index:9999;min-width:280px;max-width:400px;';
    toast.innerHTML = `<i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'} text-lg"></i><span style="font-weight:500;font-size:0.875rem;">${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// ── Hide nominal sections initially ──
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.provider-section').forEach(s => s.style.display = 'none');

    // Show desktop-only elements
    if (window.innerWidth >= 1024) {
        document.getElementById('infoDesktop').style.display = 'block';
        const promoMobile = document.getElementById('promoMobile');
        if (promoMobile) promoMobile.style.display = 'none';
    }
});
</script>

<?php
// Include layout footer (closes </main>, adds footer, sidebar JS, closes HTML)
include 'layout_footer.php';
$conn->close();
?>