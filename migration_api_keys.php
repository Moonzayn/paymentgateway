<?php
/**
 * Migration: Create api_keys table
 * Untuk menyimpan API keys untuk autentikasi mobile app Flutter
 */

$conn = new mysqli('localhost', 'root', '', 'db_ppob');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    secret_key VARCHAR(128) NOT NULL UNIQUE,
    name VARCHAR(100) DEFAULT 'Flutter App',
    platform VARCHAR(50) DEFAULT 'flutter',
    device_id VARCHAR(100),
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    is_active ENUM('yes', 'no') DEFAULT 'yes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_api_key (api_key),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Table 'api_keys' created successfully!\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
