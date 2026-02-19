<?php
/**
 * Password Strength Validation
 * Enforce strong password policies
 */

/**
 * Validate password strength
 * Returns array with 'valid' (bool) and 'errors' (array)
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    // Minimum length
    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter';
    }
    
    // Maximum length (prevent DoS)
    if (strlen($password) > 128) {
        $errors[] = 'Password maksimal 128 karakter';
    }
    
    // Check for uppercase
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password harus mengandung huruf besar (A-Z)';
    }
    
    // Check for lowercase
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password harus mengandung huruf kecil (a-z)';
    }
    
    // Check for numbers
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password harus mengandung angka (0-9)';
    }
    
    // Check for special characters
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = 'Password harus mengandung karakter khusus (!@#$%^&* dll)';
    }
    
    // Check for common weak passwords
    $weak_passwords = [
        'password', '12345678', 'qwerty123', 'admin123', 'password123',
        '123456789', 'iloveyou', 'welcome1', 'monkey123', 'dragon123'
    ];
    
    if (in_array(strtolower($password), $weak_passwords)) {
        $errors[] = 'Password terlalu umum, gunakan password yang lebih kuat';
    }
    
    // Check for sequential characters
    if (preg_match('/(012|123|234|345|456|567|678|789|890|abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz)/i', $password)) {
        $errors[] = 'Password tidak boleh mengandung karakter berurutan (123, abc, dll)';
    }
    
    // Check for repeated characters
    if (preg_match('/(.)\1{2,}/', $password)) {
        $errors[] = 'Password tidak boleh mengandung karakter yang diulang (aaa, 111, dll)';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'strength' => calculatePasswordStrength($password)
    ];
}

/**
 * Calculate password strength score (0-100)
 */
function calculatePasswordStrength($password) {
    $score = 0;
    
    // Length
    $length = strlen($password);
    if ($length >= 8) $score += 10;
    if ($length >= 12) $score += 10;
    if ($length >= 16) $score += 10;
    
    // Character variety
    if (preg_match('/[a-z]/', $password)) $score += 10;
    if (preg_match('/[A-Z]/', $password)) $score += 15;
    if (preg_match('/[0-9]/', $password)) $score += 15;
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $score += 20;
    
    // Mixed case
    if (preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password)) {
        $score += 10;
    }
    
    return min(100, $score);
}

/**
 * Get password strength label
 */
function getPasswordStrengthLabel($score) {
    if ($score < 30) return ['label' => 'Sangat Lemah', 'color' => 'red'];
    if ($score < 50) return ['label' => 'Lemah', 'color' => 'orange'];
    if ($score < 70) return ['label' => 'Sedang', 'color' => 'yellow'];
    if ($score < 90) return ['label' => 'Kuat', 'color' => 'green'];
    return ['label' => 'Sangat Kuat', 'color' => 'darkgreen'];
}

/**
 * Generate strong password suggestion
 */
function generateStrongPassword($length = 16) {
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';
    
    $password = '';
    
    // Ensure at least one of each type
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];
    
    // Fill the rest
    $all = $lowercase . $uppercase . $numbers . $special;
    for ($i = 4; $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }
    
    // Shuffle
    return str_shuffle($password);
}

/**
 * Hash password securely
 */
function hashPasswordSecure($password) {
    // Use PHP's default algorithm (currently bcrypt)
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPasswordSecure($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Check if password needs rehash (algorithm upgraded)
 */
function passwordNeedsRehash($hash) {
    return password_needs_rehash($hash, PASSWORD_DEFAULT);
}

// JavaScript validation function for frontend
function getPasswordValidationJS() {
    return <<<'JS'
function validatePassword(password) {
    const errors = [];
    
    if (password.length < 8) errors.push('Password minimal 8 karakter');
    if (password.length > 128) errors.push('Password maksimal 128 karakter');
    if (!/[A-Z]/.test(password)) errors.push('Harus ada huruf besar');
    if (!/[a-z]/.test(password)) errors.push('Harus ada huruf kecil');
    if (!/[0-9]/.test(password)) errors.push('Harus ada angka');
    if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) errors.push('Harus ada karakter khusus');
    
    const weakPasswords = ['password', '12345678', 'qwerty123', 'admin123'];
    if (weakPasswords.includes(password.toLowerCase())) {
        errors.push('Password terlalu umum');
    }
    
    return {
        valid: errors.length === 0,
        errors: errors
    };
}

function calculateStrength(password) {
    let score = 0;
    if (password.length >= 8) score += 10;
    if (password.length >= 12) score += 10;
    if (password.length >= 16) score += 10;
    if (/[a-z]/.test(password)) score += 10;
    if (/[A-Z]/.test(password)) score += 15;
    if (/[0-9]/.test(password)) score += 15;
    if (/[^a-zA-Z0-9]/.test(password)) score += 20;
    return Math.min(100, score);
}
JS;
}
