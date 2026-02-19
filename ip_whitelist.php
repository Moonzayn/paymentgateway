<?php
/**
 * Admin IP Whitelist System
 * Restrict admin access to specific IP addresses
 */

// List of allowed IPs for admin access (configure as needed)
$ADMIN_IP_WHITELIST = [
    // Add your admin IPs here
    // '192.168.1.1',
    // '10.0.0.1',
    // '127.0.0.1', // Localhost for development
];

/**
 * Check if current IP is whitelisted for admin
 */
function isAdminIPWhitelisted() {
    global $ADMIN_IP_WHITELIST;
    
    // If whitelist is empty, allow all (for development)
    if (empty($ADMIN_IP_WHITELIST)) {
        return true;
    }
    
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Check exact match
    if (in_array($current_ip, $ADMIN_IP_WHITELIST)) {
        return true;
    }
    
    // Check CIDR ranges (e.g., 192.168.1.0/24)
    foreach ($ADMIN_IP_WHITELIST as $allowed) {
        if (strpos($allowed, '/') !== false) {
            if (ipInCIDR($current_ip, $allowed)) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Check if IP is in CIDR range
 */
function ipInCIDR($ip, $cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet);
}

/**
 * Enforce IP whitelist for admin pages
 */
function enforceAdminIPWhitelist() {
    if (!isAdminIPWhitelisted()) {
        // Log unauthorized access attempt
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        error_log("[" . date('Y-m-d H:i:s') . "] UNAUTHORIZED ADMIN ACCESS from IP: $ip");
        
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>Access denied. Your IP is not authorized.</p>');
    }
}

/**
 * Get current IP address info
 */
function getCurrentIPInfo() {
    return [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'time' => date('Y-m-d H:i:s')
    ];
}

/**
 * Check admin access with comprehensive security
 */
function checkAdminAccess() {
    // Check if logged in
    cekLogin();
    
    // Check if admin
    cekAdmin();
    
    // Check IP whitelist
    enforceAdminIPWhitelist();
}

// Example usage in admin pages:
// require_once 'ip_whitelist.php';
// checkAdminAccess();
