<?php
/**
 * ========================================
 * ADMIN SETTINGS
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

    if ($action === 'update_gps') {
        $lat = $_POST['gps_lat'] ?? '';
        $lon = $_POST['gps_lon'] ?? '';
        $radius = $_POST['gps_radius'] ?? '';

        if (empty($lat) || empty($lon) || empty($radius)) {
            $error = 'Semua field GPS harus diisi';
        } else {
            $lat = (float) $lat;
            $lon = (float) $lon;
            $radius = (int) $radius;

            $success = true;
            $success &= updateSetting($conn, 'gps_kantor_latitude', $lat);
            $success &= updateSetting($conn, 'gps_kantor_longitude', $lon);
            $success &= updateSetting($conn, 'gps_kantor_radius', $radius);

            if ($success) {
                $message = 'Pengaturan GPS berhasil diperbarui';
            } else {
                $error = 'Gagal memperbarui pengaturan GPS';
            }
        }

    } elseif ($action === 'update_worktime') {
        $jam_kerja = $_POST['jam_kerja_standar'] ?? '';

        if (empty($jam_kerja)) {
            $error = 'Jam kerja standar harus diisi';
        } else {
            $jam_kerja = (int) $jam_kerja;

            if (updateSetting($conn, 'jam_kerja_standar', $jam_kerja)) {
                $message = 'Jam kerja standar berhasil diperbarui menjadi ' . $jam_kerja . ' jam/hari';
            } else {
                $error = 'Gagal memperbarui jam kerja standar';
            }
        }

    } elseif ($action === 'update_company') {
        $nama = $_POST['nama_perusahaan'] ?? '';
        $alamat = $_POST['alamat_kantor'] ?? '';

        if (empty($nama)) {
            $error = 'Nama perusahaan harus diisi';
        } else {
            $success = true;
            $success &= updateSetting($conn, 'nama_perusahaan', $nama);
            $success &= updateSetting($conn, 'alamat_kantor', $alamat);

            if ($success) {
                $message = 'Informasi perusahaan berhasil diperbarui';
            } else {
                $error = 'Gagal memperbarui informasi perusahaan';
            }
        }

    } elseif ($action === 'update_whatsapp') {
        $api_key = $_POST['whatsapp_api_key'] ?? '';
        $sender = $_POST['whatsapp_sender'] ?? '';

        if (empty($api_key) || empty($sender)) {
            $error = 'API Key dan Sender harus diisi';
        } else {
            $success = true;
            $success &= updateSetting($conn, 'whatsapp_api_key', trim($api_key));
            $success &= updateSetting($conn, 'whatsapp_sender', trim($sender));

            if ($success) {
                $message = 'Konfigurasi WhatsApp berhasil diperbarui';
            } else {
                $error = 'Gagal memperbarui konfigurasi WhatsApp';
            }
        }
    }
}

// Get current settings
$gps_lat = getSetting($conn, 'gps_kantor_latitude', -6.2088);
$gps_lon = getSetting($conn, 'gps_kantor_longitude', 106.8456);
$gps_radius = getSetting($conn, 'gps_kantor_radius', 100);
$jam_kerja = getSetting($conn, 'jam_kerja_standar', 8);
$nama_perusahaan = getSetting($conn, 'nama_perusahaan', 'PT. Aplikasi Absensi');
$alamat_kantor = getSetting($conn, 'alamat_kantor', 'Jakarta, Indonesia');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Admin - Sistem Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <!-- Header / Navigation -->
    <nav class="bg-gradient-to-r from-red-600 to-red-800 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-white">Sistem Absensi</h1>
                    <p class="text-red-100 text-sm">Pengaturan</p>
                </div>
                <div class="flex items-center gap-6 text-white">
                    <a href="/admin/dashboard.php" class="hover:text-red-200">Dashboard</a>
                    <a href="/admin/users.php" class="hover:text-red-200">Kelola User</a>
                    <a href="/admin/laporan.php" class="hover:text-red-200">Laporan</a>
                    <a href="/auth/logout.php" class="text-red-200 hover:text-red-100">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto mt-8 px-4 mb-8">
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

        <!-- GPS Settings -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-gray-800">📍 Pengaturan GPS Kantor</h2>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_gps" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="gps_lat" class="block text-sm font-semibold text-gray-700 mb-2">
                            Latitude Kantor
                        </label>
                        <input
                            type="number"
                            id="gps_lat"
                            name="gps_lat"
                            step="0.0001"
                            value="<?php echo $gps_lat; ?>"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p class="text-xs text-gray-500 mt-1">Contoh: -6.2088</p>
                    </div>

                    <div>
                        <label for="gps_lon" class="block text-sm font-semibold text-gray-700 mb-2">
                            Longitude Kantor
                        </label>
                        <input
                            type="number"
                            id="gps_lon"
                            name="gps_lon"
                            step="0.0001"
                            value="<?php echo $gps_lon; ?>"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p class="text-xs text-gray-500 mt-1">Contoh: 106.8456</p>
                    </div>
                </div>

                <div>
                    <label for="gps_radius" class="block text-sm font-semibold text-gray-700 mb-2">
                        Radius Kantor (Meter)
                    </label>
                    <input
                        type="number"
                        id="gps_radius"
                        name="gps_radius"
                        min="1"
                        value="<?php echo $gps_radius; ?>"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <p class="text-xs text-gray-500 mt-1">Jarak maksimum dari kantor untuk validasi GPS (default: 100 meter)</p>
                </div>

                <button
                    type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-6 rounded-lg font-semibold transition"
                >
                    Simpan Pengaturan GPS
                </button>
            </form>

            <!-- Map Info -->
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-gray-700"><strong>Lokasi Kantor Saat Ini:</strong></p>
                <p class="text-sm text-gray-600 mt-2">
                    Latitude: <code class="bg-gray-200 px-2 py-1 rounded"><?php echo $gps_lat; ?></code> |
                    Longitude: <code class="bg-gray-200 px-2 py-1 rounded"><?php echo $gps_lon; ?></code> |
                    Radius: <code class="bg-gray-200 px-2 py-1 rounded"><?php echo $gps_radius; ?> meter</code>
                </p>
                <p class="text-xs text-gray-500 mt-2">
                    💡 Tip: Gunakan Google Maps untuk menemukan koordinat lokasi kantor Anda
                </p>
            </div>
        </div>

        <!-- Working Hours Settings -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">⏱️ Jam Kerja Standar</h2>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_worktime" />

                <div>
                    <label for="jam_kerja_standar" class="block text-sm font-semibold text-gray-700 mb-2">
                        Jam Kerja per Hari
                    </label>
                    <input
                        type="number"
                        id="jam_kerja_standar"
                        name="jam_kerja_standar"
                        min="1"
                        max="24"
                        value="<?php echo $jam_kerja; ?>"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <p class="text-xs text-gray-500 mt-1">Standar industri: 8 jam per hari</p>
                </div>

                <button
                    type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-6 rounded-lg font-semibold transition"
                >
                    Simpan Jam Kerja
                </button>
            </form>

            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-gray-700"><strong>Pengaturan Saat Ini:</strong></p>
                <p class="text-sm text-gray-600 mt-1">
                    Jam kerja standar: <code class="bg-gray-200 px-2 py-1 rounded"><?php echo $jam_kerja; ?> jam/hari</code>
                </p>
                <p class="text-xs text-gray-500 mt-2">
                    💡 Lembur dihitung jika total jam kerja > jam standar per hari
                </p>
            </div>
        </div>

        <!-- Company Settings -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">🏢 Informasi Perusahaan</h2>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_company" />

                <div>
                    <label for="nama_perusahaan" class="block text-sm font-semibold text-gray-700 mb-2">
                        Nama Perusahaan
                    </label>
                    <input
                        type="text"
                        id="nama_perusahaan"
                        name="nama_perusahaan"
                        value="<?php echo htmlspecialchars($nama_perusahaan); ?>"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>

                <div>
                    <label for="alamat_kantor" class="block text-sm font-semibold text-gray-700 mb-2">
                        Alamat Kantor
                    </label>
                    <textarea
                        id="alamat_kantor"
                        name="alamat_kantor"
                        rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    ><?php echo htmlspecialchars($alamat_kantor); ?></textarea>
                </div>

                <button
                    type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-6 rounded-lg font-semibold transition"
                >
                    Simpan Informasi Perusahaan
                </button>
            </form>
        </div>

        <!-- WhatsApp Configuration Section -->
        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl shadow-md border border-green-200 p-6">
            <div class="flex items-center gap-3 mb-6">
                <i class="fas fa-whatsapp text-green-600 text-2xl"></i>
                <h2 class="text-2xl font-bold text-gray-800">Pengaturan WhatsApp</h2>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_whatsapp">

                <div>
                    <label for="whatsapp_api_key" class="block text-sm font-semibold text-gray-700 mb-2">
                        API Key WhatsApp
                    </label>
                    <input
                        type="text"
                        id="whatsapp_api_key"
                        name="whatsapp_api_key"
                        value="<?php echo htmlspecialchars(getSetting($conn, 'whatsapp_api_key') ?? ''); ?>"
                        placeholder="Masukkan API Key dari LinkBit"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle"></i> Dapatkan API Key dari https://linkbit.biz.id
                    </p>
                </div>

                <div>
                    <label for="whatsapp_sender" class="block text-sm font-semibold text-gray-700 mb-2">
                        Nomor WhatsApp Pengirim
                    </label>
                    <input
                        type="tel"
                        id="whatsapp_sender"
                        name="whatsapp_sender"
                        value="<?php echo htmlspecialchars(getSetting($conn, 'whatsapp_sender') ?? ''); ?>"
                        placeholder="62881234567 atau 081234567"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle"></i> Nomor WhatsApp perangkat untuk mengirim notifikasi
                    </p>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                    <p class="text-xs text-gray-700">
                        <i class="fas fa-check-circle text-green-600"></i> 
                        Konfigurasi ini digunakan untuk mengirim notifikasi WhatsApp kepada admin dan karyawan ketika melakukan absensi.
                    </p>
                </div>

                <button
                    type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-6 rounded-lg font-semibold transition flex items-center justify-center gap-2"
                >
                    <i class="fas fa-save"></i> Simpan Konfigurasi WhatsApp
                </button>
            </form>
        </div>
    </div>
</body>
</html>
