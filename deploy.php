<?php
/**
 * Auto Deploy Script - Pull from GitHub
 * Setup:
 * 1. Clone repo ke server: cd /home/crocomyi/public_html && git clone https://github.com/Moonzayn/paymentgateway.git .
 * 2. Atau kalau sudah ada: git remote set-url origin https://github.com/Moonzayn/paymentgateway.git
 * 3. Setup GitHub Webhook: Settings → Webhooks → Add webhook
 *    - Payload URL: https://croco.my.id/deploy.php?secret=YOUR_TOKEN
 *    - Content type: application/json
 *    - Events: Just the push event
 */

$secret = 'deploy_secret_token_2026'; // GANTI DENGAN TOKEN RAHASIA KAMU
$githubSecret = $_GET['secret'] ?? '';

// Verify secret
if ($githubSecret !== $secret) {
    http_response_code(401);
    echo 'Unauthorized - Invalid secret';
    exit;
}

// Log file
$logFile = __DIR__ . '/deploy.log';

// Get payload
$payload = file_get_contents('php://input');
$payloadJson = json_decode($payload, true);

// Only deploy on push to main branch
if (isset($payloadJson['ref']) && $payloadJson['ref'] === 'refs/heads/main') {
    $timestamp = date('Y-m-d H:i:s');

    // Git commands
    $commands = [
        'cd /home/crocomyi/public_html && git pull origin main 2>&1'
    ];

    $output = "=== Deploy Started: $timestamp ===\n";
    $output .= "Payload: " . json_encode($payloadJson) . "\n";

    foreach ($commands as $cmd) {
        $output .= "Running: $cmd\n";
        $output .= shell_exec($cmd) . "\n";
    }

    $output .= "=== Deploy Completed ===\n\n";

    // Write to log
    file_put_contents($logFile, $output, FILE_APPEND);

    echo "✅ Deploy triggered successfully!";
} else {
    echo "⏭️ Not a main branch push, skipping...";
}

// Also handle manual trigger
if (isset($_GET['manual'])) {
    $timestamp = date('Y-m-d H:i:s');
    $output = "=== Manual Deploy: $timestamp ===\n";
    $output .= shell_exec('cd /home/crocomyi/public_html && git pull origin main 2>&1');
    $output .= "\n=== Done ===\n";
    file_put_contents($logFile, $output, FILE_APPEND);
    echo $output;
}
