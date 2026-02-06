<?php
/**
 * ========================================
 * LOGOUT
 * ========================================
 * Handle user logout
 */

require_once __DIR__ . '/../config/auth.php';

destroySession();
header('Location: /auth/login.php?message=logged_out');
exit;
