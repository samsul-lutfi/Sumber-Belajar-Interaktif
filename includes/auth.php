<?php
/**
 * Authentication Functions
 * 
 * This file contains functions for user authentication
 */

/**
 * Register a new user
 * 
 * @param string $username Username
 * @param string $password Password
 * @param string $email Email
 * @param string $full_name Full name
 * @param string $role Role (student or admin)
 * @return array ['success' => bool, 'message' => string]
 */
function register_user($username, $password, $email, $full_name, $role = 'student') {
    global $conn;
    
    // Check if username already exists
    $check_username = "SELECT COUNT(*) as count FROM users WHERE username = ?";
    $query = execute_query($check_username, [$username]);
    
    if ($query === false) {
        return ['success' => false, 'message' => 'Terjadi kesalahan saat memeriksa username.'];
    }
    
    $username_count = $query['result']->fetchArray(SQLITE3_ASSOC)['count'];
    
    if ($username_count > 0) {
        return ['success' => false, 'message' => 'Username sudah digunakan. Silakan pilih username lain.'];
    }
    
    // Check if email already exists
    $check_email = "SELECT COUNT(*) as count FROM users WHERE email = ?";
    $query = execute_query($check_email, [$email]);
    
    if ($query === false) {
        return ['success' => false, 'message' => 'Terjadi kesalahan saat memeriksa email.'];
    }
    
    $email_count = $query['result']->fetchArray(SQLITE3_ASSOC)['count'];
    
    if ($email_count > 0) {
        return ['success' => false, 'message' => 'Email sudah digunakan. Silakan gunakan email lain.'];
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $insert_user = "INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)";
    $query = execute_query($insert_user, [$username, $hashed_password, $email, $full_name, $role]);
    
    if ($query === false) {
        return ['success' => false, 'message' => 'Terjadi kesalahan saat membuat akun.'];
    }
    
    return ['success' => true, 'message' => 'Registrasi berhasil. Silakan login.'];
}

/**
 * Authenticate user
 * 
 * @param string $username Username
 * @param string $password Password
 * @return array ['success' => bool, 'message' => string, 'user' => array]
 */
function login_user($username, $password) {
    global $conn;
    
    // Get user by username
    $get_user = "SELECT * FROM users WHERE username = ?";
    $query = execute_query($get_user, [$username]);
    
    if ($query === false) {
        return ['success' => false, 'message' => 'Terjadi kesalahan saat memeriksa akun.', 'user' => null];
    }
    
    $user = $query['result']->fetchArray(SQLITE3_ASSOC);
    
    if (!$user) {
        return ['success' => false, 'message' => 'Username atau password salah.', 'user' => null];
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Username atau password salah.', 'user' => null];
    }
    
    // Update last login time
    $update_login = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
    execute_query($update_login, [$user['id']]);
    
    // Log login activity
    log_activity($user['id'], 'login', 'auth');
    
    // Get updated user data
    $get_updated_user = "SELECT * FROM users WHERE id = ?";
    $updated_query = execute_query($get_updated_user, [$user['id']]);
    $updated_user = $updated_query['result']->fetchArray(SQLITE3_ASSOC);
    
    return ['success' => true, 'message' => 'Login berhasil.', 'user' => $updated_user ?? $user];
}

/**
 * Get user by ID
 * 
 * @param int $user_id User ID
 * @return array|null User data or null if not found
 */
function get_user_by_id($user_id) {
    global $conn;
    
    $get_user = "SELECT * FROM users WHERE id = ?";
    $query = execute_query($get_user, [$user_id]);
    
    if ($query === false) {
        return null;
    }
    
    return $query['result']->fetchArray(SQLITE3_ASSOC);
}

/**
 * Update user password
 * 
 * @param int $user_id User ID
 * @param string $current_password Current password
 * @param string $new_password New password
 * @return array ['success' => bool, 'message' => string]
 */
function update_password($user_id, $current_password, $new_password) {
    global $conn;
    
    // Get user
    $user = get_user_by_id($user_id);
    
    if (!$user) {
        return ['success' => false, 'message' => 'Pengguna tidak ditemukan.'];
    }
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        return ['success' => false, 'message' => 'Password saat ini salah.'];
    }
    
    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $update_password = "UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $query = execute_query($update_password, [$hashed_password, $user_id]);
    
    if ($query === false) {
        return ['success' => false, 'message' => 'Terjadi kesalahan saat mengubah password.'];
    }
    
    // Log password change activity
    log_activity($user_id, 'password_change', 'auth');
    
    return ['success' => true, 'message' => 'Password berhasil diubah.'];
}

/**
 * Update user profile
 * 
 * @param int $user_id User ID
 * @param string $email Email
 * @param string $full_name Full name
 * @return array ['success' => bool, 'message' => string]
 */
function update_profile($user_id, $email, $full_name) {
    global $conn;
    
    // Check if email already exists
    $check_email = "SELECT COUNT(*) as count FROM users WHERE email = ? AND id != ?";
    $query = execute_query($check_email, [$email, $user_id]);
    
    if ($query === false) {
        return ['success' => false, 'message' => 'Terjadi kesalahan saat memeriksa email.'];
    }
    
    $email_count = $query['result']->fetchArray(SQLITE3_ASSOC)['count'];
    
    if ($email_count > 0) {
        return ['success' => false, 'message' => 'Email sudah digunakan. Silakan gunakan email lain.'];
    }
    
    // Update profile
    $update_profile = "UPDATE users SET email = ?, full_name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $query = execute_query($update_profile, [$email, $full_name, $user_id]);
    
    if ($query === false) {
        return ['success' => false, 'message' => 'Terjadi kesalahan saat mengubah profil.'];
    }
    
    // Log profile update activity
    log_activity($user_id, 'profile_update', 'auth');
    
    return ['success' => true, 'message' => 'Profil berhasil diubah.'];
}

/**
 * Get user list
 * 
 * @param string $role Filter by role (optional)
 * @param int $limit Limit (optional)
 * @param int $offset Offset (optional)
 * @return array Users
 */
function get_users($role = null, $limit = null, $offset = null) {
    global $conn;
    
    $sql = "SELECT * FROM users";
    $params = [];
    
    if ($role) {
        $sql .= " WHERE role = ?";
        $params[] = $role;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    if ($limit) {
        $sql .= " LIMIT ?";
        $params[] = $limit;
        
        if ($offset) {
            $sql .= " OFFSET ?";
            $params[] = $offset;
        }
    }
    
    $query = execute_query($sql, $params);
    
    if ($query === false) {
        return [];
    }
    
    $users = [];
    while ($row = $query['result']->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    
    return $users;
}

// count_users function moved to functions.php
?>