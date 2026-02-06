<?php
/**
 * ========================================
 * EXPORT PAYROLL TO PDF
 * ========================================
 * Laporan Payroll untuk Accounting
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
$query = "SELECT a.*, u.id as user_id, u.nama, u.gaji_harian, u.tarif_lembur_per_jam
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

$query .= " ORDER BY u.nama, a.tanggal";
$report_data = getRows($conn, $query, $params, $types);

// Get company settings
$company_name = getSetting($conn, 'nama_perusahaan', 'PT. Aplikasi Absensi');
$company_address = getSetting($conn, 'alamat_kantor', 'Jakarta, Indonesia');

// Month names
$months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
           'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Calculate payroll data
$payroll_data = [];
$total_payroll = 0;

foreach ($report_data as $record) {
    if ($record['status'] === 'selesai') {
        $emp_id = $record['user_id'];
        
        if (!isset($payroll_data[$emp_id])) {
            $payroll_data[$emp_id] = [
                'nama' => $record['nama'],
                'gaji_harian' => $record['gaji_harian'],
                'tarif_lembur' => $record['tarif_lembur_per_jam'],
                'hari_kerja' => 0,
                'total_jam' => 0,
                'total_lembur' => 0,
                'total_detail' => []
            ];
        }
        
        $payroll_data[$emp_id]['hari_kerja']++;
        $payroll_data[$emp_id]['total_jam'] += $record['total_jam'] ?? 0;
        $payroll_data[$emp_id]['total_lembur'] += $record['jam_lembur'] ?? 0;
        
        $payroll_data[$emp_id]['total_detail'][] = [
            'tanggal' => $record['tanggal'],
            'jam_masuk' => $record['jam_masuk'],
            'jam_pulang' => $record['jam_pulang'],
            'total_jam' => $record['total_jam'],
            'jam_lembur' => $record['jam_lembur']
        ];
    }
}

// Calculate total payroll
foreach ($payroll_data as &$emp) {
    $gaji_pokok = $emp['hari_kerja'] * $emp['gaji_harian'];
    $gaji_lembur = $emp['total_lembur'] * $emp['tarif_lembur'];
    $emp['gaji_pokok'] = $gaji_pokok;
    $emp['gaji_lembur'] = $gaji_lembur;
    $emp['total_gaji'] = $gaji_pokok + $gaji_lembur;
    $total_payroll += $emp['total_gaji'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Payroll - <?php echo $months[$filter_month]; ?> <?php echo $filter_year; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            color: #333;
            line-height: 1.4;
            background: white;
        }
        
        .container {
            max-width: 100%;
            padding: 20px;
            margin: 0;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        
        .header h1 {
            font-size: 18px;
            margin-bottom: 3px;
            letter-spacing: 2px;
            font-weight: bold;
        }
        
        .header p {
            font-size: 11px;
            margin: 2px 0;
        }
        
        .period-info {
            margin-bottom: 15px;
            font-size: 11px;
            display: flex;
            justify-content: space-between;
        }
        
        .summary-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
            font-size: 11px;
        }
        
        .summary-table thead {
            background-color: #f0f0f0;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        
        .summary-table th {
            padding: 8px 5px;
            text-align: left;
            font-weight: bold;
            border-right: 1px solid #ccc;
        }
        
        .summary-table th:last-child {
            border-right: none;
        }
        
        .summary-table td {
            padding: 6px 5px;
            border-right: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .summary-table td:last-child {
            border-right: none;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .total-row {
            background-color: #f0f0f0;
            font-weight: bold;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        
        .detail-section {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px dashed #999;
            page-break-inside: avoid;
        }
        
        .detail-header {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 11px;
            padding: 3px 0;
            border-bottom: 1px solid #ccc;
        }
        
        .detail-item {
            font-size: 10px;
            margin: 2px 0;
            padding-left: 10px;
        }
        
        .footer {
            margin-top: 30px;
            text-align: right;
            font-size: 10px;
            border-top: 1px solid #999;
            padding-top: 10px;
        }
        
        .print-button {
            text-align: center;
            margin-bottom: 20px;
        }
        
        button {
            padding: 10px 20px;
            font-size: 12px;
            background-color: #2c3e50;
            color: white;
            border: 1px solid #000;
            border-radius: 3px;
            cursor: pointer;
            font-family: Arial, sans-serif;
        }
        
        button:hover {
            background-color: #34495e;
        }
        
        @media print {
            .print-button {
                display: none;
            }
            
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            
            .container {
                max-width: 100%;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="print-button">
            <button onclick="window.print()">🖨️ CETAK / PRINT TO PDF</button>
        </div>

        <!-- Header -->
        <div class="header">
            <h1><strong><?php echo strtoupper($company_name); ?></strong></h1>
            <p><?php echo htmlspecialchars($company_address); ?></p>
            <p><strong>SLIP GAJI KARYAWAN</strong></p>
            <p><?php echo strtoupper($months[$filter_month]); ?> <?php echo $filter_year; ?></p>
        </div>

        <!-- Period Info -->
        <div class="period-info">
            <div>
                <strong>Periode:</strong> <?php echo date('d/m/Y', strtotime($first_day)); ?> s/d <?php echo date('d/m/Y', strtotime($last_day)); ?>
            </div>
            <div>
                <strong>Cetak:</strong> <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>

        <!-- Summary Table -->
        <table class="summary-table">
            <thead>
                <tr>
                    <th style="width: 4%;">No</th>
                    <th style="width: 25%;">NAMA KARYAWAN</th>
                    <th style="width: 8%;" class="text-center">HARI KERJA</th>
                    <th style="width: 8%;" class="text-center">JAM</th>
                    <th style="width: 8%;" class="text-center">LEMBUR</th>
                    <th style="width: 15%;" class="text-right">GAJI POKOK</th>
                    <th style="width: 15%;" class="text-right">GAJI LEMBUR</th>
                    <th style="width: 17%;" class="text-right">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($payroll_data as $emp):
                ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($emp['nama']); ?></td>
                    <td class="text-center"><?php echo $emp['hari_kerja']; ?></td>
                    <td class="text-center"><?php echo round($emp['total_jam'], 1); ?></td>
                    <td class="text-center"><?php echo round($emp['total_lembur'], 1); ?></td>
                    <td class="text-right">Rp <?php echo number_format($emp['gaji_pokok'], 0, ',', '.'); ?></td>
                    <td class="text-right">Rp <?php echo number_format($emp['gaji_lembur'], 0, ',', '.'); ?></td>
                    <td class="text-right"><strong>Rp <?php echo number_format($emp['total_gaji'], 0, ',', '.'); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="7">TOTAL PENGGAJIAN BULAN INI</td>
                    <td class="text-right">Rp <?php echo number_format($total_payroll, 0, ',', '.'); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Details per Employee -->
        <div style="page-break-before: always; margin-top: 30px;">
            <h2 style="text-align: center; margin-bottom: 20px; font-size: 13px; text-decoration: underline;">DETAIL KEHADIRAN</h2>
            
            <?php foreach ($payroll_data as $emp_id => $emp): ?>
            <div class="detail-section">
                <div class="detail-header">
                    <?php echo htmlspecialchars($emp['nama']); ?> 
                    - Hari Kerja: <?php echo $emp['hari_kerja']; ?> Hari | 
                    Total Jam: <?php echo round($emp['total_jam'], 1); ?> Jam | 
                    Lembur: <?php echo round($emp['total_lembur'], 1); ?> Jam
                </div>
                
                <?php foreach ($emp['total_detail'] as $detail): ?>
                    <div class="detail-item">
                        <?php echo date('d/m/Y', strtotime($detail['tanggal'])); ?> | 
                        Masuk: <?php echo date('H:i', strtotime($detail['jam_masuk'])); ?> | 
                        Pulang: <?php echo date('H:i', strtotime($detail['jam_pulang'])); ?> | 
                        Jam: <?php echo round($detail['total_jam'], 2); ?> | 
                        Lembur: <?php echo round($detail['jam_lembur'], 2); ?>
                    </div>
                <?php endforeach; ?>
                
                <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dotted #ccc; font-size: 10px;">
                    <strong>Ringkasan:</strong>
                    Gaji Pokok: Rp <?php echo number_format($emp['gaji_pokok'], 0, ',', '.'); ?> | 
                    Gaji Lembur: Rp <?php echo number_format($emp['gaji_lembur'], 0, ',', '.'); ?> | 
                    <strong>Total: Rp <?php echo number_format($emp['total_gaji'], 0, ',', '.'); ?></strong>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div>Dicetak oleh: <?php echo htmlspecialchars(getCurrentUser()['nama']); ?></div>
            <div>Tanggal: <?php echo date('d M Y', strtotime('now')); ?></div>
            <div style="margin-top: 15px;">________________________</div>
            <div style="margin-top: -10px;">GK / HRD</div>
        </div>
    </div>
</body>
</html>