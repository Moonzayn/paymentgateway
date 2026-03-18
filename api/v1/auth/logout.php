<?php
/**
 * API v1 - Logout Endpoint
 * Method: POST
 * Header: Authorization: Bearer <api_key>
 */

require_once __DIR__ . '/../config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

// Require authentication
$user = requireAuth();

// Invalidate the API key
$apiKey = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (strpos($apiKey, 'Bearer ') === 0) {
    $apiKey = substr($apiKey, 7);
}

invalidateApiKey($apiKey);

apiSuccess(null, 'Logout berhasil');
