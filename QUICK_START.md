# ⚡ QUICK START GUIDE

## 🚀 Mulai dalam 5 Menit

### 1️⃣ Import Database
```bash
# Terminal/Command Prompt
mysql -u root -p < database.sql
```

### 2️⃣ Verifikasi Koneksi Database
Edit `config/database.php` - pastikan kredensial sesuai:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', ''); // Sesuaikan dengan password MySQL Anda
```

### 3️⃣ Jalankan Server
```bash
# Terminal di folder absensi1
php -S localhost:8000
```

### 4️⃣ Buka Browser
Pergi ke: **http://localhost:8000**

### 5️⃣ Login
Gunakan akun demo:
```
Email: admin@absensi.com
Password: admin123
```

---

## 📱 FEATURE WALKTHROUGH

### Sebagai Admin:
1. **Dashboard** - Lihat statistik hari ini
2. **Kelola User** - Tambah/edit/hapus karyawan
3. **Pengaturan GPS** - Set lokasi kantor & radius
4. **Laporan** - Lihat detail absensi & gaji

### Sebagai Karyawan:
1. **Dashboard** - Lihat status absensi
2. **Absensi** - Selfie + GPS check-in/out
3. **Riwayat** - Lihat history absensi & gaji
4. **Profil** - Edit nama & password

### Sebagai HRD:
1. **Dashboard** - Monitoring kehadiran
2. **Laporan** - View absensi & gaji (read-only)

---

## 🔐 AUTO-LOGIN ACCOUNTS

| Role | Email | Password |
|------|-------|----------|
| 👨‍💼 Admin | admin@absensi.com | admin123 |
| 👔 HRD | hrd@absensi.com | admin123 |
| 👨‍💻 Karyawan 1 | budi@absensi.com | admin123 |
| 👩‍💻 Karyawan 2 | siti@absensi.com | admin123 |
| 👨‍💼 Karyawan 3 | ahmad@absensi.com | admin123 |

---

## 📋 FIRST-TIME ADMIN SETUP

After login as admin:

### A. Verify GPS Location
1. Go: **Admin → Pengaturan**
2. Check GPS coordinates (default: Jakarta)
3. Adjust radius if needed (default: 100 meter)
4. **Save**

### B. Verify Working Hours
1. Go: **Pengaturan**
2. Check jam kerja standar (default: 8 jam)
3. **Save**

### C. Check Demo Employees
1. Go: **Admin → Kelola User**
2. Verify 3 demo karyawan exist
3. Check gaji_harian & tarif_lembur_per_jam

### D. Test as Karyawan
1. **Logout** & login as `budi@absensi.com`
2. Go: **Absensi**
3. Click "Buka Kamera"
4. Click "Ambil Foto"
5. Click "Absen Masuk"
6. Verify check-in successful

---

## 📱 TESTING ON MOBILE

### Prerequisites:
- Mobile dengan GPS & camera
- Browser: Chrome atau Safari
- Internet connection

### Steps:
1. Find local IP: `ipconfig getifaddr en0` (Mac) atau `ipconfig` (Windows)
2. Access: `http://192.168.x.x:8000` (dari mobile)
3. Login & test attendance

### Known Issues:
- Some Android devices need app permission for camera
- iOS Safari 14+ required for Camera API

---

## 🛠️ COMMON COMMANDS

### MySQL
```bash
# Connect to MySQL
mysql -u root -p

# Login to specific database
mysql -u root -p -D absensi_db

# Import SQL file
mysql -u root -p < database.sql

# Export database
mysqldump -u root -p absensi_db > backup.sql
```

### PHP Server
```bash
# Start on port 8000
php -S localhost:8000

# Start on custom port
php -S 0.0.0.0:3000

# Start with debug
php -d display_errors=1 -S localhost:8000
```

### File Permissions
```bash
# Linux: Make writable
chmod 755 uploads/
chmod 755 uploads/selfie/

# Linux: View permissions
ls -la uploads/
```

---

## 📁 FILE STRUCTURE QUICK REFERENCE

```
📦 absensi1/
 ├─ config/           💾 Database & Auth config
 ├─ auth/             🔑 Login pages
 ├─ admin/            👨‍💼 Admin pages
 ├─ hrd/              👔 HRD pages
 ├─ karyawan/         👨‍💻 Employee pages
 ├─ assets/           🎨 CSS & JS
 ├─ uploads/selfie/   📸 Selfie storage
 ├─ index.php         📄 Entry point
 ├─ database.sql      🗄️ Database schema
 ├─ README.md         📖 Full documentation
 └─ INSTALLATION.md   🛠️ Setup guide
```

---

## 🔍 VERIFY INSTALLATION

### Check 1: Database
```bash
mysql> USE absensi_db;
mysql> SHOW TABLES;
# Should show: absensi, settings, users
```

### Check 2: PHP
```bash
php -v
# Should show PHP 7.4+
```

### Check 3: Server Running
```bash
# Open: http://localhost:8000
# Should show login page
```

### Check 4: Demo Data
```bash
mysql> SELECT COUNT(*) FROM users;
# Should show: 5
```

---

## 🚨 QUICK TROUBLESHOOTING

| Problem | Solution |
|---------|----------|
| **"db connection failed"** | Check MySQL running, config/database.php credentials |
| **"Camera not working"** | Grant permission when browser asks, check browser support |
| **"GPS not accurate"** | Use mobile device, enable location, wait for fix |
| **"Page not loading"** | Check PHP server running: `php -S localhost:8000` |
| **"Can't upload file"** | Check `uploads/selfie/` folder exists & writable |
| **"404 page not found"** | Verify URL: http://locahost:8000 (not 8080 unless configured) |

---

## 🎯 NEXT STEPS

1. ✅ Import database
2. ✅ Start PHP server
3. ✅ Login with demo account
4. ✅ Explore admin features
5. ✅ Test karyawan attendance
6. ✅ Check employee dashboard
7. ✅ Review reports

---

## 📞 NEED HELP?

1. Check: `README.md` (full documentation)
2. Check: `INSTALLATION.md` (detailed setup)
3. Check: Code comments in PHP files
4. Check: Console (F12) for JavaScript errors

---

**Happy deploying! 🎉**

Selamat menggunakan Sistem Absensi!
