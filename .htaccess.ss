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
