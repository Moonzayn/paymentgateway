<?php
/**
 * API v1 - Refresh Token Endpoint
 * Method: POST
 * Header: Authorization: Bearer <api_key>
 *
 * Refresh the API key (generate new one, invalidate old)
 */

require_once __DIR__ . '/../config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

// Require authentication
$currentUser = requireAuth();

// Get old API key
$oldApiKey = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (strpos($oldApiKey, 'Bearer ') === 0) {
    $oldApiKey = substr($oldApiKey, 7);
}

// Invalidate old key
invalidateApiKey($oldApiKey);

// Generate new token
$tokenData = generateApiToken(
    $currentUser['user_id'],
    'Flutter App Refresh',
    'flutter',
    null
);

if (!$tokenData) {
    apiError('Gagal membuat token baru', 'TOKEN_ERROR', 500);
}

apiSuccess([
    'token' => $tokenData['api_key'],
    'secret' => $tokenData['secret_key'],
    'expires_at' => $tokenData['expires_at']
], 'Token berhasil diperbarui');
