# PANDUAN INSTALASI SISTEM ABSENSI

## 📋 PERSYARATAN SISTEM

### Server Requirements:
- **PHP:** 7.4 atau lebih tinggi
- **MySQL:** 5.7 atau MariaDB 10.2+
- **Web Server:** Apache 2.4+ atau Nginx
- **Extensions:** mysqli, GD (optional untuk image processing)

### Browser Requirements (Client):
- Chrome 67+
- Firefox 55+
- Safari 14.1+
- Edge 79+
- Mobile: Safari iOS 14+ atau Chrome Android

### Features Needed:
- ✅ Camera API support
- ✅ Geolocation API support
- ✅ localStorage support
- ✅ HTTPS (recommended untuk production)

---

## 🛠️ INSTALLATION STEPS

### Step 1: Download & Extract
```bash
# Navigate to project
cd d:\PROJECT-APLIKASI\absensi1

# Verify folder structure
dir
```

### Step 2: Database Setup

**Option A: Command Line (Recommended)**
```bash
# Login to MySQL
mysql -u root -p

# In MySQL console:
source database.sql;

# Verify
USE absensi_db;
SHOW TABLES;
SELECT COUNT(*) FROM users;
```

**Option B: phpMyAdmin**
1. Go to: http://localhost/phpmyadmin
2. Create new database: `absensi_db`
3. Import `database.sql` file
4. Verify tables and demo data

**Option C: MySQL Workbench**
1. New Query Tab
2. Open `database.sql`
3. Execute (Ctrl+Enter)

### Step 3: Configure Database Connection

Edit `config/database.php`:
```php
<?php
define('DB_HOST', 'localhost');      // MySQL host
define('DB_USER', 'root');           // MySQL user
define('DB_PASSWORD', '');           // MySQL password (empty if no password)
define('DB_NAME', 'absensi_db');     // Database name
define('DB_PORT', 3306);             // MySQL port
```

### Step 4: Set Folder Permissions

**Windows (Less critical):**
```bash
icacls "uploads" /grant Users:F /T
```

**Linux/Mac:**
```bash
chmod 755 uploads/
chmod 755 uploads/selfie/
chmod 755 config/
```

### Step 5: Configure Web Server

#### Option A: PHP Built-in Server (Development)
```bash
cd d:\PROJECT-APLIKASI\absensi1

# Start server on port 8000
php -S localhost:8000

# Or custom port
php -S localhost:8080
```

#### Option B: Apache (Development/Production)

**Windows (XAMPP/WAMP):**
1. Copy project to `htdocs/` or `www/` folder
2. Restart Apache
3. Access: `http://localhost/absensi1`

**Linux (Apache):**
```bash
# Create vhost config
sudo nano /etc/apache2/sites-available/absensi.conf

# Add:
<VirtualHost *:80>
    ServerName absensi.local
    DocumentRoot /var/www/html/absensi1
    <Directory /var/www/html/absensi1>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

# Enable site & rewrite module
sudo a2enmod rewrite
sudo a2ensite absensi
sudo systemctl reload apache2

# Add to /etc/hosts
127.0.0.1 absensi.local
```

Access: `http://absensi.local`

#### Option C: Nginx (Production)

```nginx
server {
    listen 80;
    server_name absensi.example.com;
    root /var/www/html/absensi1;
    index index.php;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fasturi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Step 6: First Time Access

1. Open browser: `http://localhost:8000`
2. Auto-redirect ke login page
3. Login with demo account:
   - Email: `admin@absensi.com`
   - Password: `admin123`

---

## ✅ VERIFICATION CHECKLIST

### Database
- [ ] Database `absensi_db` created
- [ ] 3 tables: users, absensi, settings
- [ ] Demo data loaded (5 users)
- [ ] Connection successful

### File System
- [ ] All files readable
- [ ] `uploads/selfie/` folder writable
- [ ] `config/` folder readable
- [ ] `.htaccess` file present

### Server
- [ ] PHP 7.4+ running
- [ ] MySQLi extension enabled
- [ ] GD extension available (optional)

### Application
- [ ] Login page loads
- [ ] Can login with demo account
- [ ] Admin dashboard accessible
- [ ] Camera/GPS prompts working

---

## 🔍 TROUBLESHOOTING

### "Connection Failed" Error

**Cause:** Database connection failed

**Solution:**
```php
// test_connection.php
<?php
$conn = new mysqli('localhost', 'root', '', 'absensi_db');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
echo 'Connected successfully';
$conn->close();
?>
```

