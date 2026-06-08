# Panduan Migrasi ke Hosting cPanel

Dokumen ini memandu Anda dalam memigrasikan POS Website Starter ke hosting cPanel (seperti Niagahoster, DomainSia, Hostinger, dll).

## 📋 Checklist Pre-Migration

- [ ] Backup database lokal: `mysqldump -u root pos_db > backup_local.sql`
- [ ] Backup folder aplikasi lokal
- [ ] Pastikan versi PHP di hosting minimal 7.4+ (cek di cPanel)
- [ ] Test aplikasi di lokal sebelum upload

---

## 1️⃣ Login ke cPanel

1. Akses: `https://yourdomain.com:2083` atau URL yang diberikan hosting
2. Username: akun cPanel Anda
3. Password: password cPanel Anda

---

## 2️⃣ Setup Database di cPanel

### Via cPanel GUI:

1. **Buka MySQL Databases**
   - Di cPanel, cari menu "MySQL Databases"
   
2. **Buat Database Baru**
   - Database Name: `pos_db` (atau pilih nama lain)
   - Klik "Create Database"
   - Catat nama lengkap: `your_cpanel_username_pos_db`

3. **Buat MySQL User Baru**
   - Username: `your_cpanel_username_posuser` (auto-prefixed)
   - Password: **Gunakan password KUAT** (minimal 16 karakter)
   - Klik "Create User"

4. **Berikan Permission ke User**
   - Di bagian "Add User to Database"
   - Pilih user dan database yang baru dibuat
   - Centang semua privilege atau minimal: SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER
   - Klik "Add"

### Catat Informasi Penting:
```
Database Name: your_cpanel_username_pos_db
Database User: your_cpanel_username_posuser
Database Pass: [password yang Anda buat]
Database Host: localhost (atau IP yang diberikan)
```

---

## 3️⃣ Import Database Schema

1. **Buka phpMyAdmin di cPanel**
   - Menu: "phpMyAdmin"
   - Login dengan user MySQL yang baru dibuat

2. **Import Database**
   - Pilih database `pos_db`
   - Klik tab "Import"
   - Pilih file `pos_schema.sql` dari repository
   - Klik "Go"
   - Tunggu hingga berhasil ✅

---

## 4️⃣ Upload File ke Hosting

### Opsi A: Via FTP/SFTP (Recommended)

1. **Download FTP Client**
   - FileZilla (gratis): https://filezilla-project.org/
   - atau WinSCP

2. **Ambil Kredensial FTP dari cPanel**
   - Menu: "FTP Accounts"
   - Buat account atau gunakan main account
   - Catat: FTP Host, Username, Password, Port (biasanya 21 atau 22 untuk SFTP)

3. **Koneksikan ke Server**
   ```
   Host: your_ftp_host
   Username: your_ftp_username
   Password: your_ftp_password
   Port: 21 (FTP) atau 22 (SFTP)
   ```

4. **Navigasi ke Folder Deployment**
   - Masuk ke folder: `public_html/` atau `public_html/pos/` (sesuai preferensi)
   
5. **Upload Semua File**
   - Copy-paste semua file dari repository ke folder remote
   - Abaikan folder: `.git/`, `node_modules/` (jika ada)
   - Pastikan `index.php` dan `config.php` terupload

### Opsi B: Via Git (Lebih Advanced)

1. **Terminal SSH dari cPanel**
   - Menu: "Terminal"
   
2. **Clone Repository**
   ```bash
   cd public_html
   git clone https://github.com/Malik9144/pos-website-starter.git pos
   cd pos
   ```

---

## 5️⃣ Konfigurasi `config.php`

1. **Via FTP**: Download `config.php`, edit lokal, upload kembali
2. **Via cPanel File Manager**: Edit langsung

**Ubah nilai berikut:**

```php
<?php
// Database configuration - SESUAIKAN!
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'your_cpanel_username_pos_db');  // <- Ganti
if (!defined('DB_USER')) define('DB_USER', 'your_cpanel_username_posuser');  // <- Ganti
if (!defined('DB_PASS')) define('DB_PASS', 'your_strong_password');         // <- Ganti

// Base URL - SESUAIKAN dengan domain Anda!
if (!defined('BASE_URL')) define('BASE_URL', 'https://yourdomain.com/pos/');
// Atau jika di root:
// if (!defined('BASE_URL')) define('BASE_URL', 'https://yourdomain.com/');

// Store Information
if (!defined('STORE_NAME')) define('STORE_NAME', 'PT Kencana Lima Delapan');
if (!defined('STORE_ADDRESS')) define('STORE_ADDRESS', 'Alamat toko Anda');
if (!defined('STORE_PHONE')) define('STORE_PHONE', '0812-1234-5678');

// App Name
if (!defined('APP_NAME')) define('APP_NAME', 'POS Kencana Lima Delapan');

// Pajak (jika diperlukan, ubah sesuai negara)
if (!defined('PPN_DEFAULT')) define('PPN_DEFAULT', 0.11); // 11% untuk Indonesia

?>
```

