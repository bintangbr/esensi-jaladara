<?php
/**
 * ========================================
 * PROCESS ATTENDANCE
 * ========================================
 * Handle check-in/check-out submission
 */

// Set error handling
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $errstr . ' (' . $errfile . ':' . $errline . ')'
    ]);
    exit;
});

set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $exception->getMessage()
    ]);
    exit;
});

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';

redirectIfUnauthorized('karyawan');

$user_id = getCurrentUserId();
$today = date('Y-m-d');
$now = date('H:i:s');

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

// Get form data
$type = $_POST['type'] ?? '';
$gps_lat = (float) ($_POST['gps_lat'] ?? 0);
$gps_lon = (float) ($_POST['gps_lon'] ?? 0);

// Validate GPS
if ($gps_lat == 0 || $gps_lon == 0) {
    sendJsonResponse(false, 'GPS position tidak valid');
}

// Validate GPS is within office radius
if (!isWithinOfficeRadius($conn, $gps_lat, $gps_lon)) {
    sendJsonResponse(false, 'Lokasi Anda diluar jangkauan kantor');
}

// Validate file upload
if (!isset($_FILES['selfie_image'])) {
    sendJsonResponse(false, 'Selfie image tidak ditemukan');
}

$file = $_FILES['selfie_image'];

// Save selfie
$upload_dir = __DIR__ . '/../uploads/selfie';
$filename = saveSelfie($file, $upload_dir, $user_id, $type);

if (!$filename) {
    sendJsonResponse(false, 'Gagal menyimpan selfie');
}

// Get current attendance record - check today first
$query = "SELECT * FROM absensi WHERE user_id = ? AND tanggal = ?";
$attendance = getRow($conn, $query, [$user_id, $today], 'is');

// Jika tidak ditemukan hari ini dan tipe checkout, cek kemarin ada record dengan status 'absen_masuk'
// (karena sesi lembur yang record-nya di hari kemarin)
if (!$attendance && $type === 'checkout') {
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $query_yesterday = "SELECT * FROM absensi WHERE user_id = ? AND tanggal = ? AND status = 'absen_masuk'";
    $attendance = getRow($conn, $query_yesterday, [$user_id, $yesterday], 'is');
}

if (!$attendance) {
    sendJsonResponse(false, 'Rekam absensi tidak ditemukan');
}