### "No tables" in database

**Cause:** database.sql not imported correctly

**Solution:**
```bash
# Via command line
mysql -u root -p absensi_db < database.sql

# Verify
mysql -u root -p -D absensi_db -e "SHOW TABLES;"
```

### Camera not working

**Requirements:**
- Use HTTPS in production
- Grant camera permission when prompted
- Check if browser supports Camera API

**Debug:**
```javascript
// Open browser console (F12)
navigator.mediaDevices.enumerateDevices().then(devices => {
    devices.forEach(device => console.log(device.kind, device.label));
});
```

### GPS not working

**Requirements:**
- Using mobile device or enable location in dev tools
- Grant geolocation permission
- Wait 5-10 seconds for GPS fix

**Debug:**
```javascript
navigator.geolocation.getCurrentPosition(
    position => console.log(position.coords),
    error => console.log('Error:', error.message)
);
```

### "Permission Denied" on uploads folder

**Linux:**
```bash
sudo chown -R www-data:www-data /var/www/html/absensi1/uploads
sudo chmod 755 /var/www/html/absensi1/uploads
sudo chmod 755 /var/www/html/absensi1/uploads/selfie
```

**Windows:**
- Right-click folder → Properties → Security
- Add read/write permissions for IIS_IUSRS or Apache user

### Module rewrite not enabled

**Apache:**
```bash
# Linux
sudo a2enmod rewrite

# Windows (XAMPP)
# Uncomment: LoadModule rewrite_module modules/mod_rewrite.so
# In: apache/conf/httpd.conf
```

### Session not persisting

**Cause:** Session directory not writable

**Solution:**
```bash
# Check session path
php -i | grep session.save_path

# Create & chmod if needed
mkdir -p /var/lib/php/sessions
chmod 1733 /var/lib/php/sessions
```

---

## 📚 TEST SCENARIOS

### Scenario 1: Admin User Management
1. Login as admin@absensi.com
2. Go to: Admin → Kelola User
3. Add new user (Karyawan)
4. Edit gaji & tarif lembur
5. Reset password
6. Verify all working

### Scenario 2: Employee Attendance
1. Login as karyawan
2. Go to: Absensi
3. Allow camera & GPS permissions
4. Take selfie
5. Confirm check-in
6. Verify in dashboard

### Scenario 3: Report Generation
1. Login as admin/hrd
2. Go to: Laporan
3. Filter by month/year
4. Check calculations
5. Export if available

### Scenario 4: Mobile Testing
1. Access via mobile
2. Take attendance with device camera
3. Verify GPS tracking
4. Check responsive design
5. Test all touch interactions

---

## 🔐 SECURITY CHECKLIST

- [ ] Database password set (not empty)
- [ ] PHP error display disabled in production
- [ ] File permissions correct (not 777)
- [ ] `.htaccess` in place for Apache
- [ ] HTTPS enabled in production
- [ ] Session timeout configured
- [ ] Input validation in all forms
- [ ] SQL injection prevention (prepared stmt)
- [ ] XSS protection headers set
- [ ] CSRF tokens implemented (optional)

---

## 📊 ADMIN FIRST TIME SETUP

After login as admin:

1. **Set Office Location:**
   - Admin → Pengaturan
   - Enter GPS coordinates (latitude, longitude)
   - Set radius (default 100 meters)

2. **Configure Working Hours:**
   - Set standard hours (default 8 jam/hari)

3. **Add Employees:**
   - Admin → Kelola User
   - Add karyawan with:
     - Name, email, password
     - Daily salary
     - Overtime rate per hour

4. **Configure Salary:**
   - Edit each employee:
     - Set gaji_harian
     - Set tarif_lembur_per_jam

5. **Test System:**
   - Login as karyawan
   - Take attendance
   - View dashboard
   - Check calculations

---

## 📞 SUPPORT & DOCUMENTATION

- **Database Schema:** See `database.sql`
- **API Documentation:** See code comments in each PHP file
- **Frontend Guide:** See asset files in `assets/js/`
- **Troubleshooting:** This file
- **Features:** See `README.md`

---

## 🎉 READY TO USE!

Your system is now ready:
✅ Database configured
✅ Server running
✅ Files in place
✅ Admin created
✅ Demo accounts ready

**Start using at:** http://localhost:8000

---

**Last Updated:** February 5, 2026
**Version:** 1.0.0
