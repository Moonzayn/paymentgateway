<?php
// =============================================
// PPOB SECURITY MIDDLEWARE
// =============================================

/**
 * Apply security headers to prevent common attacks
 */
function applySecurityHeaders() {
    // Prevent XSS attacks
    header("X-XSS-Protection: 1; mode=block");
    
    // Prevent clickjacking
    header("X-Frame-Options: DENY");
    
    // Prevent MIME type sniffing
    header("X-Content-Type-Options: nosniff");
    
    // Enforce HTTPS
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self';");
    
    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Permissions Policy
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

/**
 * Validate and sanitize input data
 */
function sanitizeInput($data, $type = 'string') {
    switch ($type) {
        case 'string':
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        case 'email':
            return filter_var(trim($data), FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT);
        case 'float':
            return filter_var($data, FILTER_VALIDATE_FLOAT);
        case 'url':
            return filter_var(trim($data), FILTER_SANITIZE_URL);
        default:
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Log security events for audit trail
 */
function logSecurityEvent($event_type, $details, $user_id = null) {
    $log_file = __DIR__ . '/logs/security.log';
    
    // Create logs directory if not exists
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $user_id = $user_id ?? ($_SESSION['user_id'] ?? 'guest');
    
    $log_entry = sprintf(
        "[%s] %s | User: %s | IP: %s | Event: %s | Details: %s | UA: %s\n",
        $timestamp,
        date('Y-m-d H:i:s'),
        $user_id,
        $ip_address,
        $event_type,
        json_encode($details),
        substr($user_agent, 0, 200)
    );
    
    error_log($log_entry, 3, $log_file);
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowed_types = ['image/jpeg', 'image/png', 'image/gif'], $max_size = 5242880) {
    $errors = [];
    
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'No file uploaded'];
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Upload error: ' . $file['error']];
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        return ['valid' => false, 'error' => 'File too large. Max: ' . ($max_size / 1024 / 1024) . 'MB'];
    }
    
    // Validate mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['valid' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
    }
    
    // Additional check for image files
    if (strpos($mime_type, 'image/') === 0) {
        if (!getimagesize($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'Invalid image file'];
        }
    }
    
    return ['valid' => true, 'mime_type' => $mime_type];
}

/**
 * Generate secure random filename
 */
function generateSecureFilename($original_name) {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    return bin2hex(random_bytes(16)) . '_' . time() . '.' . $extension;
}

/**
 * Check for suspicious activity
 */
function checkSuspiciousActivity() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'suspicious_' . md5($ip);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $data = $_SESSION[$key];
    $now = time();
    
    // Reset after 1 hour
    if ($now - $data['first_attempt'] > 3600) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => $now];
        $data = $_SESSION[$key];
    }
    
    // Increment counter
    $data['count']++;
    $_SESSION[$key] = $data;
    
    // Block if too many suspicious activities
    if ($data['count'] > 20) {
        logSecurityEvent('SUSPICIOUS_ACTIVITY_BLOCKED', ['ip' => $ip, 'count' => $data['count']]);
        http_response_code(403);
        die('Akses ditolak. Terlalu banyak aktivitas mencurigakan.');
    }
    
    return $data['count'];
}

/**
 * Verify request method
 */
function requirePostMethod() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Method not allowed');
    }
}

/**
 * Verify CSRF token for all POST requests
 */
function verifyCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        
        if (!validateCSRFToken($token)) {
            logSecurityEvent('CSRF_ATTEMPT', ['token' => $token]);
            http_response_code(403);
            die('Invalid security token. Please refresh the page and try again.');
        }
    }
}

/**
 * Create security-focused .htaccess rules
 */
function generateSecurityHtaccess() {
    return <<<'HTACCESS'
# Security Headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
</IfModule>

# Disable server signature
ServerTokens Prod
ServerSignature Off

# Protect sensitive files
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

<FilesMatch "\.(sql|log|ini|conf|env|gitignore)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Disable directory browsing
Options -Indexes

# Protect config file
<Files "config.php">
    Order allow,deny
    Deny from all
</Files>

# Protect logs directory
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^logs/ - [F,L]
    RewriteRule ^\.git/ - [F,L]
</IfModule>

# PHP settings (if .htaccess allowed)
<IfModule mod_php.c>
    php_flag display_errors off
    php_value upload_max_filesize 5M
    php_value post_max_size 5M
    php_value max_execution_time 30
    php_value memory_limit 128M
</IfModule>
HTACCESS;
}

// Auto-apply security headers
applySecurityHeaders();
