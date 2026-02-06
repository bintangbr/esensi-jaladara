<?php
/**
 * ========================================
 * LOGIN PAGE
 * ========================================
 */

require_once __DIR__ . '/../config/auth.php';

// Redirect to dashboard if already logged in
if (isLoggedIn() && !isSessionExpired()) {
    $role = getCurrentRole();
    $dashboard_map = [
        'admin' => '/admin/dashboard.php',
        'hrd' => '/hrd/dashboard.php',
        'karyawan' => '/karyawan/dashboard.php'
    ];
    header('Location: ' . ($dashboard_map[$role] ?? '/index.php'));
    exit;
}

// Get error/success messages
$error = $_SESSION['login_error'] ?? '';
$success = ($_GET['message'] ?? '') === 'logged_out' ? 'You have been logged out successfully' : '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-500 to-purple-600 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Card Container -->
        <div class="bg-white rounded-lg shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-8 text-center">
                <h1 class="text-3xl font-bold text-white mb-2">Sistem Absensi</h1>
                <p class="text-blue-100">Login ke sistem</p>
            </div>

            <!-- Form Container -->
            <div class="px-6 py-8">
                <!-- Success Message -->
                <?php if ($success): ?>
                    <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" action="/auth/login_process.php" class="space-y-4">
                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            required
                            autocomplete="email"
                            placeholder="Masukkan email Anda"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                        />
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Masukkan password Anda"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                        />
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-2 px-4 rounded-lg font-semibold hover:shadow-lg transition duration-200"
                    >
                        Login
                    </button>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-white text-sm">
            <p>&copy; 2026 Sistem Absensi. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
