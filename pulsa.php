<?php
require_once 'config.php';
require_once 'digiflazz.php';
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
                // Generate unique ref_id for Digiflazz
                $ref_id = generateDigiflazzRefId('PLS');
                $invoice = generateInvoice();
                $saldo_sebelum = $saldo;
                $saldo_sesudah = $saldo - $harga;

                // Format nomor HP: normalisasi ke 08xx
                $customerNo = formatPhoneForDigiflazz($no_hp);
                $no_tujuan = formatPhoneForDigiflazz($no_hp);

                // Initialize Digiflazz API
                $df = new DigiflazzAPI();

                // Insert transaksi with 'pending' status first
                $stmt = $conn->prepare("INSERT INTO transaksi (user_id, produk_id, no_invoice, ref_id, jenis_transaksi, no_tujuan, nominal, harga, biaya_admin, total_bayar, saldo_sebelum, saldo_sesudah, status, keterangan, api_response) VALUES (?, ?, ?, ?, 'pulsa', ?, ?, ?, 0, ?, ?, ?, 'pending', 'Menunggu response Digiflazz', NULL)");
                $stmt->bind_param("iisssddddd", $user_id, $produk_id, $invoice, $ref_id, $no_tujuan, $produk['nominal'], $harga, $harga, $saldo_sebelum, $saldo_sesudah);
                $stmt->execute();
                $transaksi_id = $conn->insert_id;

                // Kurangi saldo terlebih dahulu (optimistic lock)
                updateSaldo($user_id, $harga, 'kurang');

                // Call Digiflazz API
                $apiResult = $df->buyPulsa($produk['kode_produk'], $customerNo, $ref_id);

                // Determine final status
                $finalStatus = $apiResult['status'];
                $keterangan  = $apiResult['message'];
                $sn          = $apiResult['sn'] ?? null;
                $apiResponseJson = json_encode($apiResult);

                // Update transaksi dengan hasil API
                $stmt = $conn->prepare("UPDATE transaksi SET status = ?, keterangan = ?, server_id = ?, api_response = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("ssssi", $finalStatus, $keterangan, $sn, $apiResponseJson, $transaksi_id);
                $stmt->execute();

                // Handle berdasarkan hasil API
                if ($finalStatus === 'success') {
                    // Transaksi sukses - pulsa terkirim
                    $_SESSION['saldo'] = getSaldo($user_id);
                    $_SESSION['last_transaction'] = [
                        'id' => $transaksi_id,
                        'invoice' => $invoice,
                        'status' => 'success',
                        'jenis' => 'pulsa',
                        'produk' => $produk['nama_produk'],
                        'nominal' => $produk['nominal'],
                        'harga' => $harga,
                        'no_tujuan' => $no_tujuan,
                        'provider' => $produk['provider'],
                        'sn' => $sn,
                        'keterangan' => $keterangan,
                        'rc' => $apiResult['rc'],
                        'tanggal' => date('Y-m-d H:i:s'),
                        'ref_id' => $ref_id,
                    ];

                } elseif ($finalStatus === 'pending') {
                    // Transaksi pending - pulsa masih diproses
                    $_SESSION['saldo'] = getSaldo($user_id);
                    $_SESSION['last_transaction'] = [
                        'id' => $transaksi_id,
                        'invoice' => $invoice,
                        'status' => 'pending',
                        'jenis' => 'pulsa',
                        'produk' => $produk['nama_produk'],
                        'nominal' => $produk['nominal'],
                        'harga' => $harga,
                        'no_tujuan' => $no_tujuan,
                        'provider' => $produk['provider'],
                        'sn' => $sn,
                        'keterangan' => $keterangan,
                        'rc' => $apiResult['rc'],
                        'tanggal' => date('Y-m-d H:i:s'),
                        'ref_id' => $ref_id,
                    ];

                } else {
                    // Transaksi gagal - rollback saldo
                    if ($apiResult['should_rollback']) {
                        // Rollback saldo (tambahkan kembali)
                        updateSaldo($user_id, $harga, 'tambah');
                        $keterangan = "[ROLLBACK] " . $keterangan;
                    } else {
                        // Saldo tetap terpotong (gagal di sisi biller)
                        $keterangan = "[GAGAL] " . $keterangan;
                    }

                    // Update keterangan dengan info rollback
                    $stmt = $conn->prepare("UPDATE transaksi SET keterangan = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("si", $keterangan, $transaksi_id);
                    $stmt->execute();

                    $_SESSION['saldo'] = getSaldo($user_id);
                    $_SESSION['last_transaction'] = [
                        'id' => $transaksi_id,
                        'invoice' => $invoice,
                        'status' => 'failed',
                        'jenis' => 'pulsa',
                        'produk' => $produk['nama_produk'],
                        'nominal' => $produk['nominal'],
                        'harga' => $harga,
                        'no_tujuan' => $no_tujuan,
                        'provider' => $produk['provider'],
                        'sn' => $sn,
                        'keterangan' => $keterangan,
                        'rc' => $apiResult['rc'],
                        'tanggal' => date('Y-m-d H:i:s'),
                        'ref_id' => $ref_id,
                    ];
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

/* ── Invoice Modal ── */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    z-index: 10000;
    justify-content: center;
    align-items: center;
    padding: 1rem;
}
.modal-overlay.active {
    display: flex;
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
.invoice-modal {
    background: white;
    border-radius: 1.25rem;
    max-width: 520px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px rgba(0,0,0,0.25);
    animation: slideUp 0.4s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.invoice-header {
    padding: 1.5rem;
    text-align: center;
    border-bottom: 1px solid #f1f5f9;
}
.invoice-header.success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
.invoice-header.pending { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
.invoice-header.failed { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
.invoice-body { padding: 1.5rem; }
.invoice-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f5f9;
    gap: 1rem;
}
.invoice-row:last-child { border-bottom: none; }
.invoice-label { font-size: 0.8rem; color: #64748b; font-weight: 500; }
.invoice-value { font-size: 0.875rem; color: #1e293b; font-weight: 600; text-align: right; word-break: break-all; }
.invoice-value.mono { font-family: 'Courier New', monospace; }
.invoice-footer {
    padding: 1rem 1.5rem 1.5rem;
    display: flex;
    gap: 0.75rem;
}
.invoice-btn {
    flex: 1;
    padding: 0.875rem;
    border-radius: 0.75rem;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border: none;
}
.invoice-btn.primary { background: var(--primary); color: white; }
.invoice-btn.primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
.invoice-btn.secondary { background: #f1f5f9; color: #475569; }
.invoice-btn.secondary:hover { background: #e2e8f0; }
.invoice-btn i { font-size: 0.9rem; }
.sn-display {
    background: #f8fafc;
    border: 2px dashed #e2e8f0;
    border-radius: 0.75rem;
    padding: 1rem;
    text-align: center;
    margin-top: 0.5rem;
}
.sn-text { font-family: 'Courier New', monospace; font-size: 1.125rem; font-weight: 700; color: var(--primary); letter-spacing: 0.05em; }
.copy-feedback {
    font-size: 0.75rem;
    color: #10b981;
    margin-top: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.copy-feedback.show { opacity: 1; }
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

/* ── Loading Overlay ── */
#purchaseLoading {
    display: none;
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(4px);
    z-index: 15000;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    gap: 1.25rem;
}
.loading-spinner {
    width: 64px; height: 64px;
    border: 5px solid rgba(255,255,255,0.2);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.9s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.loading-text {
    color: white;
    font-size: 1rem;
    font-weight: 600;
    text-align: center;
}
.loading-subtext {
    color: rgba(255,255,255,0.7);
    font-size: 0.813rem;
    text-align: center;
}
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

<!-- Loading Overlay -->
<div id="purchaseLoading">
    <div class="loading-spinner"></div>
    <div class="loading-text">Memproses Pembelian...</div>
    <div class="loading-subtext">Menghubungi server Digiflazz</div>
</div>

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

// ── Form Validation & AJAX Submit ──
document.getElementById('formPulsa').addEventListener('submit', async function(e) {
    e.preventDefault();

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
        if (errorEl) {
            errorEl.classList.add('input-error');
            errorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (errorEl.tagName === 'INPUT') errorEl.focus();
        }
        showToast(errorMsg, 'error');
        return;
    }

    // Show loading overlay
    const loadingEl = document.getElementById('purchaseLoading');
    loadingEl.style.display = 'flex';

    try {
        const formData = new FormData(this);

        const response = await fetch('api_beli_pulsa.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        // Hide loading
        loadingEl.style.display = 'none';

        if (data.success) {
            // Update saldo in header
            const saldoEl = document.getElementById('saldoAmount');
            if (saldoEl && data.saldo !== undefined) {
                saldoEl.textContent = 'Rp ' + Number(data.saldo).toLocaleString('id-ID');
            }

            // Show invoice modal
            showInvoiceModal(data.data);
        } else {
            showToast(data.message || 'Terjadi kesalahan', 'error');
        }
    } catch (err) {
        loadingEl.style.display = 'none';
        showToast('Gagal terhubung ke server. Silakan coba lagi.', 'error');
        console.error('Purchase error:', err);
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

<!-- html2canvas for invoice download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<!-- ═══════════════════════════════════════════
     INVOICE MODAL - Transaction Result
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="invoiceModal">
    <div class="invoice-modal">
        <!-- Header -->
        <div class="invoice-header" id="invoiceHeader">
            <div style="font-size:3rem;margin-bottom:0.5rem;" id="invoiceIcon">
                <i class="fas fa-check-circle" id="iconSuccess" style="display:none;"></i>
                <i class="fas fa-clock" id="iconPending" style="display:none;"></i>
                <i class="fas fa-times-circle" id="iconFailed" style="display:none;"></i>
            </div>
            <h2 style="font-size:1.25rem;font-weight:700;margin-bottom:0.25rem;" id="invoiceTitle">Transaksi Sukses</h2>
            <p style="font-size:0.875rem;opacity:0.9;" id="invoiceSubtitle">Pembelian pulsa berhasil</p>
        </div>

        <!-- Body -->
        <div class="invoice-body" id="invoiceContentCapture">
            <!-- Invoice Number -->
            <div class="invoice-row">
                <span class="invoice-label">No. Invoice</span>
                <span class="invoice-value mono" id="invInvoice">-</span>
            </div>

            <!-- Date -->
            <div class="invoice-row">
                <span class="invoice-label">Tanggal & Waktu</span>
                <span class="invoice-value" id="invDate">-</span>
            </div>

            <!-- Service Type -->
            <div class="invoice-row">
                <span class="invoice-label">Jenis Layanan</span>
                <span class="invoice-value" id="invJenis">-</span>
            </div>

            <!-- Provider -->
            <div class="invoice-row">
                <span class="invoice-label">Provider</span>
                <span class="invoice-value" id="invProvider">-</span>
            </div>

            <!-- Product -->
            <div class="invoice-row">
                <span class="invoice-label">Produk</span>
                <span class="invoice-value" id="invProduk">-</span>
            </div>

            <!-- Nominal -->
            <div class="invoice-row">
                <span class="invoice-label">Nominal</span>
                <span class="invoice-value" style="color:var(--primary);" id="invNominal">-</span>
            </div>

            <!-- Destination Number -->
            <div class="invoice-row">
                <span class="invoice-label">No. Tujuan</span>
                <span class="invoice-value mono" id="invNoTujuan">-</span>
            </div>

            <!-- Price / Total -->
            <div class="invoice-row" style="background:#f8fafc;margin:0.75rem -1.5rem;padding:1rem 1.5rem;border-radius:0.75rem;">
                <span class="invoice-label" style="font-weight:700;font-size:0.9rem;">Total Bayar</span>
                <span class="invoice-value" style="font-size:1.25rem;color:var(--primary);" id="invTotal">-</span>
            </div>

            <!-- SN (if available) -->
            <div id="snSection" style="display:none;">
                <div class="invoice-row">
                    <span class="invoice-label">Serial Number (SN)</span>
                    <span class="invoice-value" style="display:flex;align-items:center;gap:0.5rem;">
                        <span id="invSN" class="mono">-</span>
                        <button onclick="copyToClipboard(document.getElementById('invSN').textContent)" style="background:none;border:none;cursor:pointer;color:var(--primary);padding:0.25rem;" title="Copy SN">
                            <i class="fas fa-copy"></i>
                        </button>
                    </span>
                </div>
            </div>

            <!-- RC Code (for debugging) -->
            <div class="invoice-row" style="display:none;" id="rcSection">
                <span class="invoice-label">Kode Response</span>
                <span class="invoice-value mono" style="color:#ef4444;" id="invRC">-</span>
            </div>

            <!-- Status Message -->
            <div class="invoice-row">
                <span class="invoice-label">Status</span>
                <span id="invStatus">-</span>
            </div>

            <!-- Pending Warning -->
            <div id="pendingWarning" style="display:none;background:#fef3c7;border:1px solid #fbbf24;border-radius:0.75rem;padding:1rem;margin-top:0.75rem;">
                <div style="display:flex;align-items:flex-start;gap:0.75rem;">
                    <i class="fas fa-exclamation-triangle" style="color:#d97706;margin-top:2px;"></i>
                    <div>
                        <p style="font-size:0.813rem;font-weight:600;color:#92400e;margin-bottom:0.25rem;">Transaksi Pending</p>
                        <p style="font-size:0.75rem;color:#92400e;">Pulsa akan dikirim dalam 1x24 jam. Jika setelah H+1 belum masuk, silakan hubungi admin.</p>
                    </div>
                </div>
            </div>

            <!-- Download Image Button -->
            <button onclick="downloadInvoiceImage()" class="invoice-btn secondary" style="width:100%;margin-top:1rem;">
                <i class="fas fa-image"></i> Download Invoice (PNG)
            </button>
        </div>

        <!-- Footer -->
        <div class="invoice-footer">
            <a href="riwayat.php" class="invoice-btn secondary" id="btnRiwayat">
                <i class="fas fa-list"></i> Lihat Riwayat
            </a>
            <button onclick="closeModal()" class="invoice-btn primary" id="btnClose">
                <i class="fas fa-check"></i> Tutup
            </button>
        </div>
    </div>
</div>

<script>
// ── Invoice Modal Logic ──
function formatRupiahModal(num) {
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function formatDateDMY(dateStr) {
    const d = new Date(dateStr);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const seconds = String(d.getSeconds()).padStart(2, '0');
    return `${day}/${month}/${year} ${hours}:${minutes}:${seconds}`;
}

// ── Download Invoice as PNG ──
function downloadInvoiceImage() {
    const el = document.getElementById('invoiceContentCapture');
    const invoiceNo = document.getElementById('invInvoice').textContent || 'Invoice';

    html2canvas(el, {
        backgroundColor: '#ffffff',
        scale: 2,
        useCORS: true,
        logging: false
    }).then(canvas => {
        const link = document.createElement('a');
        link.download = 'Invoice_' + invoiceNo + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        showToast('Invoice berhasil diunduh!', 'success');
    }).catch(err => {
        showToast('Gagal mengunduh invoice', 'error');
        console.error(err);
    });
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Berhasil disalin!', 'success');
    }).catch(() => {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showToast('Berhasil disalin!', 'success');
    });
}

function copyInvoice() {
    const data = <?= isset($_SESSION['last_transaction']) ? json_encode($_SESSION['last_transaction']) : 'null' ?>;
    if (!data) return;

    const text = `INVOICE: ${data.invoice}
Tanggal: ${formatDateDMY(data.tanggal)}
Jenis: ${data.jenis.toUpperCase()}
Provider: ${data.provider || '-'}
Produk: ${data.produk}
Nominal: Rp ${parseInt(data.nominal).toLocaleString('id-ID')}
No. Tujuan: ${data.no_tujuan}
Total Bayar: Rp ${parseInt(data.harga).toLocaleString('id-ID')}
Status: ${data.status.toUpperCase()}
${data.sn ? 'SN: ' + data.sn : ''}
${data.rc ? 'RC: ' + data.rc : ''}
Ref ID: ${data.ref_id}`;

    copyToClipboard(text);
}

function closeModal() {
    document.getElementById('invoiceModal').classList.remove('active');
}

function showInvoiceModal(data) {
    const modal = document.getElementById('invoiceModal');
    const header = document.getElementById('invoiceHeader');
    const title = document.getElementById('invoiceTitle');
    const subtitle = document.getElementById('invoiceSubtitle');

    // Reset classes
    header.className = 'invoice-header';
    document.getElementById('iconSuccess').style.display = 'none';
    document.getElementById('iconPending').style.display = 'none';
    document.getElementById('iconFailed').style.display = 'none';
    document.getElementById('pendingWarning').style.display = 'none';
    document.getElementById('snSection').style.display = 'none';
    document.getElementById('rcSection').style.display = 'none';

    // Set values
    document.getElementById('invInvoice').textContent = data.invoice;
    document.getElementById('invDate').textContent = formatDateDMY(data.tanggal);
    document.getElementById('invJenis').textContent = data.jenis.toUpperCase();
    document.getElementById('invProvider').textContent = data.provider || '-';
    document.getElementById('invProduk').textContent = data.produk;
    document.getElementById('invNominal').textContent = formatRupiahModal(parseInt(data.nominal));
    document.getElementById('invNoTujuan').textContent = data.no_tujuan;
    document.getElementById('invTotal').textContent = formatRupiahModal(parseInt(data.harga));

    if (data.status === 'success') {
        header.classList.add('success');
        document.getElementById('iconSuccess').style.display = 'block';
        title.textContent = 'Transaksi Sukses';
        subtitle.textContent = 'Pulsa telah dikirim ke nomor tujuan';

        if (data.sn) {
            document.getElementById('snSection').style.display = 'block';
            document.getElementById('invSN').textContent = data.sn;
        }

        document.getElementById('invStatus').innerHTML = '<span class="badge status-success"><i class="fas fa-check-circle"></i> SUKSES</span>';
    } else if (data.status === 'pending') {
        header.classList.add('pending');
        document.getElementById('iconPending').style.display = 'block';
        title.textContent = 'Transaksi Pending';
        subtitle.textContent = 'Menunggu konfirmasi dari server';
        document.getElementById('pendingWarning').style.display = 'block';
        document.getElementById('invStatus').innerHTML = '<span class="status-pending-badge"><i class="fas fa-clock"></i> MENUNGGU</span>';

        if (data.rc) {
            document.getElementById('rcSection').style.display = 'flex';
            document.getElementById('invRC').textContent = data.rc;
        }
    } else {
        header.classList.add('failed');
        document.getElementById('iconFailed').style.display = 'block';
        title.textContent = 'Transaksi Gagal';
        subtitle.textContent = data.keterangan || 'Silakan coba lagi';
        document.getElementById('invStatus').innerHTML = '<span class="badge status-failed"><i class="fas fa-times-circle"></i> GAGAL</span>';

        if (data.rc) {
            document.getElementById('rcSection').style.display = 'flex';
            document.getElementById('invRC').textContent = data.rc;
        }
    }

    // Show modal
    modal.classList.add('active');
}

// ── Auto-show modal if transaction result exists ──
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['last_transaction'])): ?>
    showInvoiceModal(<?= json_encode($_SESSION['last_transaction']) ?>);
    <?php unset($_SESSION['last_transaction']); endif; ?>
});

// ── Close modal on overlay click ──
document.getElementById('invoiceModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// ── Close on Escape key ──
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>