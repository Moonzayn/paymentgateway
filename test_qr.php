<?php
/**
 * Test 2FA / TOTP Functionality - Simple Version
 */
require_once 'api/totp_helper.php';
require_once 'api/simple_qr.php';

echo "=== TOTP Helper Test ===\n\n";

// Test 1: Generate Secret
$secret = generateTOTPSecret();
echo "1. Generate Secret: $secret\n\n";

// Test 2: Generate QR Code URL (local)
$qr = getTOTPQRUrl($secret, 'admin@ppobexpress');
// For local testing, generate directly
require_once 'api/simple_qr.php';
$localQr = new SimpleQRCode();
$otpauthUrl = 'otpauth://totp/PPOB%20Express:admin@ppobexpress?secret=' . $secret . '&issuer=PPOB%20Express';
$qrUrl = $localQr->generate($otpauthUrl, 200);
echo "2. QR Code URL (bisa di-scan dengan Google Authenticator):\n";
echo "   $qr\n\n";

// Test 3: Generate current TOTP code
$currentCode = getCurrentCode($secret);
echo "3. Current TOTP Code (untuk verifikasi): $currentCode\n";
echo "   Time Remaining: " . getTOTPRemainingTime() . " seconds\n\n";

// Test 4: Verify current code
echo "4. Self-Verify Current Code:\n";
$valid = verifyTOTPCode($secret, $currentCode);
echo "   Result: " . ($valid ? "VALID ✓" : "INVALID ✗") . "\n\n";

// Test 5: Verify wrong code
echo "5. Verify Wrong Code (000000):\n";
$validWrong = verifyTOTPCode($secret, '000000');
echo "   Result: " . ($validWrong ? "VALID (unexpected)" : "INVALID (expected) ✓") . "\n\n";

// Test 6: Verify wrong code length
echo "6. Verify Wrong Length (12345):\n";
$validShort = verifyTOTPCode($secret, '12345');
echo "   Result: " . ($validShort ? "VALID (unexpected)" : "INVALID (expected) ✓") . "\n\n";

echo "=== Test Complete ===\n";
echo "\n==> Jika semua test menunjukkan ✓, maka TOTP berfungsi dengan benar!\n";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test 2FA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 16px; padding: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        h1 { text-align: center; color: #333; margin-bottom: 20px; }
        .qr-box { text-align: center; margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 12px; }
        .qr-box img { width: 200px; height: 200px; border: 3px solid #333; border-radius: 8px; }
        .secret { font-family: monospace; font-size: 14px; word-break: break-all; padding: 10px; background: #e9ecef; border-radius: 8px; margin-top: 10px; }
        .code-display { text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; margin: 20px 0; }
        .code-display .code { font-size: 48px; font-weight: bold; color: white; letter-spacing: 8px; font-family: monospace; }
        .code-display .timer { color: rgba(255,255,255,0.8); margin-top: 10px; }
        .instructions { background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107; }
        .instructions h3 { margin-top: 0; color: #856404; }
        .instructions ol { margin-bottom: 0; color: #856404; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-shield-alt"></i> Test 2FA</h1>

        <div class="qr-box">
            <h3>Scan dengan Google Authenticator:</h3>
            <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Code" style="max-width:200px;">
            <p>atau masukkan secret ini secara manual:</p>
            <div class="secret"><?= htmlspecialchars($secret) ?></div>
        </div>

        <div class="code-display">
            <div class="code"><?= htmlspecialchars($currentCode) ?></div>
            <div class="timer">Refresh dalam <span id="timer"><?= getTOTPRemainingTime() ?></span> detik</div>
        </div>

        <div class="instructions">
            <h3>Cara Menguji:</h3>
            <ol>
                <li>Scan QR code di atas dengan Google Authenticator</li>
                <li>Bandingkan kode yang muncul di HP dengan kode di atas</li>
                <li>Kedua kode harus SAMA!</li>
            </ol>
        </div>
    </div>

    <script>
        // Auto refresh page every 30 seconds
        setTimeout(() => location.reload(), 30000);

        let timerInterval = setInterval(() => {
            let time = parseInt(document.getElementById('timer').textContent);
            time--;
            if (time <= 0) {
                time = 30;
                // Refresh the page when timer expires
                location.reload();
            }
            document.getElementById('timer').textContent = time;
        }, 1000);
    </script>
</body>
</html>
