<?php
require_once __DIR__ . '/config/database.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $no_wa = trim($_POST['no_wa'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $nik_ktp = trim($_POST['nik_ktp'] ?? '');
    $is_agreed = isset($_POST['is_agreed']) ? 1 : 0;

    // File upload handle
    $contract_file_path = null;

    if (empty($nama_lengkap) || empty($no_wa) || empty($alamat) || empty($nik_ktp)) {
        $error = 'Semua kolom wajib diisi.';
    } elseif (!$is_agreed) {
        $error = 'Anda harus menyetujui surat perjanjian kontrak untuk mendaftar.';
    } elseif (!isset($_FILES['contract_file']) || $_FILES['contract_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Anda harus mengunggah surat perjanjian yang telah ditandatangani.';
    } else {
        // Handle file upload
        $uploadDir = __DIR__ . '/uploads/contracts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileInfo = pathinfo($_FILES['contract_file']['name']);
        $ext = strtolower($fileInfo['extension']);
        $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (!in_array($ext, $allowedExt)) {
            $error = 'Format file tidak didukung. Harap unggah PDF, JPG, atau PNG.';
        } else {
            $newFileName = 'contract_' . time() . '_' . uniqid() . '.' . $ext;
            $destPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['contract_file']['tmp_name'], $destPath)) {
                $contract_file_path = 'uploads/contracts/' . $newFileName;
                
                try {
                    $pdo = getDB();
                    $stmt = $pdo->prepare("
                        INSERT INTO agents (nama_lengkap, no_wa, alamat, nik_ktp, status, is_agreed, contract_file)
                        VALUES (?, ?, ?, ?, 'pending', ?, ?)
                    ");
                    $stmt->execute([$nama_lengkap, $no_wa, $alamat, $nik_ktp, $is_agreed, $contract_file_path]);
                    $success = true;
                } catch (PDOException $e) {
                    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
                }
            } else {
                $error = 'Gagal mengunggah file surat perjanjian.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Agen Offline — RipaNet</title>
    <link rel="icon" type="image/png" href="assets/img/logo-RipaNet.png">
    <link rel="stylesheet" href="assets/css/style.css?v=6">
    <style>
        .contract-box {
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
            font-size: 0.95rem;
            color: var(--text-muted);
        }
        .contract-box h4 {
            color: var(--text);
            margin-bottom: 10px;
            margin-top: 15px;
        }
        .contract-box h4:first-child {
            margin-top: 0;
        }
        .contract-box p, .contract-box li {
            margin-bottom: 10px;
            line-height: 1.6;
        }
        .contract-box ul {
            padding-left: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text);
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 6px;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
        }
        .checkbox-group input {
            margin-top: 5px;
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        /* Modern Download Card */
        .download-card {
            display: flex;
            align-items: center;
            background: linear-gradient(145deg, rgba(37, 99, 235, 0.05) 0%, rgba(37, 99, 235, 0.02) 100%);
            border: 1px solid rgba(37, 99, 235, 0.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }
        .download-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.08);
            transform: translateY(-2px);
        }
        .download-icon {
            flex-shrink: 0;
            width: 48px;
            height: 48px;
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        .download-info {
            flex-grow: 1;
            text-align: left;
        }
        .download-info h4 {
            margin: 0 0 4px 0;
            color: var(--text);
            font-size: 1.05rem;
        }
        .download-info p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .btn-download {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary);
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: background 0.2s;
            margin-left: 15px;
        }
        .btn-download:hover {
            background: #1d4ed8;
        }

        /* Modern File Upload Wrapper */
        .file-upload-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 30px 20px;
            background: rgba(0, 0, 0, 0.01);
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        .file-upload-wrapper:hover, .file-upload-wrapper.dragover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.03);
        }
        .file-upload-wrapper.has-file {
            border-style: solid;
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }
        .file-upload-icon {
            color: var(--text-muted);
            margin-bottom: 12px;
            transition: color 0.3s;
        }
        .file-upload-wrapper:hover .file-upload-icon,
        .file-upload-wrapper.dragover .file-upload-icon,
        .file-upload-wrapper.has-file .file-upload-icon {
            color: var(--primary);
        }
        .file-upload-title {
            display: block;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 4px;
            font-size: 1rem;
        }
        .file-upload-desc {
            display: block;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Responsive adjustments */
        @media (max-width: 600px) {
            .download-card {
                flex-direction: column;
                text-align: center;
            }
            .download-info {
                text-align: center;
            }
            .download-icon {
                margin: 0 0 15px 0;
            }
            .btn-download {
                margin: 15px 0 0 0;
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <header class="topbar">
            <div class="topbar__inner">
                <a class="brand" href="./">
                    <img class="brand__logo" src="assets/img/logo-RipaNet.png" alt="Logo RipaNet">
                    <span class="brand__meta">
                        <strong>RipaNet</strong>
                        <span>Internet Cepat & Terjangkau</span>
                    </span>
                </a>
                <div class="nav-actions">
                    <a class="nav-link" href="./">Kembali ke Beranda</a>
                </div>
            </div>
        </header>

        <main class="container" style="max-width: 800px; margin: 40px auto; padding: 0 20px;">
            <div class="panel">
                <div class="registration-hero" style="background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%); border-radius: 16px; padding: 40px 20px; color: white; margin-bottom: 35px; text-align: center; box-shadow: 0 10px 30px rgba(37, 99, 235, 0.2); position: relative; overflow: hidden;">
                    <!-- Decorative background elements -->
                    <div style="position: absolute; top: -30px; right: -20px; width: 120px; height: 120px; background: rgba(255,255,255,0.08); border-radius: 50%;"></div>
                    <div style="position: absolute; bottom: -40px; left: 5%; width: 150px; height: 150px; background: rgba(255,255,255,0.04); border-radius: 50%;"></div>
                    
                    <div style="position: relative; z-index: 1;">
                        <div style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(8px); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                            <svg viewBox="0 0 24 24" width="40" height="40" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="color: white;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        </div>
                        <h2 style="margin: 0 0 12px 0; font-size: 2.1rem; font-weight: 700; letter-spacing: -0.02em; color: white;">Pendaftaran Agen Voucher Offline</h2>
                        <p style="margin: 0; font-size: 1.1rem; color: rgba(255, 255, 255, 0.9); line-height: 1.6; max-width: 600px; margin-left: auto; margin-right: auto; font-weight: 400;">Bergabunglah menjadi mitra RipaNet dan dapatkan penghasilan tambahan dengan menjual voucher WiFi di lokasi Anda.</p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <strong>Pendaftaran Berhasil!</strong><br>
                        Terima kasih, data Anda telah kami terima. Tim kami akan segera memverifikasi pendaftaran Anda. Anda akan dihubungi melalui WhatsApp yang telah didaftarkan.
                    </div>
                    <a href="./" class="btn btn-secondary">Kembali ke Beranda</a>
                <?php else: ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <h3>Surat Perjanjian / Kontrak Keagenan</h3>
                        <p class="muted" style="margin-bottom: 15px;">Harap unduh surat perjanjian di bawah ini, cetak, isi, dan tandatangani. Kemudian foto/scan dan unggah kembali di form pendaftaran.</p>
                        
                        <div class="download-card">
                            <div class="download-icon">
                                <svg viewBox="0 0 24 24" width="28" height="28" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            </div>
                            <div class="download-info">
                                <h4>Template Surat Perjanjian</h4>
                                <p>Silakan unduh, isi, lalu tandatangani sebelum diunggah kembali.</p>
                            </div>
                            <a href="https://docs.google.com/document/d/1Qptuki0HrwvFQOeysgoef_C7TxEOLVKR/edit" target="_blank" class="btn-download">
                                Buka Dokumen
                                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                            </a>
                        </div>

                        <div class="form-group">
                            <label style="margin-bottom: 10px;">Unggah Surat Perjanjian (Sudah Ditandatangani)</label>
                            <label for="contract_file" class="file-upload-wrapper" id="drop-area">
                                <div class="file-upload-icon">
                                    <svg viewBox="0 0 24 24" width="36" height="36" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                </div>
                                <div class="file-upload-text">
                                    <span class="file-upload-title" id="file-name">Pilih file atau seret ke sini</span>
                                    <span class="file-upload-desc">Format: PDF, JPG, PNG (Maks 2MB)</span>
                                </div>
                                <input type="file" id="contract_file" name="contract_file" accept=".pdf,.jpg,.jpeg,.png" required style="display: none;">
                            </label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="is_agreed" name="is_agreed" value="1" required <?= isset($_POST['is_agreed']) ? 'checked' : '' ?>>
                            <label for="is_agreed">Saya menyatakan bahwa dokumen yang diunggah adalah asli dan saya menyetujui seluruh isi Surat Perjanjian / Kontrak Keagenan RipaNet.</label>
                        </div>

                        <hr style="border: 0; border-top: 1px solid var(--border); margin: 30px 0;">

                        <h3>Formulir Data Diri</h3>
                        <div class="form-group">
                            <label for="nama_lengkap">Nama Lengkap (Sesuai KTP)</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="nik_ktp">Nomor Induk Kependudukan (NIK)</label>
                            <input type="text" id="nik_ktp" name="nik_ktp" class="form-control" value="<?= htmlspecialchars($_POST['nik_ktp'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="no_wa">Nomor WhatsApp Aktif</label>
                            <input type="text" id="no_wa" name="no_wa" class="form-control" value="<?= htmlspecialchars($_POST['no_wa'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="alamat">Alamat Lengkap (Lokasi Penjualan)</label>
                            <textarea id="alamat" name="alamat" class="form-control" rows="3" required><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                        </div>

                        <div style="margin-top: 30px;">
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; justify-content: center;">Kirim Pendaftaran Agen</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
        
        <footer style="background-color: var(--bg); border-top: 1px solid var(--border); padding: 50px 0 30px 0; margin-top: 60px;">
            <div class="container" style="max-width: 800px; margin: 0 auto; padding: 0 20px;">
                <div style="display: flex; flex-direction: column; align-items: center; text-align: center;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); background: var(--surface); display: flex; align-items: center; justify-content: center; padding: 4px;">
                            <img src="assets/img/logo-RipaNet.png" alt="RipaNet Logo" style="max-width: 100%; height: auto; border-radius: 6px;">
                        </div>
                        <strong style="font-size: 1.5rem; color: var(--text); font-weight: 700; letter-spacing: -0.5px;">RipaNet</strong>
                    </div>
                    <p style="color: var(--text-muted); font-size: 1rem; margin: 0 0 24px 0; max-width: 450px; line-height: 1.6;">
                        Mitra terpercaya untuk layanan internet instan dan terjangkau. Hubungkan duniamu dengan mudah.
                    </p>
                    
                    <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 15px; margin-bottom: 30px;">
                        <a href="./" style="color: var(--text-muted); text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: color 0.2s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">Beranda</a>
                        <span style="color: var(--border);">•</span>
                        <a href="help.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: color 0.2s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">Pusat Bantuan</a>
                        <span style="color: var(--border);">•</span>
                        <a href="#" style="color: var(--text-muted); text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: color 0.2s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">Syarat & Ketentuan</a>
                    </div>
                    
                    <div style="border-top: 1px solid var(--border); width: 100%; padding-top: 24px; color: var(--text-muted); font-size: 0.9rem; display: flex; flex-direction: column; gap: 6px;">
                        <span>&copy; <?= date('Y') ?> RipaNet. Seluruh hak cipta dilindungi.</span>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    
    <script>
        const fileInput = document.getElementById('contract_file');
        const fileNameDisplay = document.getElementById('file-name');
        const dropArea = document.getElementById('drop-area');

        if (fileInput && fileNameDisplay && dropArea) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    fileNameDisplay.textContent = this.files[0].name;
                    fileNameDisplay.style.color = 'var(--primary)';
                    dropArea.classList.add('has-file');
                } else {
                    fileNameDisplay.textContent = 'Pilih file atau seret ke sini';
                    fileNameDisplay.style.color = '';
                    dropArea.classList.remove('has-file');
                }
            });

            // Drag and drop effects
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, () => dropArea.classList.add('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, () => dropArea.classList.remove('dragover'), false);
            });

            dropArea.addEventListener('drop', (e) => {
                let dt = e.dataTransfer;
                let files = dt.files;
                if (files.length) {
                    fileInput.files = files;
                    const event = new Event('change');
                    fileInput.dispatchEvent(event);
                }
            });
        }
    </script>
</body>
</html>
