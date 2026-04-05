# 🛜 RipaNet Hotspot Billing System

Sistem billing voucher WiFi pintar dan otomatis yang terintegrasi langsung dengan **Midtrans** (QRIS/Online Payment) dan **Mikrotik RouterOS API**. Sistem ini juga dilengkapi dengan panel POS (Point of Sale) Kasir dan **AI Pendeteksi Keaslian/Nominal Uang Tunai** menggunakan arsitektur YOLOv8.

---

## ✨ Fitur Utama

- **🛒 Landing Page & Checkout:** Halaman publik yang elegan untuk pelanggan memilih dan membeli paket WiFi.
- **💳 Auto-Payment (Midtrans):** Mendukung pembayaran via QRIS, E-Wallet, dan Virtual Account. Voucher otomatis tercetak setelah lunas.
- **📡 Mikrotik API Integration:** Otomatis membuat _user_ hotspot di router Mikrotik dengan batasan waktu (uptime) yang sesuai dengan paket.
- **🖥️ POS Kasir (Admin Panel):** Dashboard khusus kasir untuk melayani pelanggan yang ingin membayar menggunakan uang tunai.
- **🤖 AI Cash Detector (Python):** Sistem cerdas untuk mendeteksi nominal atau keaslian uang tunai yang diberikan ke kasir melalui kamera, meminimalisir penipuan sebelum transaksi disetujui (Approval).
- **🧾 Invoice & Struk:** Fitur cetak struk/invoice untuk pelanggan.

---

## 🛠️ Teknologi yang Digunakan

**Backend (Web & API):**

- PHP 7.4 / 8.x (Native PDO)
- MySQL / MariaDB
- HTML5, CSS3 (Custom Design), Vanilla JS
- Midtrans Snap API
- Mikrotik RouterOS API

**AI Service (Cash Detector):**

- Python 3.10+
- FastAPI & Uvicorn
- Ultralytics (YOLOv8)
- OpenCV

---

## 🚀 Panduan Instalasi (Lokal / Development)

Karena sistem ini terdiri dari dua bagian (Web PHP dan Service AI Python), Anda perlu menjalankan keduanya secara paralel.

### Bagian 1: Instalasi Web PHP (Laragon / XAMPP)

1. **Clone/Pindahkan** folder proyek ini ke dalam folder root web server Anda (contoh: `E:\laragon\www\biling`).
2. **Buat Database** di MySQL dengan nama `biling_hotspot`.
3. **Import Skema Database** dari file `database/schema.sql`.
4. **Konfigurasi Environment:**
   - Duplikat file `.env.example` menjadi `.env`.
   - Buka file `.env` dan sesuaikan kredensial berikut:

     ```env
     # Kredensial Database
     DB_NAME=biling_hotspot
     DB_USER=root
     DB_PASS=

     # Kredensial Mikrotik
     MIKROTIK_HOST=192.168.88.1
     MIKROTIK_USER=admin_api
     MIKROTIK_PASS=password_anda

     # Kredensial Midtrans
     MIDTRANS_SERVER_KEY=SB-Mid-server-xxxx
     MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxx

     # Sambungan ke AI Python
     CASH_DETECTOR_URL=[http://127.0.0.1:8000](http://127.0.0.1:8000)
     ```

### Bagian 2: Instalasi AI Cash Detector (Python)

1. Buka Terminal/CMD dan masuk ke direktori servis AI:
   ```bash
   cd services/cash-detector
   Buat Virtual Environment (Venv):
   ```

Bash
python -m venv .venv
Aktifkan Virtual Environment:

Windows: .venv\Scripts\activate

Linux/Mac: source .venv/bin/activate

Install library yang dibutuhkan (YOLO, FastAPI, dll):

Bash
pip install torch torchvision torchaudio --index-url [https://download.pytorch.org/whl/cu118](https://download.pytorch.org/whl/cu118) --no-cache-dir
pip install -r requirements.txt
Penting: Pastikan Anda sudah meletakkan file model AI best.pt di dalam folder services/cash-detector/.

⚙️ Cara Menjalankan Aplikasi
Jalankan Web Server: Pastikan Apache/Nginx dan MySQL di Laragon/XAMPP dalam keadaan Start.

Jalankan Server AI: Di dalam terminal Python yang venv-nya masih aktif, jalankan:

Bash
uvicorn app:app --host 0.0.0.0 --port 8000
Akses Aplikasi:

Halaman Publik: http://localhost/biling/

Halaman Kasir/Admin: http://localhost/biling/admin/ (Login default: admin / admin123)

API Docs AI: http://localhost:8000/docs

🌐 Panduan Deployment (Production)
Untuk menerapkan sistem ini ke ranah publik (Internet):

Web (PHP & MySQL): Cocok di-deploy ke STB OpenWrt / aaPanel / Shared Hosting. Panduan lengkap tersedia di file docs/DEPLOYMENT.md.

AI Service (Python): Wajib di-deploy ke VPS mandiri seperti AWS EC2, DigitalOcean, atau Google Cloud karena membutuhkan resource komputasi dan instalasi Python yang terisolasi. Jangan gunakan hosting biasa (Shared Hosting) atau Supabase untuk servis AI ini.

Setelah Python di-deploy ke EC2, cukup ubah nilai CASH_DETECTOR_URL di file .env PHP Anda menjadi IP Public dari server AWS EC2 tersebut (contoh: http://13.250.x.x:8000).

📁 Struktur Direktori Utama
Plaintext
/biling
│
├── /admin/ # Panel POS & Dashboard Kasir
├── /api/ # Endpoint internal PHP (Midtrans Webhook, Cek Status)
├── /assets/ # CSS, JS, dan Gambar (Frontend)
├── /config/ # Pengaturan Koneksi Database & App
├── /database/ # Skema SQL (schema.sql)
├── /docs/ # Dokumentasi (Panduan Deployment STB/aaPanel)
├── /includes/ # Class Core (Midtrans, Mikrotik, CashDetector Bridge)
├── /services/
│ └── /cash-detector/ # Service AI Python (FastAPI, YOLOv8)
│ ├── app.py # Main script AI
│ ├── best.pt # Model terlatih YOLO (Perlu diletakkan di sini)
│ └── requirements.txt # Daftar library Python
│
├── .env.example # Template environment variable
├── index.php # Halaman Landing Page Publik
├── checkout.php # Halaman Checkout Midtrans (QRIS)
└── checkout-cash.php # Halaman Checkout Tunai (Menunggu Scan Kasir)
Dikembangkan untuk operasional jaringan RT/RW Net modern.
