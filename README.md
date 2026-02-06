# 📋 SISTEM ABSENSI BERBASIS WEB

Sistem absensi terintegrasi dengan selfie verification dan GPS tracking menggunakan PHP Native, MySQL, dan Tailwind CSS.

---

## 🎯 FITUR UTAMA

### 3 Role User:
1. **Admin** - Full control: kelola user, pengaturan GPS/jam kerja, lihat semua laporan
2. **HRD** - Read-only: lihat rekap absensi, gaji, foto selfie
3. **Karyawan** - Absensi daily: selfie + GPS, lihat riwayat, ubah profil

### Teknologi:
- **Backend:** PHP Native (tanpa framework)
- **Database:** MySQL
- **Frontend:** HTML + Tailwind CSS
- **JavaScript:** Camera API + Geolocation API
- **Security:** Bcrypt password hashing, prepared statements, session management

---

## 📁 STRUKTUR FOLDER

```
/absensi1
├── config/
│   ├── database.php          # Koneksi database & helper functions
│   ├── auth.php              # Session & authentication
│   └── helper.php            # Utility functions (GPS, gaji, dll)
│
├── auth/
│   ├── login.php             # Halaman login
│   ├── login_process.php     # Process login
│   └── logout.php            # Logout
│
├── admin/
│   ├── dashboard.php         # Admin dashboard
│   ├── users.php             # CRUD user management
│   ├── setting.php           # GPS & jam kerja settings
│   └── laporan.php           # Attendance & salary reports
│
├── hrd/
│   ├── dashboard.php         # HRD dashboard
│   └── laporan.php           # Read-only reports
│
├── karyawan/
│   ├── dashboard.php         # Employee dashboard
│   ├── absensi.php           # Attendance check-in/out
│   ├── riwayat.php           # Attendance history
│   ├── profil.php            # User profile & password change
│   └── process_absensi.php   # Process attendance submission
│
├── uploads/
│   └── selfie/               # Selfie image storage
│
├── assets/
│   ├── css/
│   │   └── tailwind.css      # Tailwind styling
│   └── js/
│       ├── camera.js         # Camera utility class
│       └── gps.js            # GPS utility class
│
├── index.php                 # Main index (redirect to login/dashboard)
└── database.sql              # SQL schema & sample data
```

---

## 🚀 INSTALASI & SETUP

### 1. Prerequisites
- PHP 7.4+ dengan extension `mysqli`
- MySQL 5.7+
- Browser modern yang support Camera API & Geolocation

### 2. Clone Repository
```bash
cd d:\PROJECT-APLIKASI\absensi1
```

### 3. Setup Database

**Option A: Using Command Line**
```bash
mysql -u root -p < database.sql
```

**Option B: Using phpMyAdmin**
1. Buka phpMyAdmin
2. Buat database baru: `absensi_db`
3. Import file `database.sql`

### 4. Konfigurasi Database

Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'absensi_db');
```

### 5. Setup Web Server

**Menggunakan PHP Built-in Server:**
```bash
php -S localhost:8000
```

**Menggunakan Apache:**
- Pastikan document root mengarah ke folder `absensi1/`
- Aktifkan module `mod_rewrite`

### 6. Akses Aplikasi

Buka browser ke: `http://localhost:8000`

---

## 👥 AKUN DEMO

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@absensi.com | admin123 |
| HRD | hrd@absensi.com | admin123 |
| Karyawan | budi@absensi.com | admin123 |

---

## 📱 FITUR KARYAWAN

### 1. Absensi Masuk/Pulang
- ✅ Akses kamera browser untuk selfie
- ✅ GPS tracking (harus dalam radius kantor)
- ✅ Validasi GPS di backend
- ✅ Simpan metadata lokasi & waktu

### 2. Dashboard Karyawan
- 📊 Statistik bulanan (hari kerja, jam, lembur)
- 🕐 Status absensi hari ini
- 💰 Estimasi gaji bulanan
- 📜 Riwayat absensi terbaru