---

## 6️⃣ Set Permission File

Via **cPanel > File Manager** atau **SSH Terminal**:

```bash
# Navigasi ke folder aplikasi
cd public_html/pos/

# Set permission untuk folders (755)
find . -type d -exec chmod 755 {} \;

# Set permission untuk files (644)
find . -type f -exec chmod 644 {} \;

# Jika ada folder yang perlu writable (uploads, cache, logs)
chmod 777 ./public/uploads/ 2>/dev/null || true
chmod 777 ./var/logs/ 2>/dev/null || true
chmod 777 ./temp/ 2>/dev/null || true
```

---

## 7️⃣ Test Aplikasi

1. **Akses di Browser**
   - Buka: `https://yourdomain.com/pos/`
   - Atau: `https://yourdomain.com/` (jika di root)

2. **Cek Koneksi Database**
   - Jika ada error koneksi database, verifikasi:
     - Username & password di `config.php` sudah benar
     - User sudah di-add ke database
     - Host adalah `localhost`

3. **Login**
   - Email: `admin@example.com`
   - Password: `admin123`
   - **⚠️ GANTI PASSWORD SEGERA** setelah login sukses

---

## 8️⃣ Keamanan Production

### A. Ubah Default Credentials
1. Login dengan admin
2. Menu: "Pengaturan Sistem" atau "Manage Users"
3. Ubah password admin ke password yang KUAT
4. Buat user admin baru jika perlu

### B. Aktifkan HTTPS (SSL)
1. **cPanel > AutoSSL** (gratis, Let's Encrypt)
2. Atau manual install via "SSL/TLS" menu
3. Test: akses `https://yourdomain.com` harus HIJAU ✅

### C. Backup Otomatis
1. **cPanel > Backup**
2. Set backup frequency (harian/mingguan)
3. Store backup di folder terpisah atau cloud

### D. Security Headers (opsional, via .htaccess)
Edit file `.htaccess` di root:

```apache
# Disable Directory Listing
Options -Indexes

# Protect config files
<FilesMatch "\.(env|ini|config)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Protect database dumps
<FilesMatch "\.sql$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Security Headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>
```

---

## 9️⃣ Troubleshooting

### ❌ Error: "Cannot connect to database"
- Verifikasi DB credentials di `config.php`
- Check MySQL user sudah di-add ke database
- Verifikasi host adalah `localhost`
- Restart MySQL (via cPanel > MySQL)

### ❌ Error: "Undefined index: SESSION_NAME"
- Pastikan `config.php` sudah terupload
- Check file tidak corrupt saat upload

### ❌ 404 Not Found
- Verifikasi BASE_URL di `config.php` benar
- Pastikan folder upload path sesuai

### ❌ Slow Loading / Timeout
- Hubungi support hosting untuk upgrade resource
- Atau gunakan caching plugin

### ❌ File Upload Tidak Bisa
- Check folder permissions (harus 755 atau 777)
- Verifikasi `upload/` folder ada
- Check PHP max upload size di cPanel

---

## 🔟 File Struktur yang Diperlukan

Pastikan struktur folder seperti ini di hosting:

```
public_html/pos/
├── index.php
├── config.php               ← PALING PENTING (sudah dikonfigurasi)
├── db.php
├── auth.php
├── dashboard.php
├── pos.php
├── manage_products.php
├── manage_stock.php
├── manage_users.php
├── reports.php
├── pos_schema.sql           ← Untuk import database
├── public/
│   ├── assets/
│   ├── uploads/
│   └── ...
├── .htaccess                ← Untuk security & URL rewriting
└── ... (file lain)
```

---

## 📞 Support

Jika ada error atau pertanyaan:
1. Check error di cPanel > Error Logs
2. Check aplikasi logs (jika ada di `/var/logs/`)
3. Hubungi support hosting
4. Open issue di GitHub repository

---

**Selamat! Aplikasi POS Anda sekarang live di hosting cPanel! 🎉**
