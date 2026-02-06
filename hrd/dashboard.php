<?php
/**
 * ========================================
 * HRD DASHBOARD
 * ========================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';

redirectIfUnauthorized('hrd');

$user = getCurrentUser();

// Get statistics
$today = date('Y-m-d');
$this_month_start = date('Y-m-01');
$this_month_end = date('Y-m-t');

// Total employees
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'karyawan'";
$total_employees = getRow($conn, $query)['total'];

// Today's attendance
$query = "SELECT COUNT(*) as total FROM absensi WHERE tanggal = ? AND status = 'selesai'";
$today_present = getRow($conn, $query, [$today], 's')['total'];

$query = "SELECT COUNT(*) as total FROM absensi WHERE tanggal = ? AND status = 'belum_absen'";
$today_absent = getRow($conn, $query, [$today], 's')['total'];

// Recent attendance
$query = "SELECT a.*, u.nama FROM absensi a JOIN users u ON a.user_id = u.id WHERE a.tanggal = ? ORDER BY a.created_at DESC LIMIT 5";
$recent_attendance = getRows($conn, $query, [$today], 's');

// Calculate presence rate
$total_today = $today_present + $today_absent;
$rate = $total_today > 0 ? round(($today_present / $total_today) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRD Dashboard - Sistem Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header / Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-purple-700 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-white text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">HRD Panel</h1>
                        <p class="text-gray-600 text-xs">Sistem Absensi</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="hidden md:flex items-center gap-3 bg-gray-100 px-4 py-2 rounded-full">
                        <div class="w-8 h-8 bg-gradient-to-r from-purple-400 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                            <?php echo strtoupper(substr($user['nama'], 0, 1)); ?>
                        </div>
                        <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($user['nama']); ?></span>
                    </div>
                    <button onclick="window.location.href='/auth/logout.php'" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium rounded-lg hover:bg-gray-100">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Dashboard HRD</h1>
            <p class="text-gray-600">Monitor kehadiran dan data karyawan</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Employees -->
            <div class="glass-card rounded-2xl p-6 shadow-lg border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm mb-1">Total Karyawan</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo $total_employees; ?></p>
                    </div>
                    <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Present Today -->
            <div class="glass-card rounded-2xl p-6 shadow-lg border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm mb-1">Hadir Hari Ini</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $today_present; ?></p>
                    </div>
                    <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Absent Today -->
            <div class="glass-card rounded-2xl p-6 shadow-lg border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm mb-1">Tidak Hadir</p>
                        <p class="text-3xl font-bold text-red-600"><?php echo $today_absent; ?></p>
                    </div>
                    <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-times-circle text-red-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Presence Rate -->
            <div class="glass-card rounded-2xl p-6 shadow-lg border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm mb-1">Tingkat Kehadiran</p>
                        <p class="text-3xl font-bold text-purple-600"><?php echo $rate; ?>%</p>
                    </div>
                    <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-chart-pie text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Today's Attendance Table -->
            <div class="lg:col-span-2">
                <div class="glass-card rounded-2xl shadow-lg border-transparent overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-purple-800 text-white p-6 flex items-center gap-3">
                        <i class="fas fa-list-check text-lg"></i>
                        <div>
                            <h2 class="text-lg font-bold">Absensi Hari Ini</h2>
                            <p class="text-purple-100 text-sm"><?php echo date('l, d F Y'); ?></p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600">Nama</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600">Masuk</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600">Pulang</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_attendance)): ?>
                                    <?php foreach ($recent_attendance as $record): ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="px-6 py-4 text-sm font-medium"><?php echo htmlspecialchars($record['nama']); ?></td>
                                            <td class="px-6 py-4 text-sm">
                                                <i class="fas fa-sign-in-alt text-blue-600 mr-2"></i>
                                                <?php echo $record['jam_masuk'] ? date('H:i', strtotime($record['jam_masuk'])) : '-'; ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <i class="fas fa-sign-out-alt text-green-600 mr-2"></i>
                                                <?php echo $record['jam_pulang'] ? date('H:i', strtotime($record['jam_pulang'])) : '-'; ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo match($record['status']) {
                                                    'belum_absen' => 'bg-red-100 text-red-800',
                                                    'absen_masuk' => 'bg-yellow-100 text-yellow-800',
                                                    'selesai' => 'bg-green-100 text-green-800',
                                                    default => 'bg-gray-100'
                                                }; ?>">
                                                    <?php echo match($record['status']) {
                                                        'belum_absen' => 'Belum Absen',
                                                        'absen_masuk' => 'Masuk',
                                                        'selesai' => 'Selesai',
                                                        default => $record['status']
                                                    }; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                            <i class="fas fa-inbox text-2xl mb-2"></i>
                                            <p>Tidak ada data absensi hari ini</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t bg-gray-50">
                        <a href="/hrd/laporan.php" class="text-purple-600 hover:text-purple-800 text-sm font-semibold flex items-center gap-2">
                            <i class="fas fa-arrow-right"></i> Lihat Laporan Lengkap
                        </a>
                    </div>
                </div>
            </div>

            <!-- Sidebar Menu -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="glass-card rounded-2xl p-6 shadow-lg border-transparent">
                    <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                        <i class="fas fa-bars text-purple-600"></i> Menu HRD
                    </h3>
                    <div class="space-y-3">
                        <a href="/hrd/laporan.php" class="block p-4 bg-gradient-to-r from-purple-50 to-purple-100 hover:from-purple-100 hover:to-purple-200 rounded-xl transition border border-purple-200">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-chart-bar text-purple-600 text-xl"></i>
                                <div>
                                    <p class="font-semibold text-purple-900 text-sm">Laporan</p>
                                    <p class="text-xs text-purple-700">Absensi & Gaji Karyawan</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="glass-card rounded-2xl p-6 shadow-lg border-l-4 border-cyan-500 bg-gradient-to-br from-cyan-50 to-cyan-100">
                    <h4 class="font-bold text-cyan-900 mb-3 flex items-center gap-2">
                        <i class="fas fa-info-circle text-cyan-600"></i> Ringkasan
                    </h4>
                    <ul class="text-sm text-cyan-900 space-y-2">
                        <li class="flex items-center gap-2">
                            <i class="fas fa-check text-cyan-600"></i>
                            <span><?php echo $total_employees; ?> karyawan aktif</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fas fa-check text-cyan-600"></i>
                            <span><?php echo $today_present; ?> hadir hari ini</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fas fa-check text-cyan-600"></i>
                            <span>Kehadiran <?php echo $rate; ?>%</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</body>
</html>


