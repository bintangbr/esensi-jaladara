<?php
/**
 * ========================================
 * EXPORT DATA TO CSV
 * ========================================
 * Export attendance dan employee data
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';

redirectIfUnauthorized('admin');

// Get filter parameters
$filter_month = $_GET['month'] ?? date('n');
$filter_year = $_GET['year'] ?? date('Y');
$filter_user = $_GET['user'] ?? '';

// Generate date range
$first_day = sprintf('%04d-%02d-01', $filter_year, $filter_month);
$last_day = date('Y-m-t', strtotime($first_day));

// Get data
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

// Prepare CSV data
$filename = "Laporan_Absensi_" . $filter_month . "_" . $filter_year . "_" . date('YmdHis') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// BOM for UTF-8 encoding (agar Excel bisa baca karakter Indonesia)
echo "\xEF\xBB\xBF";

// Header row
$headers = [
    'Tanggal',
    'Nama Karyawan',
    'Jam Masuk',
    'Jam Pulang',
    'Total Jam',
    'Jam Lembur',
    'Gaji Harian',
    'Tunjangan Lembur',
    'Total Gaji Harian',
    'Status'
];

// Output headers
echo implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\n";

// Output data rows
foreach ($report_data as $record) {
    if ($record['status'] === 'selesai') {
        $daily_salary = calculateDailySalary(
            $record['gaji_harian'],
            $record['jam_lembur'] ?? 0,
            $record['tarif_lembur_per_jam']
        );
        $lembur_bayar = ($record['jam_lembur'] ?? 0) * $record['tarif_lembur_per_jam'];
        
        $row = [
            date('d/m/Y', strtotime($record['tanggal'])),
            $record['nama'],
            $record['jam_masuk'] ? date('H:i', strtotime($record['jam_masuk'])) : '-',
            $record['jam_pulang'] ? date('H:i', strtotime($record['jam_pulang'])) : '-',
            $record['total_jam'] ?? '0',
            $record['jam_lembur'] ?? '0',
            $record['gaji_harian'],
            $lembur_bayar,
            $daily_salary,
            ucfirst(str_replace('_', ' ', $record['status']))
        ];
        
        echo implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
    }
}

exit;