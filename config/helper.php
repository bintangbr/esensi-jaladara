<?php
/**
 * ========================================
 * HELPER FUNCTIONS
 * ========================================
 * Fungsi utility untuk berbagai keperluan
 */

/**
 * Get settings from database
 * 
 * @param mysqli $conn Database connection
 * @param string $setting_name Nama setting
 * @param mixed $default Default value jika tidak ditemukan
 * @return mixed Setting value
 */
function getSetting($conn, $setting_name, $default = null) {
    $query = "SELECT value FROM settings WHERE nama_setting = ?";
    $row = getRow($conn, $query, [$setting_name], 's');
    return $row ? $row['value'] : $default;
}

/**
 * Update setting
 * 
 * @param mysqli $conn Database connection
 * @param string $setting_name Nama setting
 * @param mixed $value Value
 * @return bool True if success
 */
function updateSetting($conn, $setting_name, $value) {
    // Try UPDATE first
    $query = "UPDATE settings SET value = ? WHERE nama_setting = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('ss', $value, $setting_name);
    $stmt->execute();
    
    // If no rows updated, INSERT the setting
    if ($conn->affected_rows === 0) {
        $insert_query = "INSERT INTO settings (nama_setting, value) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        
        if (!$insert_stmt) {
            return false;
        }
        
        $insert_stmt->bind_param('ss', $setting_name, $value);
        $result = $insert_stmt->execute();
        $insert_stmt->close();
        
        return $result;
    }
    
    $stmt->close();
    return true;
}

/**
 * Calculate distance between two coordinates (Haversine formula)
 * 
 * @param float $lat1 Latitude 1
 * @param float $lon1 Longitude 1
 * @param float $lat2 Latitude 2
 * @param float $lon2 Longitude 2
 * @return float Distance in meters
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Earth radius in meters
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earthRadius * $c; // Distance in meters
    
    return round($distance, 2);
}

/**
 * Check if GPS location is within office radius
 * 
 * @param mysqli $conn Database connection
 * @param float $lat Current latitude
 * @param float $lon Current longitude
 * @return bool True if within radius
 */
function isWithinOfficeRadius($conn, $lat, $lon) {
    $office_lat = (float) getSetting($conn, 'gps_kantor_latitude', -6.2088);
    $office_lon = (float) getSetting($conn, 'gps_kantor_longitude', 106.8456);
    $office_radius = (int) getSetting($conn, 'gps_kantor_radius', 100);
    
    $distance = calculateDistance($lat, $lon, $office_lat, $office_lon);
    
    return $distance <= $office_radius;
}

/**
 * Validate file upload (image only)
 * 
 * @param array $file $_FILES array element
 * @param int $max_size Max file size in bytes (default 2MB)
 * @return array Validation result ['status' => bool, 'message' => string]
 */
function validateImageUpload($file, $max_size = 2097152) {
    $result = ['status' => false, 'message' => ''];
    
    // Check if file uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = 'File upload failed: ' . $file['error'];
        return $result;
    }
    
    $result['status'] = true;
    return $result;
}

/**
 * Save uploaded file
 * 
 * @param array $file $_FILES array element
 * @param string $directory Upload directory
 * @param string $prefix File prefix
 * @return string|null Filename if success, null if error
 */
function saveUploadedFile($file, $directory, $prefix = '') {
    // Validate upload
    $validation = validateImageUpload($file);
    if (!$validation['status']) {
        return null;
    }
    
    // Create directory if not exists
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    // Generate unique filename
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $prefix . '_' . time() . '_' . uniqid() . '.' . $file_ext;
    $filepath = $directory . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return null;
}

/**
 * Calculate working hours between two times
 * 
 * @param string $jam_masuk Time in HH:MM:SS format
 * @param string $jam_pulang Time in HH:MM:SS format
 * @return float Working hours
 */
function calculateWorkingHours($jam_masuk, $jam_pulang) {
    $masuk = strtotime($jam_masuk);
    $pulang = strtotime($jam_pulang);
    
    if (!$masuk || !$pulang || $pulang <= $masuk) {
        return 0;
    }
    
    $seconds = $pulang - $masuk;
    $hours = $seconds / 3600;
    
    return round($hours, 2);
}

/**
 * Calculate overtime hours
 * 
 * @param float $total_hours Total working hours
 * @param int $standard_hours Standard working hours per day (default 8)
 * @return float Overtime hours
 */
function calculateOvertimeHours($total_hours, $standard_hours = 8) {
    if ($total_hours > $standard_hours) {
        return round($total_hours - $standard_hours, 2);
    }
    
    return 0;
}

/**
 * Calculate daily salary
 * 
 * @param float $gaji_harian Daily salary
 * @param float $jam_lembur Overtime hours
 * @param float $tarif_lembur_per_jam Overtime rate per hour
 * @return float Total daily salary
 */
function calculateDailySalary($gaji_harian, $jam_lembur, $tarif_lembur_per_jam) {
    $overtime_pay = $jam_lembur * $tarif_lembur_per_jam;
    return $gaji_harian + $overtime_pay;
}

/**
 * Calculate working hours with date (handles overnight shifts)
 * Menghitung jam kerja yang melintasi tengah malam
 * 
 * Contoh:
 * - Masuk tgl 5 jam 04:00:00
 * - Pulang tgl 6 jam 04:00:00
 * - Total = 24 jam
 * 
 * @param string $tanggal_masuk Date in YYYY-MM-DD format (check-in date)
 * @param string $jam_masuk Time in HH:MM:SS format (check-in time)
 * @param string $tanggal_pulang Date in YYYY-MM-DD format (check-out date)
 * @param string $jam_pulang Time in HH:MM:SS format (check-out time)
 * @return float Working hours (rounded to 2 decimal places)
 */