// Process based on type
if ($type === 'checkin') {
    // Check if already checked in
    if (in_array($attendance['status'], ['absen_masuk', 'absen_pulang', 'selesai'])) {
        sendJsonResponse(false, 'Anda sudah absen masuk hari ini');
    }

    // Update attendance - using DATE_ADD with UTC_TIMESTAMP to ensure correct timezone (UTC+7)
    $query = "UPDATE absensi SET jam_masuk = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR), selfie_masuk = ?, lat_masuk = ?, lng_masuk = ?, status = 'absen_masuk', updated_at = NOW() 
              WHERE user_id = ? AND tanggal = ?";
    $success = executeModifyQuery($conn, $query, [$filename, $gps_lat, $gps_lon, $user_id, $today], 'sddis');

    if ($success) {
        // Get user info for WhatsApp
        $query_user = "SELECT nama, whatsapp_number FROM users WHERE id = ?";
        $user_info = getRow($conn, $query_user, [$user_id], 'i');
        
        // Get admin for notification
        $query_admin = "SELECT id, whatsapp_number FROM users WHERE role = 'admin' LIMIT 1";
        $admin_info = getRow($conn, $query_admin);
        
        // Get HRD number
        $hrd_number = getSetting($conn, 'hrd_number', '');
        
        // Send WhatsApp notifications (non-blocking)
        $user_phone = $user_info['whatsapp_number'] ?? null;
        $admin_phone = $admin_info['whatsapp_number'] ?? null;
        
        if ($user_phone) {
            $msg_user = "✅ *Absen Masuk Dicatat*\n\n"
                      . "Nama: " . $user_info['nama'] . "\n"
                      . "Tanggal: " . date('d-m-Y') . "\n"
                      . "Jam Masuk: " . date('H:i', strtotime($now)) . "\n\n"
                      . "Jangan lupa absen pulang sebelum meninggalkan tempat kerja! \n\n"
                      . "> Esensi by Bntng.Project" . "\n";

            sendWhatsAppNotification($conn, $user_phone, $msg_user);
        }
        
        if ($admin_phone && $user_phone !== $admin_phone) {
            $msg_admin = "📋 *Notifikasi Absensi Masuk*\n\n"
                       . "Karyawan: " . $user_info['nama'] . "\n"
                       . "Tanggal: " . date('d-m-Y') . "\n"
                       . "Jam Masuk: " . date('H:i', strtotime($now)) . "\n\n"
                       . "> Esensi by Bntng.Project" . "\n";

            sendWhatsAppNotification($conn, $admin_phone, $msg_admin);
        }
        
        // Send selfie to HRD with caption
       if ($hrd_number) {
            $photo_url = rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'], '/') . '/uploads/selfie/' . $filename;
            $hrd_caption = "📸 *Selfie Absen Masuk*\n\n"
                         . "Karyawan: " . $user_info['nama'] . "\n"
                         . "Tanggal: " . date('d-m-Y H:i:s') . "\n"
                         . "Status: Absen Masuk \n\n"
                         . "> Esensi by Bntng.Project" . "\n";
            
            sendWhatsAppMediaNotification($conn, $hrd_number, 'image', $photo_url, $hrd_caption);
        }
        
        sendJsonResponse(true, 'Absen masuk berhasil');
    } else {
        sendJsonResponse(false, 'Gagal menyimpan absen masuk');
    }

} elseif ($type === 'checkout') {
    // Check if already checked in
    if ($attendance['status'] === 'belum_absen') {
        sendJsonResponse(false, 'Anda harus absen masuk terlebih dahulu');
    }

    if ($attendance['status'] === 'selesai') {
        sendJsonResponse(false, 'Anda sudah absen pulang hari ini');
    }

    // Get jam_masuk and tanggal_masuk
    $jam_masuk = $attendance['jam_masuk'];
    $tanggal_masuk = $attendance['tanggal'];
    
    // Get current time untuk jam_pulang
    $jam_pulang_now = date('H:i:s');
    
    // Determine tanggal_pulang
    // Jika record masuk dari hari kemarin (ditemukan saat cek untuk checkout), 
    // maka tanggal_pulang pasti hari ini
    if ($tanggal_masuk < $today) {
        // Masuk kemarin, pulang hari ini (lembur)
        $tanggal_pulang = $today;
    } else {
        // Masuk dan pulang hari yang sama
        // Cek apakah pulang di hari berikutnya (tengah malam)
        $time_masuk = strtotime($jam_masuk);
        $time_pulang = strtotime($jam_pulang_now);
        
        if ($time_pulang < $time_masuk) {
            // Pulang di hari berikutnya (lembur melampaui tengah malam)
            $tanggal_pulang = date('Y-m-d', strtotime($tanggal_masuk . ' +1 day'));
        } else {
            // Pulang di hari yang sama
            $tanggal_pulang = $tanggal_masuk;
        }
    }
    
    // Calculate working hours dengan mempertimbangkan tanggal
    $total_jam = calculateWorkingHoursWithDate($tanggal_masuk, $jam_masuk, $tanggal_pulang, $jam_pulang_now);
    $jam_lembur = calculateOvertimeHours($total_jam);

    // Get user salary info
    $query = "SELECT gaji_harian, tarif_lembur_per_jam FROM users WHERE id = ?";
    $user_data = getRow($conn, $query, [$user_id], 'i');

    // Update attendance - using DATE_ADD with UTC_TIMESTAMP to ensure correct timezone (UTC+7)
    $query = "UPDATE absensi SET 
              jam_pulang = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR), 
              selfie_pulang = ?, 
              lat_pulang = ?, 
              lng_pulang = ?, 
              total_jam = ?, 
              jam_lembur = ?, 
              status = 'selesai',
              updated_at = NOW()
              WHERE user_id = ? AND tanggal = ?";
    
    $success = executeModifyQuery(
        $conn, 
        $query, 
        [$filename, $gps_lat, $gps_lon, $total_jam, $jam_lembur, $user_id, $tanggal_masuk], 
        'sddddis'
    );

    if ($success) {
        // Get user info for WhatsApp
        $query_user = "SELECT nama, whatsapp_number FROM users WHERE id = ?";
        $user_info = getRow($conn, $query_user, [$user_id], 'i');
        
        // Get admin for notification
        $query_admin = "SELECT id, whatsapp_number FROM users WHERE role = 'admin' LIMIT 1";
        $admin_info = getRow($conn, $query_admin);
        
        // Get HRD number
        $hrd_number = getSetting($conn, 'hrd_number', '');
        
        // Send WhatsApp notifications (non-blocking)
        $user_phone = $user_info['whatsapp_number'] ?? null;
        $admin_phone = $admin_info['whatsapp_number'] ?? null;
        
        if ($user_phone) {
            $msg_user = "✅ *Absen Pulang Dicatat*\n\n"
                      . "Nama: " . $user_info['nama'] . "\n"
                      . "Tanggal Masuk: " . date('d-m-Y', strtotime($tanggal_masuk)) . "\n"
                      . "Jam Masuk: " . date('H:i', strtotime($attendance['jam_masuk'])) . "\n"
                      . ($tanggal_pulang !== $tanggal_masuk ? "Tanggal Pulang: " . date('d-m-Y', strtotime($tanggal_pulang)) . "\n" : "")
                      . "Jam Pulang: " . date('H:i', strtotime($jam_pulang_now)) . "\n"
                      . "*Total Jam: " . $total_jam . " jam*\n"
                      . "*Lembur: " . $jam_lembur . " jam*\n";
            sendWhatsAppNotification($conn, $user_phone, $msg_user);
        }
        
        if ($admin_phone && $user_phone !== $admin_phone) {
            $msg_admin = "📋 *Notifikasi Absensi Pulang*\n\n"
                       . "Karyawan: " . $user_info['nama'] . "\n"
                       . "Tanggal Masuk: " . date('d-m-Y', strtotime($tanggal_masuk)) . "\n"
                       . "Jam Masuk: " . date('H:i', strtotime($attendance['jam_masuk'])) . "\n"
                       . ($tanggal_pulang !== $tanggal_masuk ? "Tanggal Pulang: " . date('d-m-Y', strtotime($tanggal_pulang)) . "\n" : "")
                       . "Jam Pulang: " . date('H:i', strtotime($jam_pulang_now)) . "\n"
                       . "Total Jam: " . $total_jam . " jam\n"
                       . "Lembur: " . $jam_lembur . " jam\n";
            sendWhatsAppNotification($conn, $admin_phone, $msg_admin);
        }
        
        // Send selfie to HRD with rekapan
        if ($hrd_number) {
            $photo_url = rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'], '/') . '/uploads/selfie/' . $filename;
            $hrd_caption = "📸 *Selfie Absen Pulang*\n\n"
                         . "Karyawan: " . $user_info['nama'] . "\n"
                         . "Tanggal Masuk: " . date('d-m-Y', strtotime($tanggal_masuk)) . "\n"
                         . "Jam Masuk: " . date('H:i', strtotime($attendance['jam_masuk'])) . "\n"
                         . ($tanggal_pulang !== $tanggal_masuk ? "Tanggal Pulang: " . date('d-m-Y', strtotime($tanggal_pulang)) . "\n" : "")
                         . "Jam Pulang: " . date('H:i', strtotime($jam_pulang_now)) . "\n"
                         . "Total Jam: " . $total_jam . " jam\n"
                         . "Lembur: " . $jam_lembur . " jam";
            
            sendWhatsAppMediaNotification($conn, $hrd_number, 'image', $photo_url, $hrd_caption);
        }
        
        // Return success with rekapan data
        $rekapan = [
            'status' => 'selesai',
            'nama' => $user_info['nama'],
            'tanggal_masuk' => date('d-m-Y', strtotime($tanggal_masuk)),
            'jam_masuk' => date('H:i', strtotime($attendance['jam_masuk'])),
            'tanggal_pulang' => date('d-m-Y', strtotime($tanggal_pulang)),
            'jam_pulang' => date('H:i', strtotime($jam_pulang_now)),
            'total_jam' => $total_jam,
            'jam_lembur' => $jam_lembur,
            'gaji_harian' => $user_data['gaji_harian'],
            'tunjangan_lembur' => $jam_lembur * $user_data['tarif_lembur_per_jam']
        ];
        
        sendJsonResponse(true, 'Absen pulang berhasil', $rekapan);
    } else {
        sendJsonResponse(false, 'Gagal menyimpan absen pulang');
    }

} else {
    sendJsonResponse(false, 'Tipe absensi tidak valid');
}

/**
 * Save selfie image
 * 
 * @param array $file $_FILES element
 * @param string $upload_dir Upload directory
 * @param int $user_id User ID
 * @param string $type Attendance type (checkin/checkout)
 * @return string|null Filename if success
 */
function saveSelfie($file, $upload_dir, $user_id, $type) {
    // Validate
    $validation = validateImageUpload($file);
    if (!$validation['status']) {
        return null;
    }

    // Create directory
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate filename
    $date = date('Ymd');
    $time = time();
    $filename = "selfie_{$user_id}_{$date}_{$type}_{$time}.jpg";
    $filepath = $upload_dir . '/' . $filename;

    // Move file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }

    return null;
}