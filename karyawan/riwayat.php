<?php
/**
 * ========================================
 * ATTENDANCE HISTORY - EMPLOYEE
 * ========================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';

redirectIfUnauthorized('karyawan');

$user = getCurrentUser();
$user_id = $user['id'];

// Get filter parameters
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');

// Get attendance records for the month
$first_day = sprintf('%04d-%02d-01', $year, $month);
$last_day = date('Y-m-t', strtotime($first_day));

$query = "SELECT * FROM absensi WHERE user_id = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal DESC";
$records = getRows($conn, $query, [$user_id, $first_day, $last_day], 'iss');

// Calculate totals
$total_jam = 0;
$total_lembur = 0;
$total_selesai = 0;
$total_belum = 0;

foreach ($records as $record) {
    $total_jam += $record['total_jam'] ?? 0;
    $total_lembur += $record['jam_lembur'] ?? 0;
    if ($record['status'] === 'selesai') {
        $total_selesai++;
    } elseif ($record['status'] === 'belum_absen') {
        $total_belum++;
    }
}

// Get salary info
$query = "SELECT gaji_harian, tarif_lembur_per_jam FROM users WHERE id = ?";
$salary_info = getRow($conn, $query, [$user_id], 'i');

// Calculate total salary
$total_salary = ($total_selesai * $salary_info['gaji_harian']) + ($total_lembur * $salary_info['tarif_lembur_per_jam']);

$months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Riwayat Absensi - Sistem Absensi</title>
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
    <div class="max-w-md md:max-w-6xl mx-auto px-4 pt-4 md:pt-8 pb-24 md:pb-8">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Riwayat Absensi</h1>
            <p class="text-gray-600">Lihat detail absensi bulanan Anda</p>
        </div>

        <!-- Filter -->
        <div class="glass-card rounded-2xl shadow-lg p-6 mb-6">
            <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-calendar mr-2"></i>Bulan
                    </label>
                    <select name="month" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $month ? 'selected' : ''; ?>>
                                <?php echo $months[$i - 1]; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-calendar-alt mr-2"></i>Tahun
                    </label>
                    <select name="year" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $year ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="w-full md:w-auto bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-8 py-3 rounded-lg font-semibold transition flex items-center justify-center gap-2">
                    <i class="fas fa-search"></i> Tampilkan
                </button>
            </form>
        </div>

        <!-- Statistics Summary -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="glass-card rounded-2xl shadow-lg p-5 border border-transparent">
                <div class="text-center">
                    <div class="w-12 h-12 mx-auto bg-green-100 rounded-xl flex items-center justify-center mb-2">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <p class="text-gray-600 text-xs mb-1">Hari Kerja</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo $total_selesai; ?></p>
                </div>
            </div>
            <div class="glass-card rounded-2xl shadow-lg p-5 border border-transparent">
                <div class="text-center">
                    <div class="w-12 h-12 mx-auto bg-red-100 rounded-xl flex items-center justify-center mb-2">
                        <i class="fas fa-times-circle text-red-600 text-xl"></i>
                    </div>
                    <p class="text-gray-600 text-xs mb-1">Tidak Absen</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo $total_belum; ?></p>
                </div>
            </div>
            <div class="glass-card rounded-2xl shadow-lg p-5 border border-transparent">
                <div class="text-center">
                    <div class="w-12 h-12 mx-auto bg-blue-100 rounded-xl flex items-center justify-center mb-2">
                        <i class="fas fa-hourglass-end text-blue-600 text-xl"></i>
                    </div>
                    <p class="text-gray-600 text-xs mb-1">Total Jam</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo round($total_jam, 1); ?></p>
                </div>
            </div>
            <div class="glass-card rounded-2xl shadow-lg p-5 border border-transparent">
                <div class="text-center">
                    <div class="w-12 h-12 mx-auto bg-orange-100 rounded-xl flex items-center justify-center mb-2">
                        <i class="fas fa-fire text-orange-600 text-xl"></i>
                    </div>
                    <p class="text-gray-600 text-xs mb-1">Total Lembur</p>
                    <p class="text-2xl font-bold text-orange-600"><?php echo round($total_lembur, 1); ?></p>
                </div>
            </div>
        </div>

        <!-- Salary Calculation -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="glass-card rounded-2xl border border-transparent shadow-lg p-6 bg-gradient-to-br from-blue-50 to-blue-100">
                <div class="flex items-center gap-3 mb-2">
                    <i class="fas fa-coins text-blue-600 text-2xl"></i>
                    <p class="text-gray-700 text-sm font-semibold">Gaji Pokok</p>
                </div>
                <p class="text-xl md:text-2xl font-bold text-blue-600"><?php echo formatCurrency($total_selesai * $salary_info['gaji_harian']); ?></p>
                <p class="text-xs text-gray-600 mt-1"><?php echo $total_selesai; ?> hari × <?php echo formatCurrency($salary_info['gaji_harian']); ?></p>
            </div>
            <div class="glass-card rounded-2xl border border-transparent shadow-lg p-6 bg-gradient-to-br from-purple-50 to-purple-100">
                <div class="flex items-center gap-3 mb-2">
                    <i class="fas fa-fire text-purple-600 text-2xl"></i>
                    <p class="text-gray-700 text-sm font-semibold">Tunjangan Lembur</p>
                </div>
                <p class="text-xl md:text-2xl font-bold text-purple-600"><?php echo formatCurrency($total_lembur * $salary_info['tarif_lembur_per_jam']); ?></p>
                <p class="text-xs text-gray-600 mt-1"><?php echo round($total_lembur, 2); ?> jam × <?php echo formatCurrency($salary_info['tarif_lembur_per_jam']); ?></p>
            </div>
            <div class="glass-card rounded-2xl border border-transparent shadow-lg p-6 bg-gradient-to-br from-green-50 to-green-100">
                <div class="flex items-center gap-3 mb-2">
                    <i class="fas fa-chart-line text-green-600 text-2xl"></i>
                    <p class="text-gray-700 text-sm font-semibold">Total Gaji Bulan Ini</p>
                </div>
                <p class="text-xl md:text-2xl font-bold text-green-600"><?php echo formatCurrency($total_salary); ?></p>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="glass-card rounded-2xl shadow-lg border border-transparent overflow-hidden">
            <div class="bg-gradient-to-r from-purple-600 to-purple-800 text-white p-6 flex items-center gap-3">
                <i class="fas fa-table text-lg"></i>
                <h3 class="text-lg font-bold">Detail Absensi <?php echo $months[$month - 1] ?? ''; ?> <?php echo $year; ?></h3>
            </div>

            <div class="p-6">
                <?php if (!empty($records)): ?>
                    <div class="space-y-3">
                        <?php foreach ($records as $index => $record): ?>
                            <?php 
                            $daily_salary = 0;
                            if ($record['status'] === 'selesai') {
                                $daily_salary = calculateDailySalary(
                                    $salary_info['gaji_harian'],
                                    $record['jam_lembur'] ?? 0,
                                    $salary_info['tarif_lembur_per_jam']
                                );
                            }
                            ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition border-l-4 <?php echo match($record['status']) {
                                'belum_absen' => 'border-red-500',
                                'absen_masuk' => 'border-yellow-500',
                                'absen_pulang' => 'border-blue-500',
                                'selesai' => 'border-green-500',
                                default => 'border-gray-500'
                            }; ?>">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-800"><?php echo formatDateIndonesian($record['tanggal']); ?></p>
                                    <div class="flex gap-3 mt-1 text-xs text-gray-600 flex-wrap">
                                        <?php if ($record['jam_masuk']): ?>
                                            <span><i class="fas fa-sign-in-alt mr-1 text-blue-600"></i><?php echo date('H:i', strtotime($record['jam_masuk'])); ?></span>
                                        <?php endif; ?>
                                        <?php if ($record['jam_pulang']): ?>
                                            <span><i class="fas fa-sign-out-alt mr-1 text-green-600"></i><?php echo date('H:i', strtotime($record['jam_pulang'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="text-right ml-2">
                                    <div class="flex items-center justify-end gap-2 mb-1">
                                        <span class="text-xs px-2 py-1 bg-gray-200 text-gray-800 rounded"><?php echo $record['total_jam'] ?? '-'; ?>h</span>
                                        <?php if ($record['jam_lembur']): ?>
                                            <span class="text-xs px-2 py-1 bg-orange-200 text-orange-800 rounded"><?php echo round($record['jam_lembur'], 1); ?>h</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm font-bold text-gray-800"><?php echo formatCurrency($daily_salary); ?></p>
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
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-gray-400 text-4xl mb-3"></i>
                        <p class="text-gray-500">Tidak ada data absensi untuk bulan ini</p>
                    </div>
                <?php endif; ?>
            </div>
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
            <a href="/karyawan/riwayat.php" class="nav-item active flex flex-col items-center justify-center text-blue-600">
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

</body>
</html>
