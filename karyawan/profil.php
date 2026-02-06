<?php
/**
 * ========================================
 * EMPLOYEE PROFILE
 * ========================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';

redirectIfUnauthorized('karyawan');

$user = getCurrentUser();
$user_id = $user['id'];

// Get user data from database
$query = "SELECT id, nama, email, whatsapp_number, gaji_harian, tarif_lembur_per_jam, created_at FROM users WHERE id = ?";
$user_data = getRow($conn, $query, [$user_id], 'i');

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $nama = sanitizeInput($_POST['nama'] ?? '');
        $whatsapp = sanitizeInput($_POST['whatsapp'] ?? '');

        if (empty($nama)) {
            $error = 'Nama tidak boleh kosong';
        } else {
            $query = "UPDATE users SET nama = ?, whatsapp_number = ? WHERE id = ?";
            if (executeModifyQuery($conn, $query, [$nama, $whatsapp, $user_id], 'ssi')) {
                $_SESSION['user_nama'] = $nama;
                $message = 'Profil berhasil diperbarui';
                $user_data['nama'] = $nama;
                $user_data['whatsapp_number'] = $whatsapp;
            } else {
                $error = 'Gagal memperbarui profil';
            }
        }
    } elseif ($action === 'change_password') {
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Semua field harus diisi';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Password baru tidak cocok';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password minimal 6 karakter';
        } else {
            // Verify old password
            $query = "SELECT password FROM users WHERE id = ?";
            $user_pass = getRow($conn, $query, [$user_id], 'i');

            if (!verifyPassword($old_password, $user_pass['password'])) {
                $error = 'Password lama salah';
            } else {
                $hashed_password = hashPassword($new_password);
                $query = "UPDATE users SET password = ? WHERE id = ?";
                if (executeModifyQuery($conn, $query, [$hashed_password, $user_id], 'si')) {
                    $message = 'Password berhasil diubah';
                } else {
                    $error = 'Gagal mengubah password';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Profil - Sistem Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .bottom-nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            height: 65px;
            padding-bottom: env(safe-area-inset-bottom);
        }
        
        .nav-item {
            transition: all 0.2s ease;
        }
        
        .nav-item.active {
            color: #3b82f6;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen pb-20 md:pb-0">
    <!-- Header / Navigation - Hidden on Mobile -->
    <nav class="bg-white shadow-lg hidden md:block">
        <div class="max-w-6xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-fingerprint text-white text-lg"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">AbsensiKu</h1>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 bg-gray-100 px-4 py-2 rounded-full">
                    <div class="w-8 h-8 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-white font-bold">
                        <?php echo strtoupper(substr($user['nama'], 0, 1)); ?>
                    </div>
                    <span class="text-gray-700 font-medium hidden lg:inline"><?php echo htmlspecialchars($user['nama']); ?></span>
                </div>
                <button onclick="window.location.href='/auth/logout.php'" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium rounded-lg hover:bg-gray-100">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-md md:max-w-4xl mx-auto px-4 pt-4 md:pt-8 pb-24 md:pb-8">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-xl flex items-center gap-3">
                <i class="fas fa-check-circle text-lg"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-xl flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-lg"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Profil Saya</h1>
            <p class="text-gray-600">Kelola informasi pribadi Anda</p>
        </div>

        <!-- Profile Info Card -->
        <div class="glass-card rounded-2xl overflow-hidden shadow-lg mb-6">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 flex items-center gap-4">
                <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-3xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($user_data['nama']); ?></h2>
                    <p class="text-blue-100"><?php echo htmlspecialchars($user_data['email']); ?></p>
                </div>
            </div>

            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-calendar text-blue-600 text-lg"></i>
                        <div>
                            <p class="text-gray-600 text-sm">Tanggal Bergabung</p>
                            <p class="font-semibold text-gray-800"><?php echo formatDateIndonesian($user_data['created_at']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-coins text-green-600 text-lg"></i>
                        <div>
                            <p class="text-gray-600 text-sm">Gaji Harian</p>
                            <p class="font-semibold text-gray-800"><?php echo formatCurrency($user_data['gaji_harian']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-fire text-orange-600 text-lg"></i>
                        <div>
                            <p class="text-gray-600 text-sm">Tarif Lembur per Jam</p>
                            <p class="font-semibold text-gray-800"><?php echo formatCurrency($user_data['tarif_lembur_per_jam']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Profile Form -->
        <div class="glass-card rounded-2xl p-6 shadow-lg mb-6">
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-edit text-blue-600"></i> Ubah Nama & WhatsApp
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_profile" />
                <div>
                    <label for="nama" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-user-circle mr-2 text-blue-600"></i>Nama Lengkap
                    </label>
                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        value="<?php echo htmlspecialchars($user_data['nama']); ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        required
                    />
                </div>
                <div>
                    <label for="whatsapp" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fab fa-whatsapp mr-2 text-green-600"></i>Nomor WhatsApp
                    </label>
                    <input
                        type="tel"
                        id="whatsapp"
                        name="whatsapp"
                        value="<?php echo htmlspecialchars($user_data['whatsapp_number'] ?? ''); ?>"
                        placeholder="62881234567 atau 081234567"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                    />
                    <p class="text-xs text-gray-500 mt-1">Format: 62 atau 0 (untuk menerima notifikasi absensi)</p>
                </div>
                <button
                    type="submit"
                    class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white py-3 px-6 rounded-lg font-semibold transition flex items-center justify-center gap-2"
                >
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>

        <!-- Change Password Form -->
        <div class="glass-card rounded-2xl p-6 shadow-lg">
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-lock text-red-600"></i> Ubah Password
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="change_password" />
                
                <div>
                    <label for="old_password" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-key mr-2 text-red-600"></i>Password Lama
                    </label>
                    <input
                        type="password"
                        id="old_password"
                        name="old_password"
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                    />
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-key mr-2 text-red-600"></i>Password Baru
                    </label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        required
                        minlength="6"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                    />
                    <p class="text-xs text-gray-500 mt-2"><i class="fas fa-info-circle mr-1"></i>Minimal 6 karakter</p>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-key mr-2 text-red-600"></i>Konfirmasi Password Baru
                    </label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        minlength="6"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                    />
                </div>

                <button
                    type="submit"
                    class="w-full bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white py-3 px-6 rounded-lg font-semibold transition flex items-center justify-center gap-2"
                >
                    <i class="fas fa-check"></i> Ubah Password
                </button>
            </form>
        </div>
    </div>

    <!-- Bottom Navigation - Mobile Only -->
    <div class="bottom-nav fixed bottom-0 left-0 right-0 md:hidden z-50">
        <div class="flex justify-around items-center h-full">
            <a href="/karyawan/dashboard.php" class="nav-item flex flex-col items-center justify-center text-gray-500">
                <i class="fas fa-home text-xl mb-1"></i>
                <span class="text-xs">Dashboard</span>
            </a>
            <a href="/karyawan/absensi.php" class="nav-item flex flex-col items-center justify-center text-gray-500">
                <i class="fas fa-camera text-xl mb-1"></i>
                <span class="text-xs">Absensi</span>
            </a>
            <a href="/karyawan/riwayat.php" class="nav-item flex flex-col items-center justify-center text-gray-500">
                <i class="fas fa-history text-xl mb-1"></i>
                <span class="text-xs">Riwayat</span>
            </a>
            <a href="/karyawan/profil.php" class="nav-item active flex flex-col items-center justify-center text-blue-600">
                <i class="fas fa-user text-xl mb-1"></i>
                <span class="text-xs">Profil</span>
            </a>
            <a href="/auth/logout.php" class="nav-item flex flex-col items-center justify-center text-gray-500">
                <i class="fas fa-sign-out-alt text-xl mb-1"></i>
                <span class="text-xs">Keluar</span>
            </a>
        </div>
    </div>

</body>
</html>
