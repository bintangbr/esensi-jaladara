<?php
/**
 * ========================================
 * EXPORT DATA TO PDF (HTML Printable)
 * ========================================
 * Generate laporan yang bisa di-print ke PDF
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

// Get company settings
$company_name = getSetting($conn, 'nama_perusahaan', 'PT. Aplikasi Absensi');
$company_address = getSetting($conn, 'alamat_kantor', 'Jakarta, Indonesia');

// Month names
$months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
           'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Calculate totals
$summary = [
    'total_hari_kerja' => 0,
    'total_jam_kerja' => 0,
    'total_lembur' => 0,
    'total_gaji' => 0,
    'total_lembur_bayar' => 0
];

$employee_summary = [];

foreach ($report_data as $record) {
    if ($record['status'] === 'selesai') {
        $emp_id = $record['user_id'];
        
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

// Calculate totals
foreach ($employee_summary as $emp_data) {
    $summary['total_hari_kerja'] += $emp_data['hari_kerja'];
    $summary['total_jam_kerja'] += $emp_data['total_jam'];
    $summary['total_lembur'] += $emp_data['total_lembur'];
    $summary['total_gaji'] += $emp_data['total_gaji'];
    $summary['total_lembur_bayar'] += $emp_data['total_lembur_bayar'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Absensi - <?php echo $months[$filter_month]; ?> <?php echo $filter_year; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
            color: #000;
        }
        
        .header p {
            font-size: 12px;
            color: #666;
        }
        
        .info {
            margin-bottom: 25px;
            font-size: 12px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        thead {
            background-color: #f3f4f6;
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
        }
        
        th {
            padding: 10px;
            text-align: left;
            font-weight: bold;
            font-size: 12px;
            color: #000;
        }
        
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .summary-row {
            background-color: #f3f4f6;
            font-weight: bold;
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
        }
        
        .detail-table {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #999;
        }
        
        .footer {
            margin-top: 40px;
            text-align: right;
            font-size: 12px;
        }
        
        .print-button {
            text-align: center;
            margin-bottom: 20px;
        }
        
        button {
            padding: 10px 20px;
            font-size: 14px;
            background-color: #3b82f6;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        button:hover {
            background-color: #2563eb;
        }
        
        @media print {
            .print-button {
                display: none;
            }
            
            body {
                margin: 0;
                padding: 0;
            }
            
            .container {
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="print-button">
            <button onclick="window.print()">🖨️ Cetak / Print to PDF</button>
        </div>

        <!-- Header -->
        <div class="header">
            <h1><?php echo htmlspecialchars($company_name); ?></h1>
            <p><?php echo htmlspecialchars($company_address); ?></p>
            <p style="margin-top: 10px; font-weight: bold;">LAPORAN ABSENSI KARYAWAN</p>
            <p><?php echo $months[$filter_month]; ?> <?php echo $filter_year; ?></p>
        </div>

        <!-- Info -->
        <div class="info">
            <div class="info-row">
                <span>Periode Laporan:</span>
                <span><?php echo date('d M Y', strtotime($first_day)); ?> - <?php echo date('d M Y', strtotime($last_day)); ?></span>
            </div>
            <div class="info-row">
                <span>Tanggal Cetak:</span>
                <span><?php echo date('d M Y H:i:s'); ?></span>
            </div>
        </div>

        <!-- Summary Table -->
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama Karyawan</th>
                    <th class="text-center">Hari Kerja</th>
                    <th class="text-center">Total Jam</th>
                    <th class="text-center">Lembur (Jam)</th>
                    <th class="text-right">Gaji Pokok</th>
                    <th class="text-right">Tunjangan Lembur</th>
                    <th class="text-right">Total Gaji</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($employee_summary as $emp_data):
                    $gaji_pokok = $emp_data['hari_kerja'] * $emp_data['gaji_harian'];
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($emp_data['nama']); ?></td>
                    <td class="text-center"><?php echo $emp_data['hari_kerja']; ?></td>
                    <td class="text-center"><?php echo round($emp_data['total_jam'], 1); ?></td>
                    <td class="text-center"><?php echo round($emp_data['total_lembur'], 1); ?></td>
                    <td class="text-right">Rp <?php echo number_format($gaji_pokok, 0, ',', '.'); ?></td>
                    <td class="text-right">Rp <?php echo number_format($emp_data['total_lembur_bayar'], 0, ',', '.'); ?></td>
                    <td class="text-right"><strong>Rp <?php echo number_format($emp_data['total_gaji'], 0, ',', '.'); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <tr class="summary-row">
                    <td colspan="2">TOTAL</td>
                    <td class="text-center"><?php echo $summary['total_hari_kerja']; ?></td>
                    <td class="text-center"><?php echo round($summary['total_jam_kerja'], 1); ?></td>
                    <td class="text-center"><?php echo round($summary['total_lembur'], 1); ?></td>
                    <td class="text-right">Rp <?php echo number_format($summary['total_gaji'] - $summary['total_lembur_bayar'], 0, ',', '.'); ?></td>
                    <td class="text-right">Rp <?php echo number_format($summary['total_lembur_bayar'], 0, ',', '.'); ?></td>
                    <td class="text-right">Rp <?php echo number_format($summary['total_gaji'], 0, ',', '.'); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Detailed Table -->
        <div class="detail-table">
            <h2 style="margin-bottom: 15px; font-size: 14px;">Detail Absensi Harian</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Nama Karyawan</th>
                        <th class="text-center">Jam Masuk</th>
                        <th class="text-center">Jam Pulang</th>
                        <th class="text-center">Total Jam</th>
                        <th class="text-center">Lembur</th>
                        <th class="text-right">Gaji Harian</th>
                        <th class="text-right">Tunjangan Lembur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $record): ?>
                        <?php if ($record['status'] === 'selesai'):
                            $daily_salary = calculateDailySalary(
                                $record['gaji_harian'],
                                $record['jam_lembur'] ?? 0,
                                $record['tarif_lembur_per_jam']
                            );
                            $lembur_bayar = ($record['jam_lembur'] ?? 0) * $record['tarif_lembur_per_jam'];
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($record['tanggal'])); ?></td>
                            <td><?php echo htmlspecialchars($record['nama']); ?></td>
                            <td class="text-center"><?php echo $record['jam_masuk'] ? date('H:i', strtotime($record['jam_masuk'])) : '-'; ?></td>
                            <td class="text-center"><?php echo $record['jam_pulang'] ? date('H:i', strtotime($record['jam_pulang'])) : '-'; ?></td>
                            <td class="text-center"><?php echo $record['total_jam'] ?? '0'; ?></td>
                            <td class="text-center"><?php echo $record['jam_lembur'] ?? '0'; ?></td>
                            <td class="text-right">Rp <?php echo number_format($record['gaji_harian'], 0, ',', '.'); ?></td>
                            <td class="text-right">Rp <?php echo number_format($lembur_bayar, 0, ',', '.'); ?></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>___________________________</p>
            <p>Admin / HRD</p>
            <p><?php echo date('d M Y'); ?></p>
        </div>
    </div>
</body>
</html>