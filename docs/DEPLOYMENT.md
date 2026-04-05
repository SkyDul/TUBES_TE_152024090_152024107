# Panduan Deploy ke aaPanel (STB)

Panduan lengkap untuk deploy sistem billing hotspot ke aaPanel di STB.

---

## 📋 Prasyarat

- aaPanel sudah terinstall di STB
- PHP 7.4+ dengan ekstensi: `curl`, `pdo_mysql`, `json`
- MySQL/MariaDB
- Domain sudah diarahkan ke IP STB
- SSL Certificate (Let's Encrypt via aaPanel)

---

## 🚀 Langkah-Langkah Deploy

### 1. Upload File Project

**Opsi A: Via aaPanel File Manager**
1. Buka aaPanel → File Manager
2. Navigate ke `/www/wwwroot/billing.yourdomain.com/`
3. Upload semua file dari folder `e:\laragon\www\biling\`

**Opsi B: Via FTP/SFTP**
```bash
# Dari Windows, gunakan WinSCP atau FileZilla
# Upload ke: /www/wwwroot/billing.yourdomain.com/
```

**Opsi C: Via Git (Recommended)**
```bash
cd /www/wwwroot/billing.yourdomain.com
git clone https://github.com/your-repo/biling.git .
```

---

### 2. Setup Website di aaPanel

1. **Website → Add site**
   - Domain: `billing.yourdomain.com`
   - Root Directory: `/www/wwwroot/billing.yourdomain.com`
   - PHP Version: 7.4 atau lebih baru
   - Database: MySQL (akan dibuat otomatis)

2. **SSL Certificate**
   - Klik domain → SSL → Let's Encrypt
   - Centang "Force HTTPS"

3. **PHP Extensions**
   - Pastikan ekstensi ini aktif:
     - `curl`
     - `pdo_mysql`
     - `json`
     - `mbstring`

---

### 3. Setup Database

1. **Buat Database** (jika belum)
   - aaPanel → Database → Add database
   - Name: `biling_hotspot`
   - Username: `biling_user`
   - Password: (catat password ini!)

2. **Import Schema**
   - Klik database → phpMyAdmin
   - Import file: `database/schema.sql`

   Atau via SSH:
   ```bash
   mysql -u biling_user -p biling_hotspot < /www/wwwroot/billing.yourdomain.com/database/schema.sql
   ```

---

### 4. Setup Environment Variables (PENTING!)

**Buat folder private di LUAR public directory:**

```bash
mkdir -p /www/private
chmod 700 /www/private
```

**Buat file `/www/private/.env`:**

```bash
nano /www/private/.env
```

Isi dengan:
```env
# Mode Aplikasi
APP_ENV=production
APP_DEBUG=false

# Midtrans (dari dashboard.midtrans.com)
MIDTRANS_IS_PRODUCTION=true
MIDTRANS_SERVER_KEY=Mid-server-XXXXXXXXXXXXXXXXXXXXXXXX
MIDTRANS_CLIENT_KEY=Mid-client-XXXXXXXXXXXXXXXXXXXXXXXX

# Mikrotik (IP LOKAL dari STB ke router)
MIKROTIK_HOST=192.168.88.1
MIKROTIK_PORT=8728
MIKROTIK_USER=api_billing
MIKROTIK_PASS=YourSecurePassword

# Database (sesuaikan dengan yang dibuat di aaPanel)
DB_HOST=localhost
DB_PORT=3306
DB_NAME=biling_hotspot
DB_USER=biling_user
DB_PASS=YourDatabasePassword

# URL Aplikasi
APP_URL=https://billing.yourdomain.com

# Durasi transaksi (menit)
TRANSACTION_EXPIRE_MINUTES=15
```

**Set permission:**
```bash
chmod 600 /www/private/.env
chown www:www /www/private/.env
```

---

### 5. Setup Midtrans Dashboard

1. Login ke [dashboard.midtrans.com](https://dashboard.midtrans.com)

2. **Settings → Configuration:**
   - Payment Notification URL: `https://billing.yourdomain.com/api/notification.php`
   - Finish Redirect URL: `https://billing.yourdomain.com/success.php`
   - Error Redirect URL: `https://billing.yourdomain.com/`

3. **Settings → Access Keys:**
   - Copy Server Key dan Client Key
   - Paste ke file `/www/private/.env`

---

### 6. Setup User Mikrotik (PENTING!)

Buat user khusus di Mikrotik untuk API (bukan admin!):

**Via Winbox:**
1. System → Users → Add
   - Name: `api_billing`
   - Group: `api` (atau buat group baru)
   - Password: (password yang kuat)

2. System → Users → Groups → Add (jika perlu)
   - Name: `api`
   - Policy: Centang hanya `read`, `write`, `api`
   - Jangan centang: `ftp`, `ssh`, `telnet`, `winbox`, `web`, `reboot`, `policy`

**Via Terminal:**
```
/user group add name=api_group policy=read,write,api,!ftp,!ssh,!telnet,!winbox,!web,!reboot,!policy
/user add name=api_billing password=YourSecurePassword group=api_group
```

---

### 7. Testing

1. **Test Homepage:**
   ```
   https://billing.yourdomain.com/
   ```
   Harus tampil daftar paket voucher.

2. **Test API Packages:**
   ```
   https://billing.yourdomain.com/api/get-packages.php
   ```
   Harus return JSON.

3. **Test Payment Flow (Sandbox dulu!):**
   - Ubah `MIDTRANS_IS_PRODUCTION=false` di `.env`
   - Gunakan Midtrans Sandbox untuk test
   - Setelah berhasil, ubah ke `true`

4. **Monitor Logs:**
   ```bash
   tail -f /www/wwwlogs/billing.yourdomain.com.error.log
   ```

---

## 🔒 Checklist Keamanan

- [ ] File `.env` di folder `/www/private/` (bukan di public)
- [ ] SSL/HTTPS aktif dan force redirect
- [ ] `APP_DEBUG=false` di production
- [ ] User Mikrotik dengan permission terbatas
- [ ] Folder `includes/`, `config/`, `database/` tidak bisa diakses web
- [ ] Notification URL Midtrans sudah didaftarkan

---

## 🔧 Troubleshooting

### Error "Database connection failed"
- Cek kredensial database di `/www/private/.env`
- Pastikan database sudah di-import

### Error "Mikrotik connection failed"
- Cek IP Mikrotik bisa diakses dari STB: `ping 192.168.88.1`
- Cek port API terbuka: `telnet 192.168.88.1 8728`
- Cek username/password Mikrotik

### Callback Midtrans tidak masuk
- Cek Notification URL di dashboard Midtrans
- Cek SSL valid (Let's Encrypt)
- Lihat log: `tail -f /www/wwwlogs/billing.yourdomain.com.error.log`

### QR Code tidak muncul
- Cek Server Key Midtrans
- Cek mode (Sandbox vs Production)
- Lihat response di browser console (F12)

---

## 📱 Customization

### Ubah Paket Voucher
Edit via phpMyAdmin atau MySQL:
```sql
INSERT INTO paket_voucher (nama_paket, harga, mikrotik_profile, durasi_hari, durasi_display) 
VALUES ('Nama Paket', 10000, 'nama_profile_mikrotik', 7, '7 Hari');
```

### Ubah Warna/Tampilan
Edit file: `/www/wwwroot/billing.yourdomain.com/assets/css/style.css`

---

## 📞 Support

Jika ada kendala, periksa:
1. Error log aaPanel
2. PHP error log
3. Browser console (F12)
