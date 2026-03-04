<?php
require_once 'config.php';

// Check if this is required from login (user not logged in yet)
$isRequired = isset($_GET['required']) && $_GET['required'] == 1;

if ($isRequired) {
    // User belum login, cek apakah ada session untuk setup 2FA
    if (!isset($_SESSION['2fa_required_user_id'])) {
        // Tidak ada session, redirect ke login
        header("Location: login.php");
        exit;
    }
    $user_id = $_SESSION['2fa_required_user_id'];
} else {
    // Normal access - user harus sudah login
    cekLogin();
    $user_id = $_SESSION['user_id'];
}

$conn = koneksi();

// Get current 2FA status
$stmt = $conn->prepare("SELECT enabled, enabled_at FROM user_2fa WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$twofa = $result->fetch_assoc();

$isEnabled = $twofa && $twofa['enabled'] === 'yes';

// Set layout variables
$pageTitle = 'Setup 2FA';
$pageIcon = 'fas fa-shield-alt';
$currentPage = 'setup_2fa';

include 'layout.php';
?>

<style>
.setup-2fa-container {
    max-width: 600px;
    margin: 0 auto;
}

.qr-code-box {
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
}

.qr-code-box img {
    width: 200px;
    height: 200px;
    border-radius: 12px;
}

.secret-box {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    padding: 1rem;
    font-family: monospace;
    font-size: 1.25rem;
    letter-spacing: 2px;
    text-align: center;
    color: var(--primary);
}

.backup-codes {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.backup-code {
    background: #f1f5f9;
    padding: 0.5rem;
    border-radius: 6px;
    font-family: monospace;
    font-size: 0.875rem;
    text-align: center;
}

.timer-box {
    background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
}

.input-code {
    font-size: 2rem;
    letter-spacing: 0.5rem;
    text-align: center;
}

.btn-copy {
    cursor: pointer;
    color: var(--primary);
}

.copy-success {
    color: #22c55e;
}
</style>

<div class="setup-2fa-container">

    <!-- Header -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; background: var(--primary-50); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-shield-alt" style="color: var(--primary); font-size: 1.5rem;"></i>
            </div>
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0;">Two-Factor Authentication (2FA)</h2>
                <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">
                    Amankan akun Anda dengan Google Authenticator
                </p>
            </div>
            <div style="margin-left: auto; display: flex; gap: 0.5rem; align-items: center;">
                <?php if ($isRequired): ?>
                    <span class="badge badge-failed" style="padding: 0.5rem 1rem; font-size: 0.75rem; background: #fee2e2; color: #991b1b;">
                        <i class="fas fa-exclamation-triangle"></i> WAJIB
                    </span>
                <?php endif; ?>
                <?php if ($isEnabled): ?>
                    <span class="badge badge-success" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                        <i class="fas fa-check-circle"></i> Aktif
                    </span>
                <?php else: ?>
                    <span class="badge badge-pending" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                        <i class="fas fa-exclamation-circle"></i> Belum Aktif
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($isRequired): ?>
    <!-- Warning untuk 2FA wajib -->
    <div class="card" style="padding: 1rem; margin-bottom: 1.5rem; background: #fee2e2; border: 1px solid #fecaca;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class="fas fa-exclamation-triangle" style="color: #991b1b; font-size: 1.5rem;"></i>
            <div>
                <p style="font-weight: 600; color: #991b1b; margin: 0;">2FA Wajib Diaktifkan</p>
                <p style="color: #b91c1c; font-size: 0.875rem; margin: 0;">Anda harus mengaktifkan 2FA untuk dapat menggunakan sistem.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Setup Section (jika belum aktif) -->
    <?php if (!$isEnabled): ?>
    <div id="setupSection">
        <!-- Step 1: Instructions -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 1rem;">
            <h3 style="font-weight: 600; margin-bottom: 1rem;">
                <span style="background: var(--primary); color: white; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; margin-right: 0.5rem;">1</span>
                Install Google Authenticator
            </h3>
            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 1rem;">
                Download dan install <strong>Google Authenticator</strong> atau <strong>Microsoft Authenticator</strong> dari App Store / Play Store.
            </p>
            <button onclick="initSetup()" class="btn-primary" style="width: 100%;">
                <i class="fas fa-qrcode"></i> Generate QR Code
            </button>
        </div>

        <script>
        // Auto generate QR code on page load
        document.addEventListener('DOMContentLoaded', function() {
            initSetup();
        });
        </script>

        <!-- Step 2: QR Code -->
        <div id="qrSection" style="display: none;">
            <div class="qr-code-box" style="margin-bottom: 1rem;">
                <h3 style="font-weight: 600; margin-bottom: 1rem;">
                    <span style="background: var(--primary); color: white; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; margin-right: 0.5rem;">2</span>
                    Scan QR Code
                </h3>
                <img id="qrImage" src="" alt="QR Code" style="margin:1rem auto;display:block;max-width:200px;border:2px solid #333;">
                <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1rem;">
                    Buka Google Authenticator dan scan QR code di atas
                </p>
                <div style="margin: 1rem 0;">
                    <p style="font-size: 0.75rem; color: var(--text-muted);">Atau masukkan kode ini secara manual:</p>
                    <div class="secret-box" id="secretDisplay">-</div>
                    <button onclick="copySecret()" class="btn-copy" style="background: none; border: none; margin-top: 0.5rem; font-size: 0.875rem;">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
            </div>

            <!-- Step 3: Verify -->
            <div class="card" style="padding: 1.5rem;">
                <h3 style="font-weight: 600; margin-bottom: 1rem;">
                    <span style="background: var(--primary); color: white; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; margin-right: 0.5rem;">3</span>
                    Verifikasi Kode
                </h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem;">
                    Masukkan kode 6 digit dari Google Authenticator untuk verifikasi
                </p>

                <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 1rem;">
                    <div class="timer-box" id="timerBox">
                        <i class="fas fa-clock"></i> <span id="timer">30</span>s
                    </div>
                </div>

                <input type="text" id="verifyCode" class="input-code"
                       placeholder="000000" maxlength="6"
                       style="width: 100%; padding: 1rem; border: 2px solid var(--border); border-radius: 12px; margin-bottom: 1rem; font-size: 1.5rem; text-align: center; letter-spacing: 0.5rem;">

                <button onclick="verifySetup()" id="verifyBtn" class="btn-primary" style="width: 100%;">
                    <i class="fas fa-check"></i> Aktifkan 2FA
                </button>

                <p id="verifyError" style="color: #ef4444; font-size: 0.875rem; text-align: center; margin-top: 1rem; display: none;"></p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Status Aktif -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 1rem;">
        <div style="text-align: center; padding: 2rem 0;">
            <div style="width: 80px; height: 80px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                <i class="fas fa-check" style="color: #16a34a; font-size: 2rem;"></i>
            </div>
            <h3 style="font-weight: 600; margin-bottom: 0.5rem;">2FA Sudah Aktif</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem;">
                Diaktifkan sejak: <?= date('d F Y, H:i', strtotime($twofa['enabled_at'])) ?>
            </p>
        </div>
    </div>

    <!-- Disable 2FA -->
    <div class="card" style="padding: 1.5rem;">
        <h3 style="font-weight: 600; margin-bottom: 1rem; color: #dc2626;">
            Nonaktifkan 2FA
        </h3>
        <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem;">
            Masukkan kode dari Google Authenticator untuk menonaktifkan 2FA
        </p>
        <input type="text" id="disableCode" class="input-code"
               placeholder="000000" maxlength="6"
               style="width: 100%; padding: 1rem; border: 2px solid var(--border); border-radius: 12px; margin-bottom: 1rem; font-size: 1.5rem; text-align: center; letter-spacing: 0.5rem;">
        <button onclick="disable2FA()" class="btn-primary" style="width: 100%; background: #dc2626;">
            <i class="fas fa-times"></i> Nonaktifkan 2FA
        </button>
    </div>
    <?php endif; ?>

    <!-- Help -->
    <div class="card" style="padding: 1.5rem; margin-top: 1.5rem;">
        <h3 style="font-weight: 600; margin-bottom: 1rem;">
            <i class="fas fa-question-circle" style="color: var(--primary);"></i>
            Perlu Bantuan?
        </h3>
        <ul style="font-size: 0.875rem; color: var(--text-secondary); padding-left: 1.25rem;">
            <li style="margin-bottom: 0.5rem;">Kode berubah setiap 30 detik</li>
            <li style="margin-bottom: 0.5rem;">Pastikan waktu di hp Anda sudah benar</li>
            <li style="margin-bottom: 0.5rem;">Jika kehilangan hp, hubungi admin untuk reset 2FA</li>
        </ul>
    </div>

</div>

<script>
let secret = '';
let timerInterval = null;
let otpauthUrl = '';

function initSetup() {
    // Auto-detect correct path based on current location
    var pathParts = window.location.pathname.split('/');
    var apiPath = '/api/2fa_setup.php';

    // If payment folder exists in URL, use payment/api
    if (pathParts.includes('payment')) {
        apiPath = '/payment/api/2fa_setup.php';
    }

    fetch(apiPath + '?action=init')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                document.getElementById('qrSection').style.display = 'block';
                document.getElementById('secretDisplay').textContent = data.secret;
                secret = data.secret;
                otpauthUrl = data.otpauth || 'otpauth://totp/PPOB Express:user@ppobexpress?secret=' + secret + '&issuer=PPOB Express';

                // Use img tag with qr_url from API (PHP generates QR)
                var qrImg = document.getElementById('qrImage');
                if (qrImg) {
                    qrImg.src = data.qr_url;
                    qrImg.onerror = function() {
                        // Fallback: show manual entry
                        this.style.display = 'none';
                        document.getElementById('secretDisplay').innerHTML = '<span style="color:red">QR Gagal Load!</span><br>Secret: ' + secret;
                    };
                }

                startTimer();
            } else {
                alert(data.message);
            }
        })
        .catch(function(err) {
            console.error(err);
            alert('Gagal mengambil data. Refresh halaman dan coba lagi.');
        });
}

