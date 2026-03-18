<?php
/**
 * API v1 Configuration
 * Include this file at the top of all API v1 endpoints
 */

require_once __DIR__ . '/../../config.php';

// Set CORS headers for mobile app
setCorsHeaders();

// Get the API key from header
$apiKey = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

// Validate API key
$currentUser = validateApiKey($apiKey);

function requireAuth() {
    global $currentUser;

    if (!$currentUser) {
        apiError('Unauthorized. Invalid or expired API key.', 'UNAUTHORIZED', 401);
    }

    return $currentUser;
}

function getCurrentUser() {
    global $currentUser;
    return $currentUser;
}
