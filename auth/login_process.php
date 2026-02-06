<?php
/**
 * ========================================
 * LOGIN PROCESS
 * ========================================
 * Handle user login authentication
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';

// Only accept POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /auth/login.php');
    exit;
}

// Get form input
$email = sanitizeInput($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate input
$errors = [];

if (empty($email)) {
    $errors[] = 'Email is required';
}

if (empty($password)) {
    $errors[] = 'Password is required';
}

if (!empty($errors)) {
    $_SESSION['login_error'] = implode(', ', $errors);
    header('Location: /auth/login.php');
    exit;
}

// Query user from database
$query = "SELECT id, nama, email, password, role, gaji_harian, tarif_lembur_per_jam, status 
          FROM users WHERE email = ? AND status = 'aktif'";
$user = getRow($conn, $query, [$email], 's');

if (!$user) {
    $_SESSION['login_error'] = 'Email atau password salah';
    header('Location: /auth/login.php');
    exit;
}

// Verify password
if (!verifyPassword($password, $user['password'])) {
    $_SESSION['login_error'] = 'Email atau password salah';
    header('Location: /auth/login.php');
    exit;
}

// Create session
createSession($user);

// Redirect to dashboard based on role
$dashboard_map = [
    'admin' => '/admin/dashboard.php',
    'hrd' => '/hrd/dashboard.php',
    'karyawan' => '/karyawan/dashboard.php'
];

$redirect_url = $dashboard_map[$user['role']] ?? '/index.php';
header('Location: ' . $redirect_url);
exit;