### 3. Riwayat Absensi
- 📅 Filter per bulan & tahun
- 📊 Detail setiap hari (masuk, pulang, jam, lembur)
- 💵 Perhitungan gaji per hari
- 🖼️ Preview foto selfie

### 4. Profil & Password
- 👤 Edit nama profil
- 🔑 Ubah password dengan verifikasi password lama

---

## 🔧 FITUR ADMIN

### 1. Kelola User (CRUD)
- ➕ Tambah user baru (Karyawan/HRD/Admin)
- ✏️ Edit data user (nama, email, gaji, tarif lembur)
- 🔄 Reset password user
- 🗑️ Hapus user

### 2. Pengaturan Sistem
- 📍 **GPS Kantor:** Set latitude, longitude, radius
- ⏱️ **Jam Kerja Standar:** Default 8 jam/hari
- 🏢 **Informasi Perusahaan:** Nama & alamat kantor

### 3. Laporan Lengkap
- 📋 Detail absensi per hari
- 💼 Ringkasan gaji per karyawan
- 📊 Filter per bulan, tahun, karyawan
- 🧮 Perhitungan otomatis (jam kerja, lembur, gaji)

---

## 💫 FITUR HRD

### 1. Dashboard
- 📊 Statistik hari ini (hadir, tidak hadir, tingkat kehadiran)
- 👥 Total karyawan
- 📋 Absensi hari ini

### 2. Laporan Read-Only
- 📜 Laporan Absensi (filter per bulan/tahun/karyawan)
- 💰 Laporan Gaji (ringkasan per karyawan)
- 🖼️ View foto selfie

---

## 🧮 RUMUS PERHITUNGAN

### Jam Kerja
```
total_jam = jam_pulang - jam_masuk
```

### Lembur
```
IF total_jam > 8:
    jam_lembur = total_jam - 8
ELSE:
    jam_lembur = 0
```

### Gaji Harian
```
total_gaji = gaji_harian + (jam_lembur × tarif_lembur_per_jam)
```

---

## 🔐 KEAMANAN

### Implementasi:
- ✅ **Password Hashing:** Bcrypt dengan cost = 10
- ✅ **Session Management:** 2 hours timeout
- ✅ **Prepared Statements:** Semua query database
- ✅ **Input Sanitization:** htmlspecialchars() untuk HTML output
- ✅ **Role-Based Access:** Middleware auth per halaman
- ✅ **File Upload Validation:** Tipe file, ukuran max 2MB
- ✅ **GPS Validation:** Verifikasi radius di backend
- ✅ **CSRF Protection:** Via session token (tambahan)

---

## 📸 GPS & CAMERA

### Geolocation API
```javascript
// Get current position
const position = await gpsHandler.getCurrentPosition();
console.log(position.latitude, position.longitude);

// Check if within office radius
const isInside = GPSHandler.isWithinRadius(
    currentLat, currentLon,
    officeLat, officeLon,
    radius
);
```

### Camera API
```javascript
// Start camera
await cameraHandler.start();

// Capture photo
const photoData = cameraHandler.capture(); // Returns data URL

// Convert to blob
const blob = cameraHandler.dataUrlToBlob(photoData);
```

---

## 🗄️ SKEMA DATABASE

### Tabel: users
| Column | Type | Keterangan |
|--------|------|-----------|
| id | INT | Primary key |
| nama | VARCHAR | Nama lengkap |
| email | VARCHAR | Email unik |
| password | VARCHAR | Hash bcrypt |
| role | ENUM | admin/hrd/karyawan |
| gaji_harian | DECIMAL | Gaji per hari |
| tarif_lembur_per_jam | DECIMAL | Tarif lembur/jam |
| status | ENUM | aktif/nonaktif |
| created_at | TIMESTAMP | Waktu buat |

