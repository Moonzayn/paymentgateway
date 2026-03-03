<?php
$secret = 'ddf29c89c806c2da9b10bfe9b490daf178fd20181c25cb1b383f4d0c42273778';

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

if ($signature) {
    $hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    if (hash_equals($hash, $signature)) {
        chdir('/var/www/paymentgateway');
        $output = shell_exec('git pull origin main 2>&1');
        file_put_contents('/tmp/deploy.log', date('Y-m-d H:i:s') . " - Webhook deployed\n$output\n", FILE_APPEND);
        echo 'Deployed successfully';
        http_response_code(200);
    } else {
        http_response_code(403);
        echo 'Invalid signature';
    }
} else {
    http_response_code(400);
    echo 'No signature';
}
