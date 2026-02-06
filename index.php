<?php
/**
 * ========================================
 * MAIN INDEX
 * ========================================
 * Halaman awal yang mengalihkan ke login atau dashboard
 */

require_once __DIR__ . '/config/auth.php';

// If logged in, redirect to dashboard
if (isLoggedIn() && !isSessionExpired()) {
    $role = getCurrentRole();
    $dashboard_map = [
        'admin' => '/admin/dashboard.php',
        'hrd' => '/hrd/dashboard.php',
        'karyawan' => '/karyawan/dashboard.php'
    ];
    header('Location: ' . ($dashboard_map[$role] ?? '/auth/login.php'));
    exit;
}

// Otherwise, redirect to login
header('Location: /auth/login.php');
exit;
