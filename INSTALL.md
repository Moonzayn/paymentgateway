# PPOB Express - Installation Guide

## 📋 Pre-Installation Checklist

- [ ] PHP 7.4+ with MySQLi extension
- [ ] MySQL 5.7+ or MariaDB 10.3+
- [ ] SSL Certificate (for HTTPS)
- [ ] Web server (Apache/Nginx)

## 🚀 Installation Steps

### 1. Database Setup

```bash
# Create database
mysql -u root -p
CREATE DATABASE db_ppob CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ppob_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON db_ppob.* TO 'ppob_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u ppob_user -p db_ppob < database.sql
```

### 2. Configure Application

```bash
# Copy environment file
cp .env.example .env

# Edit .env with your settings
nano .env
```

**Important .env settings:**
- `DB_PASS`: Use strong password
- `APP_KEY`: Generate with `openssl rand -base64 32`
- `APP_DEBUG`: Set to `false` in production
- `ADMIN_IPS`: Add your IP for admin access (optional)

### 3. Update config.php

Edit `config.php` and update database credentials:
```php
define('DB_USER', 'ppob_user');
define('DB_PASS', 'your_secure_password');
```

### 4. Directory Permissions

```bash
# Set permissions
chmod 755 .
chmod 644 *.php
chmod 600 config.php
chmod 700 logs/
chmod 700 backups/
chmod 755 uploads/

# Create necessary directories
mkdir -p logs backups uploads/bukti_transfer
```

### 5. Web Server Configuration

#### Apache (.htaccess already included)
Ensure `mod_rewrite` and `mod_headers` are enabled:
```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl restart apache2
```

#### Nginx
Add to your server block:
```nginx
location ~ /\.ht {
    deny all;
}

location ~* \.(sql|log|ini|conf|env)$ {
    deny all;
}

location ~ /(logs|backups)/ {
    deny all;
}
```

### 6. Run Setup Wizard

1. Navigate to `https://your-domain.com/setup.php`
2. Create your admin account with strong password
3. **DELETE setup.php after completion!**

```bash
rm setup.php
```

### 7. SSL/HTTPS Configuration

#### Let's Encrypt (Recommended)
```bash
sudo certbot --apache -d your-domain.com
# or
sudo certbot --nginx -d your-domain.com
```

#### Manual SSL
Update `.env`:
```
SESSION_SECURE=true
APP_URL=https://your-domain.com
```

### 8. Final Security Checks

```bash
# Verify file permissions
ls -la config.php
ls -la .env
ls -la .htaccess

# Check setup.php deleted
ls setup.php  # Should show "No such file"

# Verify logs directory writable
touch logs/test && rm logs/test
```

## 🔒 Post-Installation Security

### 1. Change Default Passwords
- Login as admin
- Go to Kelola User
- Change admin password immediately

### 2. Configure IP Whitelist (Optional)
Edit `ip_whitelist.php`:
```php
$ADMIN_IP_WHITELIST = [
    'YOUR_ADMIN_IP_HERE',
    '192.168.1.100',
];
```

### 3. Enable Auto-Backup
Set up cron job:
```bash
# Backup database daily at 2 AM
0 2 * * * cd /path/to/ppob && php backup.php key=YOUR_SECRET_KEY
```

### 4. Monitor Logs
Regularly check:
- `logs/security.log` - Security events
- `logs/transactions.log` - Financial transactions
- `logs/admin_activity.log` - Admin actions

### 5. Update Regularly
```bash
# Keep dependencies updated
composer update

# Update system packages
sudo apt update && sudo apt upgrade
```

## ⚠️ Security Checklist

- [ ] Database credentials changed from defaults
- [ ] setup.php deleted
- [ ] config.php has secure permissions (600)
- [ ] .env file created and secured
- [ ] HTTPS enabled with valid SSL certificate
- [ ] Admin password is strong (8+ chars, mixed case, numbers, symbols)
- [ ] File upload directory protected
- [ ] Logs directory not accessible via web
- [ ] Database backups configured
- [ ] Error reporting disabled in production (APP_DEBUG=false)

## 🆘 Troubleshooting

### "Sesi tidak valid" error
- Clear browser cookies
- Refresh the page
- Ensure cookies are enabled

### "Access denied" for admin
- Check IP whitelist configuration
- Verify you're accessing via HTTPS

### Database connection failed
- Check DB_HOST in config.php
- Verify database user permissions
- Check if MySQL is running

### Permission denied errors
```bash
sudo chown -R www-data:www-data /path/to/ppob
sudo chmod -R 755 /path/to/ppob
sudo chmod 600 /path/to/ppob/config.php
```

## 📞 Support

For security issues, check:
1. `logs/security.log`
2. Web server error logs
3. Browser console for JavaScript errors

---

**IMPORTANT: Never share your .env file or database credentials!**