function calculateWorkingHoursWithDate($tanggal_masuk, $jam_masuk, $tanggal_pulang, $jam_pulang) {
    // Create DateTime objects dengan timezone yang sudah dikonfigurasi
    $datetime_masuk = new DateTime($tanggal_masuk . ' ' . $jam_masuk);
    $datetime_pulang = new DateTime($tanggal_pulang . ' ' . $jam_pulang);
    
    // Calculate difference
    $interval = $datetime_masuk->diff($datetime_pulang);
    
    // Get total hours
    $days = (int)$interval->format('%d');
    $hours = (int)$interval->format('%h');
    $minutes = (int)$interval->format('%i');
    $seconds = (int)$interval->format('%s');
    
    // Convert to hours with decimal
    $total_hours = ($days * 24) + $hours + ($minutes / 60) + ($seconds / 3600);
    
    return round($total_hours, 2);
}

/**
 * Format currency
 * 
 * @param float $number Number to format
 * @param string $currency Currency symbol (default Rp)
 * @return string Formatted currency string
 */
function formatCurrency($number, $currency = 'Rp') {
    return $currency . ' ' . number_format($number, 0, ',', '.');
}

/**
 * Format time difference nicely
 * 
 * @param string $time Time in HH:MM:SS format
 * @return string Formatted time
 */
function formatTime($time) {
    return date('H:i', strtotime($time));
}

/**
 * Format date to Indonesian
 * 
 * @param string $date Date in YYYY-MM-DD format
 * @return string Formatted date in Indonesian
 */
function formatDateIndonesian($date) {
    $months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $date_obj = new DateTime($date);
    $day = $date_obj->format('d');
    $month = $date_obj->format('n');
    $year = $date_obj->format('Y');
    
    return $day . ' ' . $months[$month - 1] . ' ' . $year;
}

/**
 * Sanitize input string
 * 
 * @param string $input Input string
 * @return string Sanitized string
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Send JSON response
 * 
 * @param bool $success Success status
 * @param string $message Message
 * @param mixed $data Additional data
 * @return void
 */
function sendJsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Send WhatsApp notification
 * 
 * @param mysqli $conn Database connection
 * @param string $phone_number Nomor penerima (format: 62 atau 72)
 * @param string $message Pesan yang dikirim
 * @return bool True jika berhasil
 */
function sendWhatsAppNotification($conn, $phone_number, $message) {
    // Configuration
    $api_key = getSetting($conn, 'whatsapp_api_key', '');
    $sender = getSetting($conn, 'whatsapp_sender', '');
    
    if (empty($api_key) || empty($sender) || empty($phone_number)) {
        return false;
    }
    
    // Normalize phone number
    $phone_number = preg_replace('/[^0-9]/', '', $phone_number);
    if (strlen($phone_number) === 10) {
        $phone_number = '62' . substr($phone_number, 1); // 0888 -> 62888
    } elseif (!str_starts_with($phone_number, '62')) {
        $phone_number = '62' . $phone_number;
    }
    
    // Prepare request
    $url = 'https://api-wa.linkbit.biz.id/send-message';
    $data = [
        'api_key' => $api_key,
        'sender' => $sender,
        'number' => $phone_number,
        'message' => $message
    ];
    
    // Send via cURL dengan timeout error handling
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    // Return success jika tidak ada error (bisajalankan di background)
    return empty($err);
}

/**
 * Get phone number for WhatsApp
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return string|null Phone number atau null
 */
function getWhatsAppNumber($conn, $user_id) {
    try {
        $query = "SELECT whatsapp_number FROM users WHERE id = ?";
        $row = getRow($conn, $query, [$user_id], 'i');
        return $row ? $row['whatsapp_number'] : null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Send WhatsApp media notification (foto, video, etc)
 * 
 * @param mysqli $conn Database connection
 * @param string $phone_number Nomor penerima (format: 62 atau 72)
 * @param string $media_type Tipe media (image, video, audio, pdf, xls, xlsx, doc, docx, zip)
 * @param string $url URL direktmenuju file (harus direct link)
 * @param string $caption Caption/pesan yang dikirim
 * @return bool True jika berhasil
 */
function sendWhatsAppMediaNotification($conn, $phone_number, $media_type, $url, $caption = '') {
    // Configuration
    $api_key = getSetting($conn, 'whatsapp_api_key', '');
    $sender = getSetting($conn, 'whatsapp_sender', '');
    
    if (empty($api_key) || empty($sender) || empty($phone_number) || empty($url)) {
        return false;
    }
    
    // Normalize phone number
    $phone_number = preg_replace('/[^0-9]/', '', $phone_number);
    if (strlen($phone_number) === 10) {
        $phone_number = '62' . substr($phone_number, 1); // 0888 -> 62888
    } elseif (!str_starts_with($phone_number, '62')) {
        $phone_number = '62' . $phone_number;
    }
    
    // Prepare request
    $api_url = 'https://api-wa.linkbit.biz.id/send-media';
    $data = [
        'api_key' => $api_key,
        'sender' => $sender,
        'number' => $phone_number,
        'media_type' => $media_type,
        'url' => $url
    ];
    
    // Add caption if provided
    if (!empty($caption)) {
        $data['caption'] = $caption;
    }
    
    // Send via cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    return empty($err);
}