<?php
/**
 * ========================================
 * ADMIN - ATTENDANCE & SALARY REPORTS
 * ========================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';

redirectIfUnauthorized('admin');

$user = getCurrentUser();

// Get filter parameters
$filter_month = $_GET['month'] ?? date('n');
$filter_year = $_GET['year'] ?? date('Y');
$filter_user = $_GET['user'] ?? '';

// Generate date range
$first_day = sprintf('%04d-%02d-01', $filter_year, $filter_month);
$last_day = date('Y-m-t', strtotime($first_day));

// Get employees list for filter
$query = "SELECT id, nama FROM users WHERE role = 'karyawan' ORDER BY nama";
$employees = getRows($conn, $query);

// Build query for report
$query = "SELECT a.*, u.nama, u.gaji_harian, u.tarif_lembur_per_jam
          FROM absensi a 
          JOIN users u ON a.user_id = u.id 
          WHERE a.tanggal BETWEEN ? AND ? 
          AND u.role = 'karyawan'";

$params = [$first_day, $last_day];
$types = 'ss';

if (!empty($filter_user)) {
    $query .= " AND a.user_id = ?";
    $params[] = (int) $filter_user;
    $types .= 'i';
}

$query .= " ORDER BY a.tanggal DESC, u.nama";
$report_data = getRows($conn, $query, $params, $types);

// Calculate summary
$summary = [
    'total_hari_kerja' => 0,
    'total_jam_kerja' => 0,
    'total_lembur' => 0,
    'total_gaji' => 0,
    'total_lembur_bayar' => 0,
    'employee_count' => 0
];

$employee_summary = [];

foreach ($report_data as $record) {
    $emp_id = $record['user_id'];
    
    if ($record['status'] === 'selesai') {
        // Initialize employee summary if not exists
        if (!isset($employee_summary[$emp_id])) {
            $employee_summary[$emp_id] = [
                'nama' => $record['nama'],
                'gaji_harian' => $record['gaji_harian'],
                'tarif_lembur' => $record['tarif_lembur_per_jam'],
                'hari_kerja' => 0,
                'total_jam' => 0,
                'total_lembur' => 0,
                'total_gaji' => 0,
                'total_lembur_bayar' => 0
            ];
        }
        
        $employee_summary[$emp_id]['hari_kerja']++;
        $employee_summary[$emp_id]['total_jam'] += $record['total_jam'] ?? 0;
        $employee_summary[$emp_id]['total_lembur'] += $record['jam_lembur'] ?? 0;
        
        // Calculate daily salary
        $daily_salary = calculateDailySalary(
            $record['gaji_harian'],
            $record['jam_lembur'] ?? 0,
            $record['tarif_lembur_per_jam']
        );
        
        $employee_summary[$emp_id]['total_gaji'] += $daily_salary;
        $employee_summary[$emp_id]['total_lembur_bayar'] += ($record['jam_lembur'] ?? 0) * $record['tarif_lembur_per_jam'];
    }
}

// Calculate totals
foreach ($employee_summary as $emp_data) {
    $summary['employee_count']++;
    $summary['total_hari_kerja'] += $emp_data['hari_kerja'];
    $summary['total_jam_kerja'] += $emp_data['total_jam'];
    $summary['total_lembur'] += $emp_data['total_lembur'];
    $summary['total_gaji'] += $emp_data['total_gaji'];
    $summary['total_lembur_bayar'] += $emp_data['total_lembur_bayar'];
}

// Month names
$months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
           'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Absensi & Gaji - Sistem Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <!-- Header / Navigation -->
    <nav class="bg-gradient-to-r from-red-600 to-red-800 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-white">Laporan Absensi & Gaji</h1>
                <div class="flex items-center gap-6 text-white">
                    <a href="/admin/dashboard.php" class="hover:text-red-200">Dashboard</a>
                    <a href="/admin/users.php" class="hover:text-red-200">Kelola User</a>
                    <a href="/admin/setting.php" class="hover:text-red-200">Pengaturan</a>
                    <a href="/auth/logout.php" class="text-red-200 hover:text-red-100">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto mt-8 px-4 mb-8">
        <!-- Filter Section -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-bold mb-4">Filter Laporan</h2>
            <form method="GET" class="flex gap-4 items-end flex-wrap">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Bulan</label>
                    <select name="month" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $filter_month ? 'selected' : ''; ?>>
                                <?php echo $months[$i]; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tahun</label>
                    <select name="year" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $filter_year ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Karyawan</label>
                    <select name="user" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Semua Karyawan --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo $emp['id'] == $filter_user ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['nama']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold">
                    Tampilkan
                </button>
                <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" class="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2 rounded-lg font-semibold">
                    Reset
                </a>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-gray-600 text-sm">Jumlah Karyawan</p>
                <p class="text-3xl font-bold text-blue-600"><?php echo $summary['employee_count']; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-gray-600 text-sm">Total Hari Kerja</p>
                <p class="text-3xl font-bold text-green-600"><?php echo $summary['total_hari_kerja']; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-gray-600 text-sm">Total Jam Kerja</p>
                <p class="text-3xl font-bold text-purple-600"><?php echo round($summary['total_jam_kerja'], 1); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-gray-600 text-sm">Total Lembur (jam)</p>
                <p class="text-3xl font-bold text-orange-600"><?php echo round($summary['total_lembur'], 1); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-gray-600 text-sm">Total Penggajian</p>
                <p class="text-2xl font-bold text-red-600"><?php echo formatCurrency($summary['total_gaji']); ?></p>
            </div>
        </div>

        <!-- Export Buttons -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6 p-6">
            <h3 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-download mr-2 text-green-600"></i> Export Data
            </h3>
            <div class="flex flex-wrap gap-3">
                <a href="/admin/export_csv.php?month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&user=<?php echo $filter_user; ?>" 
                   class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-6 py-2 rounded-lg font-semibold flex items-center">
                    <i class="fas fa-file-excel mr-2"></i> Export Excel (CSV)
                </a>
                <a href="/admin/export_pdf.php?month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&user=<?php echo $filter_user; ?>" 
                   class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-6 py-2 rounded-lg font-semibold flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> Export PDF
                </a>
                <a href="/admin/export_payroll_pdf.php?month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&user=<?php echo $filter_user; ?>" 
                   class="bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white px-6 py-2 rounded-lg font-semibold flex items-center">
                    <i class="fas fa-file-invoice-dollar mr-2"></i> Export Payroll PDF
                </a>
            </div>
        </div>

        <!-- Employee Summary Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
            <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-800 text-white flex items-center justify-between">
                <h3 class="text-lg font-bold">Ringkasan Gaji Karyawan - <?php echo $months[$filter_month]; ?> <?php echo $filter_year; ?></h3>
                <i class="fas fa-file-alt text-xl opacity-50"></i>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold">No.</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Nama Karyawan</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Hari Kerja</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Total Jam</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Lembur</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Gaji Pokok</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Tunjangan Lembur</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Total Gaji</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($employee_summary)): ?>
                            <?php $no = 1; foreach ($employee_summary as $emp_data): ?>
                                <?php 
                                $gaji_pokok = $emp_data['hari_kerja'] * $emp_data['gaji_harian'];
                                ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-semibold"><?php echo $no++; ?></td>
                                    <td class="px-6 py-4 text-sm font-semibold"><?php echo htmlspecialchars($emp_data['nama']); ?></td>
                                    <td class="px-6 py-4 text-sm text-center"><?php echo $emp_data['hari_kerja']; ?></td>
                                    <td class="px-6 py-4 text-sm text-center"><?php echo round($emp_data['total_jam'], 1); ?></td>
                                    <td class="px-6 py-4 text-sm text-center text-orange-600 font-semibold"><?php echo round($emp_data['total_lembur'], 1); ?></td>
                                    <td class="px-6 py-4 text-sm text-right"><?php echo formatCurrency($gaji_pokok); ?></td>
                                    <td class="px-6 py-4 text-sm text-right"><?php echo formatCurrency($emp_data['total_lembur_bayar']); ?></td>
                                    <td class="px-6 py-4 text-sm text-right font-bold text-green-600"><?php echo formatCurrency($emp_data['total_gaji']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bg-gray-100 font-bold">
                                <td colspan="2" class="px-6 py-4">TOTAL</td>
                                <td class="px-6 py-4 text-center border-l"><?php echo $summary['total_hari_kerja']; ?></td>
                                <td class="px-6 py-4 text-center"><?php echo round($summary['total_jam_kerja'], 1); ?></td>
                                <td class="px-6 py-4 text-center text-orange-600"><?php echo round($summary['total_lembur'], 1); ?></td>
                                <td class="px-6 py-4 text-right"><?php echo formatCurrency($summary['total_gaji'] - $summary['total_lembur_bayar']); ?></td>
                                <td class="px-6 py-4 text-right"><?php echo formatCurrency($summary['total_lembur_bayar']); ?></td>
                                <td class="px-6 py-4 text-right text-green-600 border-l"><?php echo formatCurrency($summary['total_gaji']); ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    Tidak ada data untuk periode ini
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Detailed Attendance Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-purple-600 to-purple-800 text-white">
                <h3 class="text-lg font-bold">Detail Absensi Harian</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
                            <th class="px-4 py-3 text-left font-semibold">Nama Karyawan</th>
                            <th class="px-4 py-3 text-left font-semibold">Jam Masuk</th>
                            <th class="px-4 py-3 text-left font-semibold">Jam Pulang</th>
                            <th class="px-4 py-3 text-left font-semibold">Total Jam</th>
                            <th class="px-4 py-3 text-left font-semibold">Lembur</th>
                            <th class="px-4 py-3 text-left font-semibold">Gaji Harian</th>
                            <th class="px-4 py-3 text-left font-semibold">Tunjangan Lembur</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($report_data)): ?>
                            <?php foreach ($report_data as $record): ?>
                                <?php 
                                if ($record['status'] !== 'selesai') continue;
                                
                                $daily_salary = calculateDailySalary(
                                    $record['gaji_harian'],
                                    $record['jam_lembur'] ?? 0,
                                    $record['tarif_lembur_per_jam']
                                );
                                $lembur_bayar = ($record['jam_lembur'] ?? 0) * $record['tarif_lembur_per_jam'];
                                ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-3"><?php echo formatDateIndonesian($record['tanggal']); ?></td>
                                    <td class="px-4 py-3 font-semibold"><?php echo htmlspecialchars($record['nama']); ?></td>
                                    <td class="px-4 py-3"><?php echo $record['jam_masuk'] ? date('H:i', strtotime($record['jam_masuk'])) : '-'; ?></td>
                                    <td class="px-4 py-3"><?php echo $record['jam_pulang'] ? date('H:i', strtotime($record['jam_pulang'])) : '-'; ?></td>
                                    <td class="px-4 py-3 text-center"><?php echo $record['total_jam'] ?? 0; ?></td>
                                    <td class="px-4 py-3 text-center text-orange-600 font-semibold"><?php echo $record['jam_lembur'] ?? 0; ?></td>
                                    <td class="px-4 py-3 text-right"><?php echo formatCurrency($record['gaji_harian']); ?></td>
                                    <td class="px-4 py-3 text-right"><?php echo formatCurrency($lembur_bayar); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 rounded text-xs font-semibold bg-green-100 text-green-800">Selesai</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                                    Tidak ada data absensi untuk periode ini
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t">
                <p class="text-sm text-gray-600">💡 Menampilkan hanya data absensi dengan status "Selesai"</p>
            </div>
        </div>
    </div>
</body>
</html>
