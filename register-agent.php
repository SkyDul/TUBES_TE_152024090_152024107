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
                <div class="panel-head" style="margin-bottom: 20px;">
                    <div>
                        <h2>Pendaftaran Agen Voucher Offline</h2>
                        <p>Bergabunglah menjadi mitra RipaNet dan dapatkan penghasilan tambahan dengan menjual voucher WiFi di lokasi Anda.</p>
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
                        
                        <div style="background: var(--surface); border: 1px dashed var(--primary); border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 20px;">
                            <span class="icon" style="color: var(--primary); display: block; margin-bottom: 10px;">
                                <svg viewBox="0 0 24 24" width="32" height="32" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </span>
                            <h4 style="margin: 0 0 10px 0;">Template Surat Perjanjian</h4>
                            <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 15px;">Silakan unduh atau cetak template surat perjanjian ini.</p>
                            <a href="contract-template.html" target="_blank" class="btn btn-secondary">
                                Unduh / Cetak Template
                            </a>
                        </div>

                        <div class="form-group">
                            <label for="contract_file">Unggah Surat Perjanjian (Sudah Ditandatangani)</label>
                            <input type="file" id="contract_file" name="contract_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required style="padding: 8px;">
                            <small class="muted" style="display: block; margin-top: 5px;">Format yang didukung: JPG, PNG, PDF. Maksimal 2MB.</small>
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
        
        <footer class="footer">
            <div class="container">
                <div class="footer__card">
                    <div>
                        <strong>RipaNet</strong>
                        <p>Voucher WiFi instan — bayar dan langsung online.</p>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
