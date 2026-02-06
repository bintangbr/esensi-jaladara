<?php
/**
 * ========================================
 * ADMIN - USER MANAGEMENT
 * ========================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';

redirectIfUnauthorized('admin');

$user = getCurrentUser();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $nama = sanitizeInput($_POST['nama'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $gaji_harian = (float) ($_POST['gaji_harian'] ?? 0);
        $tarif_lembur = (float) ($_POST['tarif_lembur_per_jam'] ?? 0);

        if (empty($nama) || empty($email) || empty($password) || empty($role)) {
            $error = 'Semua field harus diisi';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email tidak valid';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter';
        } else {
            // Check if email already exists
            $query = "SELECT id FROM users WHERE email = ?";
            $existing = getRow($conn, $query, [$email], 's');

            if ($existing) {
                $error = 'Email sudah terdaftar';
            } else {
                $hashed_password = hashPassword($password);
                $query = "INSERT INTO users (nama, email, password, role, gaji_harian, tarif_lembur_per_jam, status) 
                         VALUES (?, ?, ?, ?, ?, ?, 'aktif')";

                if (executeModifyQuery($conn, $query, [$nama, $email, $hashed_password, $role, $gaji_harian, $tarif_lembur], 'ssssdd')) {
                    $message = 'User berhasil ditambahkan';
                } else {
                    $error = 'Gagal menambahkan user';
                }
            }
        }

    } elseif ($action === 'edit_user') {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $nama = sanitizeInput($_POST['nama'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $gaji_harian = (float) ($_POST['gaji_harian'] ?? 0);
        $tarif_lembur = (float) ($_POST['tarif_lembur_per_jam'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (empty($user_id) || empty($nama) || empty($email) || empty($role)) {
            $error = 'Semua field harus diisi';
        } else {
            $query = "UPDATE users SET nama = ?, email = ?, role = ?, gaji_harian = ?, tarif_lembur_per_jam = ?, status = ? WHERE id = ?";

            if (executeModifyQuery($conn, $query, [$nama, $email, $role, $gaji_harian, $tarif_lembur, $status, $user_id], 'sssddsi')) {
                $message = 'User berhasil diperbarui';
            } else {
                $error = 'Gagal memperbarui user';
            }
        }

    } elseif ($action === 'delete_user') {
        $user_id = (int) ($_POST['user_id'] ?? 0);

        if ($user_id === $user['id']) {
            $error = 'Anda tidak bisa menghapus akun sendiri';
        } else {
            $query = "DELETE FROM users WHERE id = ?";
            if (executeModifyQuery($conn, $query, [$user_id], 'i')) {
                $message = 'User berhasil dihapus';
            } else {
                $error = 'Gagal menghapus user';
            }
        }

    } elseif ($action === 'reset_password') {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';

        if (empty($user_id) || empty($new_password)) {
            $error = 'User ID dan password harus diisi';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password minimal 6 karakter';
        } else {
            $hashed_password = hashPassword($new_password);
            $query = "UPDATE users SET password = ? WHERE id = ?";

            if (executeModifyQuery($conn, $query, [$hashed_password, $user_id], 'si')) {
                $message = 'Password berhasil direset';
            } else {
                $error = 'Gagal mereset password';
            }
        }
    }
}

// Get all users
$query = "SELECT * FROM users ORDER BY nama ASC";
$users = getRows($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Sistem Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <!-- Header / Navigation -->
    <nav class="bg-gradient-to-r from-red-600 to-red-800 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-white">Kelola User</h1>
                <div class="flex items-center gap-6 text-white">
                    <a href="/admin/dashboard.php" class="hover:text-red-200">Dashboard</a>
                    <a href="/admin/setting.php" class="hover:text-red-200">Pengaturan</a>
                    <a href="/auth/logout.php" class="text-red-200 hover:text-red-100">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto mt-8 px-4 mb-8">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                ✓ <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                ✗ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Add User Button -->
        <div class="mb-6">
            <button onclick="showAddUserForm()" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-6 rounded-lg font-semibold">
                + Tambah User Baru
            </button>
        </div>

        <!-- Add User Form (Hidden) -->
        <div id="addUserForm" class="hidden bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-2xl font-bold mb-4">Tambah User Baru</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_user" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Nama Lengkap</label>
                        <input type="text" name="nama" required class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <input type="password" name="password" required minlength="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                        <select name="role" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <option value="karyawan">Karyawan</option>
                            <option value="hrd">HRD</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Gaji Harian</label>
                        <input type="number" name="gaji_harian" step="0.01" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tarif Lembur / Jam</label>
                        <input type="number" name="tarif_lembur_per_jam" step="0.01" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white py-2 px-6 rounded-lg font-semibold">
                        Tambah User
                    </button>
                    <button type="button" onclick="hideAddUserForm()" class="bg-gray-400 hover:bg-gray-500 text-white py-2 px-6 rounded-lg font-semibold">
                        Batal
                    </button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
                <h3 class="text-lg font-bold">Daftar User (<?php echo count($users); ?>)</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Nama</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Email</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Role</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Gaji Harian</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Tarif Lembur</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Status</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-semibold"><?php echo htmlspecialchars($u['nama']); ?></td>
                                <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php 
                                        echo match($u['role']) {
                                            'admin' => 'bg-red-100 text-red-800',
                                            'hrd' => 'bg-purple-100 text-purple-800',
                                            'karyawan' => 'bg-blue-100 text-blue-800',
                                            default => 'bg-gray-100'
                                        };
                                    ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm"><?php echo formatCurrency($u['gaji_harian']); ?></td>
                                <td class="px-6 py-4 text-sm"><?php echo formatCurrency($u['tarif_lembur_per_jam']); ?></td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php 
                                        echo $u['status'] === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                    ?>">
                                        <?php echo ucfirst($u['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm space-y-2">
                                    <button onclick="showEditForm(<?php echo htmlspecialchars(json_encode($u)); ?>)" 
                                            class="block bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs font-semibold">
                                        Edit
                                    </button>
                                    <button onclick="showResetPasswordForm(<?php echo $u['id']; ?>)"
                                            class="block bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-xs font-semibold">
                                        Reset
                                    </button>
                                    <?php if ($u['id'] !== $user['id']): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin?');">
                                            <input type="hidden" name="action" value="delete_user" />
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>" />
                                            <button type="submit" class="block w-full bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs font-semibold">
                                                Hapus
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4 max-h-96 overflow-y-auto">
            <h2 class="text-2xl font-bold mb-4">Edit User</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_user" />
                <input type="hidden" id="editUserId" name="user_id" />

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nama</label>
                    <input type="text" id="editNama" name="nama" required class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                    <input type="email" id="editEmail" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                    <select id="editRole" name="role" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="karyawan">Karyawan</option>
                        <option value="hrd">HRD</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Gaji Harian</label>
                    <input type="number" id="editGaji" name="gaji_harian" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tarif Lembur</label>
                    <input type="number" id="editTarif" name="tarif_lembur_per_jam" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select id="editStatus" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Non-Aktif</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg font-semibold">
                        Simpan
                    </button>
                    <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white py-2 px-4 rounded-lg font-semibold">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4">
            <h2 class="text-2xl font-bold mb-4">Reset Password</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="reset_password" />
                <input type="hidden" id="resetUserId" name="user_id" />

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Password Baru</label>
                    <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-lg font-semibold">
                        Reset
                    </button>
                    <button type="button" onclick="closeResetPasswordModal()" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white py-2 px-4 rounded-lg font-semibold">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddUserForm() {
            document.getElementById('addUserForm').classList.remove('hidden');
        }

        function hideAddUserForm() {
            document.getElementById('addUserForm').classList.add('hidden');
        }

        function showEditForm(userData) {
            document.getElementById('editUserId').value = userData.id;
            document.getElementById('editNama').value = userData.nama;
            document.getElementById('editEmail').value = userData.email;
            document.getElementById('editRole').value = userData.role;
            document.getElementById('editGaji').value = userData.gaji_harian;
            document.getElementById('editTarif').value = userData.tarif_lembur_per_jam;
            document.getElementById('editStatus').value = userData.status;
            document.getElementById('editUserModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editUserModal').classList.add('hidden');
        }

        function showResetPasswordForm(userId) {
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetPasswordModal').classList.remove('hidden');
        }

        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.add('hidden');
        }
    </script>
</body>
</html>
