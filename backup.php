<?php
/**
 * Database Backup Utility untuk PPOB
 * Run this script manually or via cron job
 * 
 * ⚠️ SECURITY: This file requires Strong Authentication
 */

require_once 'config.php';
cekLogin(); // Require login

// Only admin can access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    die('Access denied. Admin only.');
}

// Generate strong random token for cron
$cron_token = $_GET['cron_token'] ?? '';

// Simple token verification for cron - use a strong random string
// Set this in your cron job: backup.php?cron_token=YOUR_STRONG_RANDOM_TOKEN
$expected_cron_token = 'YOUR_STRONG_RANDOM_TOKEN_CHANGE_ME';

// Verify cron token if provided (for automated backups)
if (!empty($cron_token) && $cron_token !== $expected_cron_token) {
    http_response_code(403);
    die('Invalid token');
}

// Also verify session for web access
if (php_sapi_name() !== 'cli' && empty($cron_token)) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
        http_response_code(403);
        die('Access denied');
    }
}

/**
 * Backup database ke file SQL
 */
function backupDatabase($host, $user, $pass, $dbname, $backupDir = 'backups/') {
    // Create backup directory if not exists
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // Generate filename with timestamp
    $filename = $backupDir . 'backup_' . $dbname . '_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Connect to database
    $conn = new mysqli($host, $user, $pass, $dbname);
    
    if ($conn->connect_error) {
        return ['success' => false, 'error' => 'Connection failed: ' . $conn->connect_error];
    }
    
    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    $sql = "-- PPOB Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: " . $dbname . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        // Get create table statement
        $result = $conn->query("SHOW CREATE TABLE $table");
        $row = $result->fetch_array();
        $sql .= "\n-- Table structure for table `$table`\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $row[1] . ";\n\n";
        
        // Get table data
        $result = $conn->query("SELECT * FROM $table");
        $numFields = $result->field_count;
        
        if ($result->num_rows > 0) {
            $sql .= "-- Dumping data for table `$table`\n";
            
            while ($row = $result->fetch_assoc()) {
                $sql .= "INSERT INTO `$table` VALUES(";
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $conn->real_escape_string($value) . "'";
                    }
                }
                $sql .= implode(', ', $values);
                $sql .= ");\n";
            }
            $sql .= "\n";
        }
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    
    // Save to file
    if (file_put_contents($filename, $sql)) {
        // Compress the file
        $zipname = $filename . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipname, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($filename, basename($filename));
            $zip->close();
            unlink($filename); // Delete uncompressed file
            $filename = $zipname;
        }
        
        // Delete old backups (keep last 7 days)
        deleteOldBackups($backupDir, 7);
        
        return [
            'success' => true, 
            'file' => $filename, 
            'size' => formatBytes(filesize($filename))
        ];
    } else {
        return ['success' => false, 'error' => 'Failed to write backup file'];
    }
}

/**
 * Delete old backup files
 */
function deleteOldBackups($backupDir, $daysToKeep = 7) {
    $files = glob($backupDir . 'backup_*.sql*');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) > 60 * 60 * 24 * $daysToKeep) {
                unlink($file);
            }
        }
    }
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Log backup activity
 */
function logBackup($message, $type = 'info') {
    $log_file = __DIR__ . '/logs/backup.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$type] $message\n";
    error_log($log_entry, 3, $log_file);
}

// Auto-run backup if called directly
if (basename($_SERVER['PHP_SELF']) == 'backup.php') {
    // Check for secret key (for cron job security)
    $secret_key = $_GET['key'] ?? '';
    $expected_key = 'YOUR_SECRET_BACKUP_KEY'; // Change this!
    
    if ($secret_key !== $expected_key && php_sapi_name() !== 'cli') {
        http_response_code(403);
        die('Access denied');
    }
    
    $result = backupDatabase(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($result['success']) {
        logBackup("Backup created successfully: " . $result['file'] . " (" . $result['size'] . ")");
        echo "Backup created successfully!\n";
        echo "File: " . $result['file'] . "\n";
        echo "Size: " . $result['size'] . "\n";
    } else {
        logBackup("Backup failed: " . $result['error'], 'error');
        echo "Backup failed: " . $result['error'] . "\n";
    }
}
