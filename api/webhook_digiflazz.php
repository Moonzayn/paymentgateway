<?php
/**
 * Digiflazz Webhook Handler
 * URL: https://gorolpay.id/webhook/digiflazz
 * 
 * Events:
 * - create: New transaction
 * - update: Transaction status change
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config.php';

// Webhook secret from Digiflazz dashboard
define('WEBHOOK_SECRET', 'SkM23XONCmWO');

function verifySignature($payload, $signature) {
    $expected = 'sha1=' . hash_hmac('sha1', $payload, WEBHOOK_SECRET);
    return $signature === $expected;
}

function logWebhook($data) {
    $logFile = dirname(__DIR__) . '/logs/webhook_' . date('Y-m-d') . '.log';
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . json_encode($data) . "\n", FILE_APPEND);
}

try {
    // Get raw input
    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput, true);
    
    // Get headers
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
    $eventType = $_SERVER['HTTP_X_DIGIFLAZZ_EVENT'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Log incoming webhook
    logWebhook([
        'event' => $eventType,
        'user_agent' => $userAgent,
        'signature' => $signature,
        'payload' => $payload
    ]);
    
    // Verify signature
    if (!empty($signature) && !verifySignature($rawInput, $signature)) {
        echo json_encode(['success' => false, 'message' => 'Invalid signature']);
        logWebhook(['error' => 'Invalid signature']);
        exit;
    }
    
    // Determine transaction type from user agent
    $isPostpaid = strpos($userAgent, 'Pasca') !== false;
    $jenisTransaksi = $isPostpaid ? 'pascabayar' : 'prepaid';
    
    // Process based on event type
    if ($eventType === 'create' || $eventType === 'update') {
        if (!isset($payload['data'])) {
            echo json_encode(['success' => false, 'message' => 'No data payload']);
            exit;
        }
        
        $data = $payload['data'];
        $refId = $data['ref_id'] ?? '';
        $status = strtolower($data['status'] ?? '');
        $message = $data['message'] ?? '';
        $customerNo = $data['customer_no'] ?? '';
        $sn = $data['sn'] ?? null;
        
        // Map Digiflazz status to our status
        $statusMap = [
            'success' => 'success',
            'sukses' => 'success',
            'pending' => 'pending',
            'failed' => 'failed',
            'gagal' => 'failed'
        ];
        $finalStatus = $statusMap[$status] ?? 'pending';
        
        // Find transaction by ref_id
        $conn = koneksi();
        
        // Find by ref_id (our format LST..., PLS...)
        $stmt = $conn->prepare("SELECT * FROM transaksi WHERE ref_id = ?");
        $stmt->bind_param("s", $refId);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaksi = $result->fetch_assoc();
        
        // If not found, try to find byDigiflazz ref_id in server_id
        if (!$transaksi) {
            $stmt = $conn->prepare("SELECT * FROM transaksi WHERE server_id = ?");
            $stmt->bind_param("s", $refId);
            $stmt->execute();
            $result = $stmt->get_result();
            $transaksi = $result->fetch_assoc();
        }
        
        if ($transaksi) {
            // Update existing transaction
            $apiResponse = json_encode($data);
            $stmt = $conn->prepare("UPDATE transaksi SET status = ?, keterangan = ?, api_response = ?, server_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssssi", $finalStatus, $message, $apiResponse, $refId, $transaksi['id']);
            $stmt->execute();
            
            logWebhook([
                'action' => 'update_transaction',
                'transaksi_id' => $transaksi['id'],
                'ref_id' => $refId,
                'status' => $finalStatus,
                'message' => $message
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Transaction updated',
                'transaksi_id' => $transaksi['id'],
                'status' => $finalStatus
            ]);
        } else {
            // Transaction not found - log for debugging
            logWebhook([
                'action' => 'transaction_not_found',
                'ref_id' => $refId,
                'customer_no' => $customerNo
            ]);
            
            echo json_encode([
                'success' => false,
                'message' => 'Transaction not found',
                'ref_id' => $refId
            ]);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'Event received: ' . $eventType]);
    }
    
} catch (Exception $e) {
    logWebhook(['error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}