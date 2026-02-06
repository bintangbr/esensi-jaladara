<?php
/**
 * ========================================
 * HRD - ATTENDANCE & SALARY REPORTS
 * ========================================
 * Read-only reports for HRD
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';

redirectIfUnauthorized('hrd');

// Get filter parameters
$filter_month = $_GET['month'] ?? date('n');
$filter_year = $_GET['year'] ?? date('Y');
$filter_user = $_GET['user'] ?? '';
$filter_type = $_GET['type'] ?? 'absensi'; // absensi or gaji

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
        
        $daily_salary = calculateDailySalary(
            $record['gaji_harian'],
            $record['jam_lembur'] ?? 0,
            $record['tarif_lembur_per_jam']
        );
        
        $employee_summary[$emp_id]['total_gaji'] += $daily_salary;
        $employee_summary[$emp_id]['total_lembur_bayar'] += ($record['jam_lembur'] ?? 0) * $record['tarif_lembur_per_jam'];
    }
}

foreach ($employee_summary as $emp_data) {
    $summary['employee_count']++;
    $summary['total_hari_kerja'] += $emp_data['hari_kerja'];
    $summary['total_jam_kerja'] += $emp_data['total_jam'];
    $summary['total_lembur'] += $emp_data['total_lembur'];
    $summary['total_gaji'] += $emp_data['total_gaji'];
    $summary['total_lembur_bayar'] += $emp_data['total_lembur_bayar'];
}

$months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
           'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Sistem Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <!-- Header / Navigation -->
    <nav class="bg-gradient-to-r from-purple-600 to-purple-800 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-white">Laporan Absensi & Gaji</h1>
                <div class="flex items-center gap-6 text-white">
                    <a href="/hrd/dashboard.php" class="hover:text-purple-200">Dashboard</a>
                    <a href="/auth/logout.php" class="text-purple-200 hover:text-purple-100">Logout</a>
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
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tipe Laporan</label>
                    <select name="type" onchange="this.form.submit()" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="absensi" <?php echo $filter_type === 'absensi' ? 'selected' : ''; ?>>Laporan Absensi</option>
                        <option value="gaji" <?php echo $filter_type === 'gaji' ? 'selected' : ''; ?>>Laporan Gaji</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Bulan</label>
                    <select name="month" class="px-4 py-2 border border-gray-300 rounded-lg">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $filter_month ? 'selected' : ''; ?>>
                                <?php echo $months[$i]; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tahun</label>
                    <select name="year" class="px-4 py-2 border border-gray-300 rounded-lg">
                        <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $filter_year ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Karyawan</label>
                    <select name="user" class="px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="">-- Semua --</option>
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
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-gray-600 text-sm">Karyawan</p>
                <p class="text-3xl font-bold text-blue-600"><?php echo $summary['employee_count']; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-gray-600 text-sm">Hari Kerja</p>
                <p class="text-3xl font-bold text-green-600"><?php echo $summary['total_hari_kerja']; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-gray-600 text-sm">Jam Kerja</p>
                <p class="text-3xl font-bold text-purple-600"><?php echo round($summary['total_jam_kerja'], 1); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-gray-600 text-sm">Lembur</p>
                <p class="text-3xl font-bold text-orange-600"><?php echo round($summary['total_lembur'], 1); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-gray-600 text-sm">Penggajian</p>
                <p class="text-2xl font-bold text-red-600"><?php echo formatCurrency($summary['total_gaji']); ?></p>
            </div>
        </div>

        <!-- Report Table -->
        <?php if ($filter_type === 'gaji'): ?>
            <!-- Salary Report -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-green-600 to-green-800 text-white">
                    <h3 class="text-lg font-bold">Laporan Gaji - <?php echo $months[$filter_month]; ?> <?php echo $filter_year; ?></h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold">No.</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold">Nama Karyawan</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold">Hari</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold">Jam</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold">Lembur</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold">Gaji Pokok</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold">Tunjangan</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold">Total Gaji</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($employee_summary)): ?>
                                <?php $no = 1; foreach ($employee_summary as $emp_data): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm font-semibold"><?php echo $no++; ?></td>
                                        <td class="px-6 py-4 text-sm font-semibold"><?php echo htmlspecialchars($emp_data['nama']); ?></td>
                                        <td class="px-6 py-4 text-sm text-center"><?php echo $emp_data['hari_kerja']; ?></td>
                                        <td class="px-6 py-4 text-sm text-center"><?php echo round($emp_data['total_jam'], 1); ?></td>
                                        <td class="px-6 py-4 text-sm text-center text-orange-600"><?php echo round($emp_data['total_lembur'], 1); ?></td>
                                        <td class="px-6 py-4 text-sm text-right"><?php echo formatCurrency($emp_data['hari_kerja'] * $emp_data['gaji_harian']); ?></td>
                                        <td class="px-6 py-4 text-sm text-right"><?php echo formatCurrency($emp_data['total_lembur_bayar']); ?></td>
                                        <td class="px-6 py-4 text-sm text-right font-bold text-green-600"><?php echo formatCurrency($emp_data['total_gaji']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-gray-500">Tidak ada data</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <!-- Attendance Report -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
                    <h3 class="text-lg font-bold">Laporan Absensi - <?php echo $months[$filter_month]; ?> <?php echo $filter_year; ?></h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
                                <th class="px-4 py-3 text-left font-semibold">Nama</th>
                                <th class="px-4 py-3 text-left font-semibold">Masuk</th>
                                <th class="px-4 py-3 text-left font-semibold">Pulang</th>
                                <th class="px-4 py-3 text-left font-semibold">Jam</th>
                                <th class="px-4 py-3 text-left font-semibold">Lembur</th>
                                <th class="px-4 py-3 text-left font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report_data)): ?>
                                <?php foreach ($report_data as $record): ?>
                                    <?php if ($record['status'] !== 'selesai') continue; ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-4 py-3"><?php echo formatDateIndonesian($record['tanggal']); ?></td>
                                        <td class="px-4 py-3 font-semibold"><?php echo htmlspecialchars($record['nama']); ?></td>
                                        <td class="px-4 py-3"><?php echo $record['jam_masuk'] ? date('H:i', strtotime($record['jam_masuk'])) : '-'; ?></td>
                                        <td class="px-4 py-3"><?php echo $record['jam_pulang'] ? date('H:i', strtotime($record['jam_pulang'])) : '-'; ?></td>
                                        <td class="px-4 py-3 text-center"><?php echo $record['total_jam'] ?? 0; ?></td>
                                        <td class="px-4 py-3 text-center text-orange-600 font-semibold"><?php echo $record['jam_lembur'] ?? 0; ?></td>
                                        <td class="px-4 py-3"><span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded font-semibold">Selesai</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">Tidak ada data absensi</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
