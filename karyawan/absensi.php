<?php
/**
 * ========================================
 * HALAMAN ABSENSI KARYAWAN
 * ========================================
 * Attendance check-in/check-out with selfie and GPS
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';

redirectIfUnauthorized('karyawan');

$user = getCurrentUser();
$user_id = $user['id'];
$today = date('Y-m-d');

// Get office GPS settings
$office_lat = (float) getSetting($conn, 'gps_kantor_latitude', -6.2088);
$office_lon = (float) getSetting($conn, 'gps_kantor_longitude', 106.8456);
$office_radius = (int) getSetting($conn, 'gps_kantor_radius', 100);

// Get today's attendance record
$query = "SELECT * FROM absensi WHERE user_id = ? AND tanggal = ?";
$attendance = getRow($conn, $query, [$user_id, $today], 'is');

// Jika tidak ada record hari ini, cek apakah ada record kemarin yang statusnya 'absen_masuk'
// (berarti dia lembur ke hari ini)
$is_overtime_from_yesterday = false;
$debug_info = ''; // Debug info

if (!$attendance) {
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $query_yesterday = "SELECT * FROM absensi WHERE user_id = ? AND tanggal = ? AND status = 'absen_masuk'";
    $attendance_yesterday = getRow($conn, $query_yesterday, [$user_id, $yesterday], 'is');
    
    if ($attendance_yesterday) {
        // Ada record kemarin dengan status 'absen_masuk', gunakan itu untuk checkout
        $attendance = $attendance_yesterday;
        $is_overtime_from_yesterday = true;
        $debug_info = "✓ Overtime dari kemarin (tanggal: {$yesterday}, status: {$attendance['status']})";
    } else {
        // Tidak ada record sama sekali, buat record baru untuk hari ini
        $query = "INSERT INTO absensi (user_id, tanggal, status, created_at, updated_at) VALUES (?, ?, 'belum_absen', NOW(), NOW())";
        executeModifyQuery($conn, $query, [$user_id, $today], 'is');
        $attendance = ['status' => 'belum_absen', 'jam_masuk' => null, 'jam_pulang' => null];
        $debug_info = "✓ Record baru dibuat untuk hari ini (tanggal: {$today})";
    }
} else {
    $debug_info = "✓ Record hari ini ditemukan (tanggal: {$today}, status: {$attendance['status']})";
}

// Check if already check-out
$already_checkout = $attendance['status'] === 'selesai';
$already_checkin = !in_array($attendance['status'], ['belum_absen', 'absen_masuk']);

// Jika lembur dari kemarin, force user hanya bisa absen pulang
if ($is_overtime_from_yesterday) {
    $already_checkin = true;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Absensi - Sistem Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/assets/js/camera.js"></script>
    <script src="/assets/js/gps.js"></script>
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
        
        .status-card {
            background: linear-gradient(135deg, #1d2a63 0%, #5a6886 100%);
            color: white;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.5); }
            70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
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
            transform: translateY(-2px);
        }
        
        .camera-frame {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .camera-frame::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 4px solid rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            pointer-events: none;
            z-index: 10;
        }
        
        .floating-button {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .floating-button:active {
            transform: scale(0.95);
        }
        
        .progress-ring {
            transform: rotate(-90deg);
        }
        
        .progress-ring__circle {
            transition: stroke-dashoffset 0.35s;
            transform-origin: 50% 50%;
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
    <div class="max-w-md mx-auto px-4 pt-4 pb-24 md:pt-8">
        <!-- Welcome Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Halo, <?php echo explode(' ', $user['nama'])[0]; ?>! 👋</h1>
                    <p class="text-gray-600"><?php echo date('l, d F Y'); ?></p>
                </div>
                <button onclick="window.location.href='/karyawan/riwayat.php'" class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-history text-gray-600"></i>
                </button>
            </div>
        </div>

        <!-- Status Card -->
        <div class="status-card rounded-2xl p-5 mb-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -translate-y-6 translate-x-6"></div>
            <div class="absolute bottom-0 left-0 w-16 h-16 bg-white/10 rounded-full translate-y-4 -translate-x-4"></div>
            
            <?php if ($is_overtime_from_yesterday): ?>
            <!-- Overtime Badge -->
            <div class="mb-4 p-3 bg-yellow-400/30 rounded-xl backdrop-blur-sm border border-yellow-300">
                <div class="flex items-center">
                    <i class="fas fa-clock text-yellow-200 mr-2"></i>
                    <span class="text-sm font-semibold text-yellow-100">
                        ⚠️ Anda sedang dalam sesi lembur dari hari kemarin. Silakan absen pulang untuk menyelesaikan sesi kerja.
                    </span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Debug Info Badge -->
            <!-- <div class="mb-4 p-3 bg-blue-400/20 rounded-xl backdrop-blur-sm border border-blue-300 text-xs">
                <p class="text-blue-100">🔍 Debug: <?php echo $debug_info; ?></p>
            </div> -->
            
            <div class="relative z-10">
                <h2 class="text-xl font-bold mb-4">Status Absensi Saat Ini</h2>
                
                <?php if ($is_overtime_from_yesterday): ?>
                <!-- Overtime Info -->
                <div class="mb-4 p-3 bg-white/10 rounded-xl backdrop-blur-sm border border-white/20">
                    <p class="text-xs opacity-90 mb-2">📅 Tanggal Masuk (Kemarin)</p>
                    <p class="text-lg font-bold">
                        <?php echo date('d-m-Y', strtotime($attendance['tanggal'])); ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div class="text-center p-3 bg-white/20 rounded-xl backdrop-blur-sm">
                        <p class="text-xs opacity-90 mb-1">Jam Masuk</p>
                        <p class="text-xl font-bold">
                            <?php 
                            if ($attendance['jam_masuk']) {
                                echo date('H:i', strtotime($attendance['jam_masuk']));
                            } else {
                                echo $is_overtime_from_yesterday ? '(Kemarin)' : 'Belum Masuk';
                            }
                            ?>
                        </p>
                    </div>
                    <div class="text-center p-3 bg-white/20 rounded-xl backdrop-blur-sm">
                        <p class="text-xs opacity-90 mb-1">Status</p>
                        <p class="text-lg font-bold">
                            <?php 
                            $status_text = [
                                'belum_absen' => 'Belum Absen',
                                'absen_masuk' => $is_overtime_from_yesterday ? 'Lembur (Masuk Kemarin)' : 'Sudah Masuk',
                                'absen_pulang' => 'Sudah Pulang',
                                'selesai' => 'Selesai'
                            ];
                            echo $status_text[$attendance['status']] ?? 'N/A';
                            ?>
                        </p>
                    </div>
                    <div class="text-center p-3 bg-white/20 rounded-xl backdrop-blur-sm">
                        <p class="text-xs opacity-90 mb-1">Jam Pulang</p>
                        <p class="text-xl font-bold">
                            <?php echo $attendance['jam_pulang'] ? date('H:i', strtotime($attendance['jam_pulang'])) : 'Belum Pulang'; ?>
                        </p>
                    </div>
                </div>

                <!-- Location Info -->
                <div class="bg-white/20 p-3 rounded-xl backdrop-blur-sm mb-3">
                    <div class="flex items-center mb-1">
                        <i class="fas fa-map-marker-alt mr-2 text-sm"></i>
                        <p class="text-sm font-medium">Lokasi Kantor</p>
                    </div>
                    <div class="text-xs opacity-90">
                        <div class="flex justify-between mb-1">
                            <span>Latitude:</span>
                            <code class="font-mono"><?php echo $office_lat; ?></code>
                        </div>
                        <div class="flex justify-between mb-1">
                            <span>Longitude:</span>
                            <code class="font-mono"><?php echo $office_lon; ?></code>
                        </div>
                        <div class="flex justify-between">
                            <span>Radius:</span>
                            <code class="font-mono"><?php echo $office_radius; ?>m</code>
                        </div>
                    </div>
                </div>
                
                <?php if ($attendance['status'] === 'selesai'): ?>
                <!-- Work Summary if completed -->
                <div class="mt-3 p-3 bg-green-400/20 rounded-xl backdrop-blur-sm border border-green-300">
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <p class="opacity-90">Total Jam</p>
                            <p class="font-bold text-green-100"><?php echo $attendance['total_jam'] ?? '0'; ?> jam</p>
                        </div>
                        <div>
                            <p class="opacity-90">Jam Lembur</p>
                            <p class="font-bold text-orange-100"><?php echo $attendance['jam_lembur'] ?? '0'; ?> jam</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div id="gpsStatus" class="mt-3 text-sm flex items-center">
                    <i class="fas fa-sync-alt animate-spin mr-2"></i>
                    <span>Memeriksa lokasi Anda...</span>
                </div>
            </div>
        </div>

        <!-- Camera Section -->
        <div class="glass-card rounded-2xl p-5 mb-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800">Selfie Absensi</h3>
                <div class="flex items-center text-sm text-gray-600">
                    <i class="fas fa-camera mr-1"></i>
                    <span>Wajah harus jelas</span>
                </div>
            </div>

            <!-- Camera Preview -->
            <div class="mb-6">
                <div class="camera-frame mb-4 relative">
                    <video id="cameraPreview" class="w-full h-64 bg-gray-900 rounded-lg" autoplay playsinline></video>
                    <div class="absolute bottom-4 left-0 right-0 flex justify-center z-20">
                        <div class="bg-black/50 text-white text-xs px-3 py-1 rounded-full">
                            <i class="fas fa-user-circle mr-1"></i> Posisikan wajah di dalam area
                        </div>
                    </div>
                </div>
                <canvas id="captureCanvas" style="display: none;"></canvas>
                
                <!-- Preview Section -->
                <div id="imagePreview" class="hidden mb-4">
                    <div class="relative">
                        <img id="previewImage" class="w-full h-64 object-cover rounded-2xl" />
                        <div class="absolute top-3 right-3 bg-black/50 text-white text-xs px-3 py-1 rounded-full">
                            <i class="fas fa-check-circle mr-1"></i> Foto diambil
                        </div>
                    </div>
                    
                    <div id="previewControls" class="flex gap-3 mt-4">
                        <button type="button" onclick="retakeSelfie()" 
                                class="flex-1 bg-white border-2 border-gray-300 text-gray-700 py-3 px-4 rounded-xl font-semibold flex items-center justify-center">
                            <i class="fas fa-redo mr-2"></i> Ambil Ulang
                        </button>
                        <button type="button" id="confirmSelfieBtn" 
                                class="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3 px-4 rounded-xl font-semibold flex items-center justify-center">
                            <i class="fas fa-check mr-2"></i> Konfirmasi
                        </button>
                    </div>
                </div>
            </div>

            <!-- Camera Controls -->
            <div class="flex gap-3" id="mainControls">
                <button type="button" id="startCameraBtn" onclick="startCamera()" 
                        class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white py-3 px-4 rounded-xl font-semibold flex items-center justify-center floating-button">
                    <i class="fas fa-video mr-2"></i> Buka Kamera
                </button>
                <button type="button" id="captureBtn" onclick="captureSelfie()" 
                        class="flex-1 bg-gradient-to-r from-purple-500 to-pink-600 text-white py-3 px-4 rounded-xl font-semibold flex items-center justify-center floating-button pulse-animation"
                        disabled>
                    <i class="fas fa-camera mr-2"></i> Ambil Foto
                </button>
            </div>
        </div>

        <!-- Attendance Form -->
        <div id="attendanceForm" method="POST" enctype="multipart/form-data" class="hidden">
            <input type="hidden" id="selfieData" name="selfie_data" />
            <input type="hidden" id="attendanceType" name="attendance_type" />
            <input type="hidden" id="gpsLat" name="gps_lat" />
            <input type="hidden" id="gpsLon" name="gps_lon" />

            <div class="glass-card rounded-2xl p-5 mb-6 shadow-xl">
                <div class="flex items-start mb-4 p-3 bg-blue-50 rounded-xl">
                    <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                    <div class="text-sm text-gray-700">
                        <p class="font-medium mb-1">Konfirmasi Absensi</p>
                        <p>Pastikan data selfie dan lokasi Anda sudah benar sebelum melanjutkan</p>
                    </div>
                </div>

                <!-- GPS Status Display -->
                <div id="gpsConfirmStatus" class="mb-4 p-3 bg-gray-50 rounded-xl">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <i class="fas fa-map-marker-alt text-gray-600 mr-2"></i>
                            <span class="text-sm font-medium">Status Lokasi</span>
                        </div>
                        <span id="gpsStatusIcon" class="text-xl"></span>
                    </div>
                    <div class="text-xs text-gray-600" id="gpsDistanceInfo">
                        Menghitung jarak dari kantor...
                    </div>
                </div>

                <!-- Agreement -->
                <div class="mb-6">
                    <label class="flex items-start p-3 bg-gray-50 rounded-xl cursor-pointer">
                        <input type="checkbox" id="agreeCheckbox" class="mt-1 mr-3 w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                        <span class="text-sm text-gray-700">
                            Saya setuju bahwa selfie dan lokasi GPS saya akan digunakan untuk keperluan absensi dan disimpan secara aman sesuai dengan kebijakan privasi perusahaan.
                        </span>
                    </label>
                </div>

                <!-- Submit Buttons -->
                <div class="space-y-3">
                    <button type="button" id="submitCheckinBtn" 
                            class="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white py-4 px-4 rounded-xl font-bold text-lg flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed floating-button"
                            <?php echo in_array($attendance['status'], ['absen_masuk', 'selesai']) ? 'disabled' : ''; ?>>
                        <i class="fas fa-sign-in-alt mr-3"></i> ABSEN MASUK
                    </button>
                    
                    <button type="button" id="submitCheckoutBtn" 
                            class="w-full bg-gradient-to-r from-red-500 to-orange-600 hover:from-red-600 hover:to-orange-700 text-white py-4 px-4 rounded-xl font-bold text-lg flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed floating-button"
                            <?php echo $already_checkout ? 'disabled' : ($attendance['status'] === 'belum_absen' ? 'disabled' : ''); ?>>
                        <i class="fas fa-sign-out-alt mr-3"></i> ABSEN PULANG
                    </button>
                    
                    <button type="button" onclick="cancelAttendance()" 
                            class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 py-3 px-4 rounded-xl font-medium">
                        <i class="fas fa-times mr-2"></i> Batalkan
                    </button>
                </div>
            </div>
        </div>

        <!-- Info Card -->
        <div class="glass-card rounded-2xl p-5 shadow-xl">
            <div class="flex items-center mb-3">
                <div class="w-10 h-10 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-exclamation-triangle text-white"></i>
                </div>
                <h4 class="text-lg font-bold text-gray-800">Panduan Absensi</h4>
            </div>
            
            <div class="space-y-3">
                <div class="flex items-start">
                    <div class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs mr-3 mt-0.5">
                        <i class="fas fa-check"></i>
                    </div>
                    <p class="text-sm text-gray-700 flex-1">Pastikan Anda berada dalam radius <strong><?php echo $office_radius; ?> meter</strong> dari tempat kerja</p>
                </div>
                <div class="flex items-start">
                    <div class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs mr-3 mt-0.5">
                        <i class="fas fa-check"></i>
                    </div>
                    <p class="text-sm text-gray-700 flex-1">Wajah harus terlihat jelas dan pencahayaan cukup</p>
                </div>
                <div class="flex items-start">
                    <div class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs mr-3 mt-0.5">
                        <i class="fas fa-check"></i>
                    </div>
                    <p class="text-sm text-gray-700 flex-1">Pastikan GPS aktif dan koneksi internet stabil</p>
                </div>
                <!-- <div class="flex items-start">
                    <div class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs mr-3 mt-0.5">
                        <i class="fas fa-check"></i>
                    </div>
                    <p class="text-sm text-gray-700 flex-1">Ukuran file selfie maksimal 2MB (format JPG/PNG)</p>
                </div> -->
            </div>
        </div>
    </div>

    <!-- Hidden form for processing -->
    <form id="processForm" method="POST" action="/karyawan/process_absensi.php" enctype="multipart/form-data" style="display: none;">
        <input type="hidden" id="processType" name="type" />
        <input type="hidden" id="processGpsLat" name="gps_lat" />
        <input type="hidden" id="processGpsLon" name="gps_lon" />
        <input type="file" id="processFile" name="selfie_image" />
    </form>

    <!-- Bottom Navigation -->
    <div class="bottom-nav fixed bottom-0 left-0 right-0 md:hidden z-50">
        <div class="flex justify-around items-center h-full">
            <a href="/karyawan/dashboard.php" class="nav-item flex flex-col items-center justify-center text-gray-500">
                <i class="fas fa-home text-xl mb-1"></i>
                <span class="text-xs">Dashboard</span>
            </a>
            <a href="/karyawan/riwayat.php" class="nav-item flex flex-col items-center justify-center text-gray-500">
                <i class="fas fa-history text-xl mb-1"></i>
                <span class="text-xs">Riwayat</span>
            </a>
            <a href="/karyawan/absensi.php" class="nav-item active flex flex-col items-center justify-center text-blue-600">
                <div class="w-14 h-14 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center -mt-6 shadow-lg">
                    <i class="fas fa-camera text-white text-xl"></i>
                </div>
                <span class="text-xs mt-2">Absensi</span>
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

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl p-8 max-w-xs mx-4 text-center">
            <div class="w-16 h-16 mx-auto mb-4 relative">
                <svg class="progress-ring w-full h-full" width="120" height="120">
                    <circle class="progress-ring__circle" stroke="#3b82f6" stroke-width="4" 
                            fill="transparent" r="52" cx="60" cy="60"/>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <i class="fas fa-spinner fa-spin text-blue-600 text-2xl"></i>
                </div>
            </div>
            <p class="text-gray-800 font-medium mb-1" id="loadingText">Mengirim data...</p>
            <p class="text-sm text-gray-600">Harap tunggu sebentar</p>
        </div>
    </div>

    <!-- Rekapan Modal -->
    <div id="rekapanModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl max-w-sm mx-4 overflow-hidden shadow-2xl animate-in">
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 p-6 text-white text-center">
                <i class="fas fa-check-circle text-4xl mb-3"></i>
                <h2 class="text-2xl font-bold">Absen Pulang Berhasil!</h2>
            </div>

            <!-- Content -->
            <div class="p-6 space-y-4">
                <div class="bg-gray-50 p-4 rounded-xl">
                    <p class="text-gray-600 text-sm mb-1">Nama Karyawan</p>
                    <p class="text-xl font-bold text-gray-800" id="rekapNama">-</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-blue-50 p-3 rounded-lg">
                        <p class="text-gray-600 text-xs">Tanggal Masuk</p>
                        <p class="text-sm font-bold text-blue-600" id="rekapTanggalMasuk">--:--</p>
                        <p class="text-gray-600 text-xs mt-2">Jam Masuk</p>
                        <p class="text-lg font-bold text-blue-600" id="rekapMasuk">--:--</p>
                    </div>
                    <div class="bg-red-50 p-3 rounded-lg">
                        <p class="text-gray-600 text-xs">Tanggal Pulang</p>
                        <p class="text-sm font-bold text-red-600" id="rekapTanggalPulang">--:--</p>
                        <p class="text-gray-600 text-xs mt-2">Jam Pulang</p>
                        <p class="text-lg font-bold text-red-600" id="rekapPulang">--:--</p>
                    </div>
                </div>

                <div class="border-t-2 border-gray-200 pt-4 space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">Total Jam Kerja</span>
                        <span class="text-xl font-bold text-green-600" id="rekapTotalJam">0 jam</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">Jam Lembur</span>
                        <span class="text-xl font-bold text-orange-600" id="rekapLembur">0 jam</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">Gaji Harian</span>
                        <span class="text-lg font-bold text-blue-600" id="rekapGaji">Rp 0</span>
                    </div>
                    <div class="flex justify-between items-center border-t pt-3">
                        <span class="text-gray-700 font-semibold">Tunjangan Lembur</span>
                        <span class="text-lg font-bold text-purple-600" id="rekapTunjangan">Rp 0</span>
                    </div>
                </div>

                <div class="bg-green-50 border border-green-200 p-3 rounded-lg mt-4">
                    <p class="text-sm text-green-800">✅ WhatsApp notifikasi telah dikirim ke nomor Anda</p>
                </div>
            </div>

            <!-- Action -->
            <div class="bg-gray-50 p-6 flex gap-3">
                <button onclick="closeRekapanModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 px-4 rounded-lg font-semibold">
                    Selesai
                </button>
                <button onclick="window.location.href='/karyawan/riwayat.php'" class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-lg font-semibold flex items-center justify-center gap-2">
                    <i class="fas fa-history"></i> Riwayat
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let cameraHandler = null;
        let gpsHandler = null;
        let currentSelfie = null;
        let currentGpsPosition = null;
        let isWithinRadius = false;
        let gpsDistance = 0;

        const officeData = {
            latitude: <?php echo $office_lat; ?>,
            longitude: <?php echo $office_lon; ?>,
            radius: <?php echo $office_radius; ?>
        };

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            cameraHandler = new CameraHandler('cameraPreview', 'captureCanvas');
            gpsHandler = new GPSHandler();

            // Setup event listeners
            document.getElementById('confirmSelfieBtn').addEventListener('click', showAttendanceForm);
            document.getElementById('submitCheckinBtn').addEventListener('click', () => submitAttendance('checkin'));
            document.getElementById('submitCheckoutBtn').addEventListener('click', () => submitAttendance('checkout'));
            
            // Check dependencies
            if (!CameraHandler.isCameraAvailable()) {
                showToast('Kamera tidak tersedia di perangkat Anda', 'error');
            }

            if (!gpsHandler.isGPSAvailable()) {
                showToast('GPS tidak tersedia di perangkat Anda', 'error');
            }

            // Get GPS position on page load
            getGPSPosition();

            // Add active state to bottom nav
            const currentPage = window.location.pathname;
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                const link = item.getAttribute('href');
                if (currentPage.includes(link)) {
                    item.classList.add('active');
                }
            });
        });

        async function startCamera() {
            const btn = document.getElementById('startCameraBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Membuka...';
            btn.disabled = true;

            try {
                const success = await cameraHandler.start();
                if (success) {
                    document.getElementById('captureBtn').disabled = false;
                    btn.innerHTML = '<i class="fas fa-video mr-2"></i> Kamera Aktif';
                    btn.classList.remove('pulse-animation');
                    
                    // Show camera status
                    showToast('Kamera siap digunakan', 'success');
                } else {
                    throw new Error('Gagal mengakses kamera');
                }
            } catch (error) {
                btn.innerHTML = '<i class="fas fa-video mr-2"></i> Buka Kamera';
                btn.disabled = false;
                showToast('Gagal mengakses kamera: ' + error.message, 'error');
            }
        }

        function captureSelfie() {
            const dataUrl = cameraHandler.capture();
            if (dataUrl) {
                currentSelfie = dataUrl;
                document.getElementById('previewImage').src = dataUrl;
                document.getElementById('imagePreview').classList.remove('hidden');
                document.getElementById('mainControls').classList.add('hidden');
                
                // Stop camera to save battery
                cameraHandler.stop();
                
                showToast('Foto berhasil diambil', 'success');
            }
        }

        function retakeSelfie() {
            document.getElementById('imagePreview').classList.add('hidden');
            document.getElementById('mainControls').classList.remove('hidden');
            document.getElementById('attendanceForm').classList.add('hidden');
            currentSelfie = null;
            
            // Restart camera
            startCamera();
        }

        function showAttendanceForm() {
            if (!currentSelfie) {
                showToast('Silakan ambil foto terlebih dahulu', 'warning');
                return;
            }
            
            document.getElementById('attendanceForm').classList.remove('hidden');
            document.getElementById('imagePreview').classList.add('hidden');
            
            // Scroll to form
            document.getElementById('attendanceForm').scrollIntoView({ behavior: 'smooth' });
        }

        async function getGPSPosition() {
            try {
                const position = await gpsHandler.getCurrentPosition();
                currentGpsPosition = position;
                
                gpsDistance = GPSHandler.calculateDistance(
                    position.latitude,
                    position.longitude,
                    officeData.latitude,
                    officeData.longitude
                );

                isWithinRadius = GPSHandler.isWithinRadius(
                    position.latitude,
                    position.longitude,
                    officeData.latitude,
                    officeData.longitude,
                    officeData.radius
                );

                // Update GPS status display
                const statusEl = document.getElementById('gpsStatus');
                const statusIcon = document.getElementById('gpsStatusIcon');
                const distanceInfo = document.getElementById('gpsDistanceInfo');
                const confirmStatus = document.getElementById('gpsConfirmStatus');
                
                if (isWithinRadius) {
                    statusEl.innerHTML = `<i class="fas fa-check-circle text-green-500 mr-2"></i><span>Lokasi valid (${Math.round(gpsDistance)}m dari tempat kerja)</span>`;
                    statusIcon.innerHTML = '<i class="fas fa-check-circle text-green-500"></i>';
                    distanceInfo.innerHTML = `Anda berada dalam radius (${Math.round(gpsDistance)}m dari kantor)`;
                    confirmStatus.classList.remove('bg-gray-50');
                    confirmStatus.classList.add('bg-green-50', 'border', 'border-green-200');
                } else {
                    statusEl.innerHTML = `<i class="fas fa-exclamation-circle text-red-500 mr-2"></i><span>Luar jangkauan (${Math.round(gpsDistance)}m dari tempat kerja)</span>`;
                    statusIcon.innerHTML = '<i class="fas fa-times-circle text-red-500"></i>';
                    distanceInfo.innerHTML = `Anda di luar radius (${Math.round(gpsDistance)}m dari tempat kerja, maks: ${officeData.radius}m)`;
                    confirmStatus.classList.remove('bg-gray-50');
                    confirmStatus.classList.add('bg-red-50', 'border', 'border-red-200');
                }
                
                // Enable/disable submit buttons based on location
                const checkinBtn = document.getElementById('submitCheckinBtn');
                const checkoutBtn = document.getElementById('submitCheckoutBtn');
                
                if (!checkinBtn.disabled) {
                    checkinBtn.disabled = !isWithinRadius;
                }
                if (!checkoutBtn.disabled) {
                    checkoutBtn.disabled = !isWithinRadius;
                }

            } catch (error) {
                document.getElementById('gpsStatus').innerHTML = `<i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i><span>Error: ${error.message}</span>`;
                showToast('Gagal mendapatkan lokasi GPS', 'error');
            }
        }

        function cancelAttendance() {
            document.getElementById('attendanceForm').classList.add('hidden');
            document.getElementById('mainControls').classList.remove('hidden');
            document.getElementById('agreeCheckbox').checked = false;
            
            // Restart camera
            startCamera();
        }

        async function submitAttendance(type) {
            // Validation
            if (!document.getElementById('agreeCheckbox').checked) {
                showToast('Silakan setujui persyaratan terlebih dahulu', 'warning');
                return;
            }

            if (!currentSelfie) {
                showToast('Silakan ambil selfie terlebih dahulu', 'warning');
                return;
            }

            if (!currentGpsPosition) {
                showToast('Lokasi GPS tidak tersedia', 'error');
                return;
            }

            if (!isWithinRadius) {
                showToast('Anda berada di luar radius kantor', 'error');
                return;
            }

            // Show loading
            const loadingText = type === 'checkin' ? 'Mengirim absensi masuk...' : 'Mengirim absensi pulang...';
            document.getElementById('loadingText').textContent = loadingText;
            document.getElementById('loadingOverlay').classList.remove('hidden');

            try {
                // Convert dataUrl to File
                const blob = cameraHandler.dataUrlToBlob(currentSelfie);
                const fileName = `selfie_${type}_${Date.now()}.jpg`;
                const file = new File([blob], fileName, { type: 'image/jpeg' });

                // Create FormData
                const formData = new FormData();
                formData.append('type', type);
                formData.append('selfie_image', file);
                formData.append('gps_lat', currentGpsPosition.latitude);
                formData.append('gps_lon', currentGpsPosition.longitude);

                // Submit
                const response = await fetch('/karyawan/process_absensi.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                // Hide loading
                document.getElementById('loadingOverlay').classList.add('hidden');

                if (result.success) {
                    showToast(result.message, 'success');
                    
                    // Jika ada data rekapan (checkout), tampilkan summary modal
                    if (type === 'checkout' && result.data) {
                        showRekapanModal(result.data);
                    } else {
                        // Refresh page after 2 seconds
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }
                } else {
                    showToast('Error: ' + result.message, 'error');
                }
            } catch (error) {
                document.getElementById('loadingOverlay').classList.add('hidden');
                showToast('Terjadi kesalahan: ' + error.message, 'error');
            }
        }

        // Toast notification function
        function showToast(message, type = 'info') {
            // Remove existing toast
            const existingToast = document.querySelector('.toast-notification');
            if (existingToast) existingToast.remove();

            // Create toast
            const toast = document.createElement('div');
            toast.className = `toast-notification fixed top-4 right-4 md:right-auto md:left-1/2 md:-translate-x-1/2 z-50 px-6 py-3 rounded-xl shadow-lg text-white font-medium max-w-md transform transition-all duration-300 translate-y-0 opacity-100`;
            
            // Set color based on type
            switch(type) {
                case 'success':
                    toast.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                    break;
                case 'error':
                    toast.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                    break;
                case 'warning':
                    toast.style.background = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';
                    break;
                default:
                    toast.style.background = 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)';
            }
            
            toast.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'} mr-3"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Auto remove after 4 seconds
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 300);
            }, 4000);
        }

        // Refresh GPS position every 10 seconds
        setInterval(getGPSPosition, 10000);
        
        // Rekapan Modal Functions
        function showRekapanModal(data) {
            // Format currency
            const formatCurrency = (num) => {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0
                }).format(num);
            };

            // Populate modal
            document.getElementById('rekapNama').textContent = data.nama || '-';
            document.getElementById('rekapTanggalMasuk').textContent = data.tanggal_masuk || '--';
            document.getElementById('rekapMasuk').textContent = data.jam_masuk || '--:--';
            document.getElementById('rekapTanggalPulang').textContent = data.tanggal_pulang || '--';
            document.getElementById('rekapPulang').textContent = data.jam_pulang || '--:--';
            document.getElementById('rekapTotalJam').textContent = (data.total_jam || 0).toFixed(2) + ' jam';
            document.getElementById('rekapLembur').textContent = (data.jam_lembur || 0).toFixed(2) + ' jam';
            document.getElementById('rekapGaji').textContent = formatCurrency(data.gaji_harian || 0);
            document.getElementById('rekapTunjangan').textContent = formatCurrency(data.tunjangan_lembur || 0);

            // Show modal
            document.getElementById('rekapanModal').classList.remove('hidden');
        }

        function closeRekapanModal() {
            document.getElementById('rekapanModal').classList.add('hidden');
            // Reload page after closing
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
        
        // Add pull-to-refresh functionality
        let touchStartY = 0;
        document.addEventListener('touchstart', (e) => {
            touchStartY = e.touches[0].clientY;
        }, {passive: true});
        
        document.addEventListener('touchend', (e) => {
            const touchEndY = e.changedTouches[0].clientY;
            const diff = touchStartY - touchEndY;
            
            // If pull down from top
            if (window.scrollY === 0 && diff < -50) {
                getGPSPosition();
                showToast('Memperbarui lokasi...', 'info');
            }
        }, {passive: true});
    </script>
</body>
</html>