### Tabel: absensi
| Column | Type | Keterangan |
|--------|------|-----------|
| id | INT | Primary key |
| user_id | INT | Foreign key to users |
| tanggal | DATE | Tanggal absensi |
| jam_masuk | TIME | Jam check-in |
| jam_pulang | TIME | Jam check-out |
| total_jam | DECIMAL | Total jam kerja |
| jam_lembur | DECIMAL | Jam lembur |
| selfie_masuk | VARCHAR | Filename fotoo masuk |
| selfie_pulang | VARCHAR | Filename foto pulang |
| lat_masuk | DECIMAL | Latitude masuk |
| lng_masuk | DECIMAL | Longitude masuk |
| lat_pulang | DECIMAL | Latitude pulang |
| lng_pulang | DECIMAL | Longitude pulang |
| status | ENUM | belum_absen/absen_masuk/selesai |

### Tabel: settings
| Column | Type | Keterangan |
|--------|------|-----------|
| id | INT | Primary key |
| nama_setting | VARCHAR | Nama setting |
| value | TEXT | Value setting |

---

## 🐛 TROUBLESHOOTING

### Camera tidak berfungsi
- ✅ Pastikan browser support Camera API (Chrome, Firefox, Safari 14+)
- ✅ Akses via HTTPS (camera API memerlukan secure context di production)
- ✅ Berikan izin akses kamera saat diminta browser

### GPS tidak akurat
- ✅ Gunakan device dengan GPS (mobile/tablet)
- ✅ Aktifkan location services di device
- ✅ Buka halaman absensi di browser native (bukan iframe)
- ✅ Tunggu 5-10 detik untuk GPS fix

### Database connection error
- ✅ Pastikan MySQL running
- ✅ Check kredensial di `config/database.php`
- ✅ Pastikan database `absensi_db` sudah dibuat

### Session logout otomatis
- ✅ Session timeout default 2 jam (ubah di `config/auth.php`)
- ✅ User akan di-redirect ke login jika session expired

---

## 📝 FILE UTAMA

### config/database.php (312 lines)
- Koneksi mysqli
- Helper functions: `getRow()`, `getRows()`, `executeModifyQuery()`
- Query execution dengan prepared statements

### config/auth.php (106 lines)
- Session management
- Login/logout functions
- Password hashing & verification
- Role checking functions

### config/helper.php (289 lines)
- GPS validation & distance calculation
- File upload validation
- Working hours & salary calculation
- Currency formatting
- Input sanitization

### auth/login.php (127 lines)
- Login form Tailwind styled
- Demo credentials display
- Error/success messages

### karyawan/absensi.php (297 lines)
- Camera preview & capture
- GPS real-time status
- Selfie preview & confirmation
- Form submission dengan FormData API

### admin/laporan.php (459 lines)
- Filter per bulan/tahun/karyawan
- Employee salary summary table
- Detailed daily attendance table
- Auto calculations

---

## 🎨 STYLING

- **Framework:** Tailwind CSS 3.x via CDN
- **Mobile-First:** Responsive design
- **Color Scheme:**
  - Admin: Red gradient
  - HRD: Purple gradient
  - Karyawan: Blue gradient
  - Success: Green
  - Error: Red
  - Warning: Yellow

---

## ✅ CHECKLIST FITUR

- [x] Login system dengan bcrypt
- [x] 3 role user (Admin, HRD, Karyawan)
- [x] Admin: CRUD user, settings GPS/jam kerja
- [x] Karyawan: Selfie + GPS absensi
- [x] Auto perhitungan jam kerja & lembur & gaji
- [x] Laporan absensi & gaji
- [x] Photo storage di uploads/selfie/
- [x] Mobile-responsive design
- [x] Session management & security
- [x] Prepared statements (anti SQL injection)
- [x] Email validation
- [x] GPS radius validation

---

## 📞 SUPPORT

Untuk masalah atau pertanyaan, silakan check:
1. File `database.sql` untuk struktur database
2. Folder `config/` untuk konfigurasi
3. Comment di setiap file PHP untuk logika detail

---

## 📄 LICENSE

Sistem Absensi © 2026. All rights reserved.

---

**Happy Coding! 🚀**
#   e s e n s i - j a l a d a r a  
 