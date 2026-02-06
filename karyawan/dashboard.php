<?php
/**
 * ========================================
 * EMPLOYEE DASHBOARD
 * ========================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';

redirectIfUnauthorized('karyawan');

$user = getCurrentUser();
$user_id = $user['id'];
$today = date('Y-m-d');

// Get today's attendance
$query = "SELECT * FROM absensi WHERE user_id = ? AND tanggal = ?";
$today_attendance = getRow($conn, $query, [$user_id, $today], 'is');

// Get this month statistics
$first_day = date('Y-m-01');
$last_day = date('Y-m-t');

$query = "SELECT 
    COUNT(*) as total_hari,
    SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as hari_kerja,
    SUM(CASE WHEN status = 'belum_absen' THEN 1 ELSE 0 END) as hari_tidak_absen,
    ROUND(SUM(COALESCE(total_jam, 0)), 2) as total_jam_bulan,
    ROUND(SUM(COALESCE(jam_lembur, 0)), 2) as total_lembur_bulan
    FROM absensi 
    WHERE user_id = ? AND tanggal BETWEEN ? AND ?";

$stats = getRow($conn, $query, [$user_id, $first_day, $last_day], 'iss');

// Get recent attendance records
$query = "SELECT * FROM absensi WHERE user_id = ? ORDER BY tanggal DESC LIMIT 10";
$recent_records = getRows($conn, $query, [$user_id], 'i');

// Calculate estimated salary
$gaji_harian = $user['gaji_harian'];
$tarif_lembur = $user['tarif_lembur'];
$total_salary = 0;

foreach ($recent_records as $record) {
    if ($record['status'] === 'selesai') {
        $salary = calculateDailySalary($gaji_harian, $record['jam_lembur'] ?? 0, $tarif_lembur);
        $total_salary += $salary;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - Sistem Absensi</title>
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
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
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
    <div class="max-w-md md:max-w-6xl mx-auto px-4 pt-4 md:pt-8 pb-24 md:pb-8">
        <!-- Welcome Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Halo, <?php echo explode(' ', $user['nama'])[0]; ?>! 👋</h1>
                    <p class="text-gray-600 text-sm md:text-base"><?php echo date('l, d F Y'); ?></p>
                </div>
            </div>
        </div>

        <!-- Stats Cards - Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 mb-6">
            <!-- Hari Kerja -->
            <div class="stat-card rounded-2xl p-4 md:p-6 border border-transparent hover:border-blue-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-xs md:text-sm mb-1">Hari Kerja</p>
                        <p class="text-2xl md:text-3xl font-bold text-blue-600"><?php echo $stats['hari_kerja'] ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 md:w-14 md:h-14 bg-blue-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Tidak Absen -->
            <div class="stat-card rounded-2xl p-4 md:p-6 border border-transparent hover:border-red-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-xs md:text-sm mb-1">Tidak Absen</p>
                        <p class="text-2xl md:text-3xl font-bold text-red-600"><?php echo $stats['hari_tidak_absen'] ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 md:w-14 md:h-14 bg-red-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Jam -->
            <div class="stat-card rounded-2xl p-4 md:p-6 border border-transparent hover:border-green-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-xs md:text-sm mb-1">Total Jam</p>
                        <p class="text-2xl md:text-3xl font-bold text-green-600"><?php echo $stats['total_jam_bulan'] ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 md:w-14 md:h-14 bg-green-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-hourglass-end text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Lembur -->
            <div class="stat-card rounded-2xl p-4 md:p-6 border border-transparent hover:border-orange-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-xs md:text-sm mb-1">Total Lembur</p>
                        <p class="text-2xl md:text-3xl font-bold text-orange-600"><?php echo $stats['total_lembur_bulan'] ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 md:w-14 md:h-14 bg-orange-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-fire text-orange-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Status Hari Ini -->
            <div class="glass-card rounded-2xl p-6 shadow-lg border border-transparent">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center">
                        <i class="fas fa-clock text-blue-600 mr-2"></i> Status Hari Ini
                    </h3>
                </div>
                <div class="space-y-3">
                        <?php if ($today_attendance): ?>
                            <div class="flex items-center justify-between p-3 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-sign-in-alt text-blue-600"></i>
                                    <span class="text-gray-700 text-sm">Jam Masuk</span>
                                </div>
                                <span class="font-bold text-blue-600"><?php echo $today_attendance['jam_masuk'] ? date('H:i', strtotime($today_attendance['jam_masuk'])) : '--:--'; ?></span>
                            </div>

                            <div class="flex items-center justify-between p-3 bg-gradient-to-r from-green-50 to-green-100 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-sign-out-alt text-green-600"></i>
                                    <span class="text-gray-700 text-sm">Jam Pulang</span>
                                </div>
                                <span class="font-bold text-green-600"><?php echo $today_attendance['jam_pulang'] ? date('H:i', strtotime($today_attendance['jam_pulang'])) : '--:--'; ?></span>
                            </div>

                            <div class="flex items-center justify-between p-3 bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-hourglass-half text-purple-600"></i>
                                    <span class="text-gray-700 text-sm">Total Jam</span>
                                </div>
                                <span class="font-bold text-purple-600"><?php echo $today_attendance['total_jam'] ?? '-'; ?></span>
                            </div>

                            <div class="flex items-center justify-between p-3 bg-gradient-to-r from-orange-50 to-orange-100 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-fire text-orange-600"></i>
                                    <span class="text-gray-700 text-sm">Lembur</span>
                                </div>
                                <span class="font-bold text-orange-600"><?php echo $today_attendance['jam_lembur'] ?? 0; ?></span>
                            </div>

                            <div class="p-3 rounded-lg mt-4 <?php echo match($today_attendance['status']) {
                                'belum_absen' => 'bg-red-100 text-red-700',
                                'absen_masuk' => 'bg-yellow-100 text-yellow-700',
                                'absen_pulang' => 'bg-blue-100 text-blue-700',
                                'selesai' => 'bg-green-100 text-green-700',
                                default => 'bg-gray-100 text-gray-700'
                            }; ?>">
                                <div class="flex items-center justify-center gap-2">
                                    <i class="fas fa-info-circle"></i>
                                    <p class="font-semibold text-center">
                                        <?php echo match($today_attendance['status']) {
                                            'belum_absen' => '⚠️ Belum Absen',
                                            'absen_masuk' => '✓ Sudah Masuk',
                                            'absen_pulang' => '✓ Sudah Pulang',
                                            'selesai' => '✓ Selesai',
                                            default => 'Status Tidak Jelas'
                                        }; ?>
                                    </p>
                                </div>
                            </div>

                            <a href="/karyawan/absensi.php" class="block w-full mt-4 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white py-3 px-4 rounded-xl font-semibold text-center transition flex items-center justify-center gap-2">
                                <i class="fas fa-camera"></i> Buka Absensi
                            </a>
                        <?php else: ?>
                            <div class="text-center py-6">
                                <i class="fas fa-inbox text-gray-400 text-3xl mb-2"></i>
                                <p class="text-gray-600 mb-4">Belum ada data hari ini</p>
                                <a href="/karyawan/absensi.php" class="inline-block bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white py-2 px-6 rounded-xl font-semibold transition flex items-center justify-center gap-2">
                                    <i class="fas fa-camera"></i> Mulai Absensi
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
            </div>

            <!-- Recent Records -->
            <div class="lg:col-span-2">
                <div class="glass-card rounded-2xl shadow-lg border border-transparent overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-purple-800 text-white p-6 flex items-center gap-3">
                        <i class="fas fa-history text-lg"></i>
                        <h2 class="text-lg font-bold">Riwayat Terbaru</h2>
                    </div>

                    <div class="p-6">
                        <?php if (!empty($recent_records)): ?>
                            <div class="space-y-2">
                                <?php foreach ($recent_records as $index => $record): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                        <div class="flex-1">
                                            <p class="text-sm font-semibold text-gray-800"><?php echo formatDateIndonesian($record['tanggal']); ?></p>
                                            <p class="text-xs text-gray-600">
                                                <?php echo $record['jam_masuk'] ? date('H:i', strtotime($record['jam_masuk'])) . ' - ' . ($record['jam_pulang'] ? date('H:i', strtotime($record['jam_pulang'])) : 'Belum pulang') : 'Belum masuk'; ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-bold text-gray-800"><?php echo $record['total_jam'] ?? '-'; ?>h</p>
                                            <span class="text-xs px-2 py-1 rounded-full <?php echo match($record['status']) {
                                                'belum_absen' => 'bg-red-100 text-red-800',
                                                'absen_masuk' => 'bg-yellow-100 text-yellow-800',
                                                'absen_pulang' => 'bg-blue-100 text-blue-800',
                                                'selesai' => 'bg-green-100 text-green-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            }; ?>">
                                                <?php echo match($record['status']) {
                                                    'belum_absen' => 'Belum Absen',
                                                    'absen_masuk' => 'Masuk',
                                                    'absen_pulang' => 'Pulang',
                                                    'selesai' => 'Selesai',
                                                    default => 'N/A'
                                                }; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-inbox text-gray-400 text-2xl mb-2"></i>
                                <p class="text-gray-500 text-sm">Belum ada riwayat absensi</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                        <a href="/karyawan/riwayat.php" class="text-blue-600 hover:text-blue-800 text-sm font-semibold flex items-center gap-2">
                            <i class="fas fa-arrow-right"></i> Lihat Semua Riwayat
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Salary Info -->
        <div class="glass-card rounded-2xl p-6 shadow-lg border border-transparent">
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-money-bill-wave text-green-600"></i> Informasi Gaji
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border border-blue-200">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-coins text-blue-600"></i>
                        <p class="text-gray-600 text-sm">Gaji Harian</p>
                    </div>
                    <p class="text-2xl font-bold text-blue-600"><?php echo formatCurrency($gaji_harian); ?></p>
                </div>
                <div class="p-4 bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl border border-purple-200">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-fire text-purple-600"></i>
                        <p class="text-gray-600 text-sm">Tarif Lembur</p>
                    </div>
                    <p class="text-2xl font-bold text-purple-600"><?php echo formatCurrency($tarif_lembur); ?>/jam</p>
                </div>
                <div class="p-4 bg-gradient-to-br from-green-50 to-green-100 rounded-xl border border-green-200">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-chart-line text-green-600"></i>
                        <p class="text-gray-600 text-sm">Estimasi Bulan Ini</p>
                    </div>
                    <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($total_salary); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation - Mobile Only -->
    <div class="bottom-nav fixed bottom-0 left-0 right-0 md:hidden z-50">
        <div class="flex justify-around items-center h-full">
            <a href="/karyawan/dashboard.php" class="nav-item active flex flex-col items-center justify-center text-blue-600">
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
            <a href="/karyawan/profil.php" class="nav-item flex flex-col items-center justify-center text-gray-500">
                <i class="fas fa-user text-xl mb-1"></i>
                <span class="text-xs">Profil</span>
            </a>
            <a href="/auth/logout.php" class="nav-item flex flex-col items-center justify-center text-gray-500">
                <i class="fas fa-sign-out-alt text-xl mb-1"></i>
                <span class="text-xs">Keluar</span>
            </a>
        </div>
    </div>