function tryAlternateQR(imgEl) {
    if (qrAlternateUrl && imgEl.src !== qrAlternateUrl) {
        imgEl.src = qrAlternateUrl;
    } else {
        // Show manual entry message
        imgEl.style.display = 'none';
        const secretEl = document.getElementById('secretDisplay');
        secretEl.innerHTML = '<span style="color:red;">QR Code gagal load!</span><br>Silakan masukkan kode ini secara manual di Google Authenticator:<br><strong style="font-size:1.2em;letter-spacing:2px;">' + secret + '</strong>';
    }
}

function startTimer() {
    let time = 30;
    if (timerInterval) clearInterval(timerInterval);

    timerInterval = setInterval(() => {
        time--;
        document.getElementById('timer').textContent = time;
        if (time <= 0) time = 30;
    }, 1000);
}

function verifySetup() {
    const code = document.getElementById('verifyCode').value.trim();
    const errorEl = document.getElementById('verifyError');
    const isRequired = new URLSearchParams(window.location.search).get('required') === '1';

    if (!code || code.length !== 6) {
        errorEl.textContent = 'Masukkan kode 6 digit';
        errorEl.style.display = 'block';
        return;
    }

    const formData = new FormData();
    formData.append('code', code);

    document.getElementById('verifyBtn').disabled = true;
    document.getElementById('verifyBtn').textContent = 'Memverifikasi...';

    fetch('/payment/api/2fa_setup.php?action=verify', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Show backup codes
            let codesHtml = '<div class="backup-codes">';
            data.backup_codes.forEach(c => {
                codesHtml += `<div class="backup-code">${c}</div>`;
            });
            codesHtml += '</div>';

            alert('2FA Berhasil Diaktifkan!\n\nSIMPAN KODE CADANGAN INI:\n' + data.backup_codes.join('\n') + '\n\nKode ini digunakan jika Anda kehilangan hp!');

            // If required mode, redirect to index, otherwise reload
            if (isRequired) {
                window.location.href = 'index.php';
            } else {
                location.reload();
            }
        } else {
            errorEl.textContent = data.message;
            errorEl.style.display = 'block';
        }
    })
    .catch(err => {
        errorEl.textContent = 'Terjadi kesalahan. Silakan coba lagi.';
        errorEl.style.display = 'block';
    })
    .finally(() => {
        document.getElementById('verifyBtn').disabled = false;
        document.getElementById('verifyBtn').innerHTML = '<i class="fas fa-check"></i> Aktifkan 2FA';
    });
}

function disable2FA() {
    const code = document.getElementById('disableCode').value.trim();

    if (!code || code.length !== 6) {
        alert('Masukkan kode 6 digit');
        return;
    }

    if (!confirm('Apakah Anda yakin ingin menonaktifkan 2FA? Akun Anda akan kurang aman.')) {
        return;
    }

    const formData = new FormData();
    formData.append('code', code);

    fetch('/payment/api/2fa_setup.php?action=disable', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('2FA berhasil dinonaktifkan');
            location.reload();
        } else {
            alert(data.message);
        }
    });
}

function copySecret() {
    navigator.clipboard.writeText(secret).then(() => {
        alert('Secret disalin!');
    });
}

// Enter key to submit
document.getElementById('verifyCode')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        verifySetup();
    }
});
</script>

<?php
include 'layout_footer.php';
$conn->close();
