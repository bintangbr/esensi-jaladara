<?php
/**
 * ========================================
 * AUTHENTICATION & SESSION MANAGEMENT
 * ========================================
 * Fungsi untuk login, logout, session handling
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Session timeout in seconds (2 hours)
define('SESSION_TIMEOUT', 7200);

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user data
 * 
 * @return array|null User data or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'nama' => $_SESSION['user_nama'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
        'gaji_harian' => $_SESSION['user_gaji_harian'] ?? 0,
        'tarif_lembur' => $_SESSION['user_tarif_lembur'] ?? 0
    ];
}

/**
 * Get current user role
 * 
 * @return string|null User role or null if not logged in
 */
function getCurrentRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user ID
 * 
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Check if user has specific role
 * 
 * @param string|array $roles Role or array of roles
 * @return bool True if user has role
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['user_role'], $roles);
    }
    
    return $_SESSION['user_role'] === $roles;
}

/**
 * Create session after login
 * 
 * @param array $userData User data from database
 * @return void
 */
function createSession($userData) {
    $_SESSION['user_id'] = $userData['id'];
    $_SESSION['user_nama'] = $userData['nama'];
    $_SESSION['user_email'] = $userData['email'];
    $_SESSION['user_role'] = $userData['role'];
    $_SESSION['user_gaji_harian'] = $userData['gaji_harian'];
    $_SESSION['user_tarif_lembur'] = $userData['tarif_lembur_per_jam'];
    $_SESSION['login_time'] = time();
}

/**
 * Destroy session (logout)
 * 
 * @return void
 */
function destroySession() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Check session timeout
 * 
 * @return bool True if session expired
 */
function isSessionExpired() {
    if (!isLoggedIn()) {
        return true;
    }
    
    if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
        destroySession();
        return true;
    }
    
    return false;
}

/**
 * Redirect to login if not logged in
 * 
 * @return void
 */
function redirectIfNotLoggedIn() {
    if (!isLoggedIn() || isSessionExpired()) {
        header('Location: /index.php');
        exit;
    }
}

/**
 * Redirect if user role doesn't match
 * 
 * @param string|array $roles Required role(s)
 * @return void
 */
function redirectIfUnauthorized($roles) {
    redirectIfNotLoggedIn();
    
    if (!hasRole($roles)) {
        header('Location: /index.php?error=unauthorized');
        exit;
    }
}

/**
 * Verify password using bcrypt
 * 
 * @param string $password Plain password
 * @param string $hash Password hash from database
 * @return bool True if password matches
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Hash password using bcrypt
 * 
 * @param string $password Plain password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}