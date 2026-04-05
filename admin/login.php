<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Isi username dan password terlebih dahulu.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_user'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            header('Location: pos.php');
            exit;
        }

        $error = 'Username atau password tidak cocok.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login POS - RipaNet</title>
    <link rel="icon" type="image/png" href="../assets/img/logo-RipaNet.png">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <section class="auth-showcase">
                <a class="brand" href="../" style="position: relative; z-index: 1; color: white;">
                    <img class="brand__logo" src="../assets/img/logo-RipaNet.png" alt="Logo RipaNet">
                    <span class="brand__meta">
                        <strong>RipaNet POS</strong>
                        <span style="color: rgba(248,251,255,0.78);">Akses terminal kasir dan approval tunai</span>
                    </span>
                </a>

                <h1 style="margin-top: 42px; position: relative; z-index: 1;">Satu login untuk dashboard, terminal POS, dan antrean cash.</h1>
                <p style="margin-top: 16px; position: relative; z-index: 1; max-width: 520px;">
                    Area admin sekarang didesain ulang supaya kasir lebih cepat memproses order tunai dan admin lebih gampang memantau transaksi hotspot.
                </p>

                <ul style="position: relative; z-index: 1;">
                    <li>Masuk ke terminal POS untuk penjualan cash cepat di meja kasir.</li>
                    <li>Buka antrean cash untuk approval order tunai dari landing page publik.</li>
                    <li>Pantau pendapatan harian dan transaksi terbaru dari dashboard admin.</li>
                </ul>
            </section>

            <section class="auth-form">
                <div>
                    <span class="eyebrow">Login Admin</span>
                    <h2 style="margin-top: 16px;">Masuk ke area kasir</h2>
                    <p class="form-note" style="margin-top: 10px;">Gunakan akun admin atau kasir yang sudah terdaftar untuk melanjutkan.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" style="display: grid; gap: 18px;">
                    <div class="field">
                        <label for="username">Username</label>
                        <input id="username" class="input" type="text" name="username" placeholder="Masukkan username" required autofocus>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" class="input" type="password" name="password" placeholder="Masukkan password" required>
                    </div>

                    <button class="btn btn-primary" type="submit">Masuk ke Terminal POS</button>
                </form>

                <div class="summary-grid">
                    <article class="summary-item">
                        <h4>Butuh lihat landing page?</h4>
                        <p>Perubahan UI publik bisa dicek langsung dari halaman utama.</p>
                        <a class="btn btn-secondary btn-sm" href="../">Kembali ke Landing Page</a>
                    </article>
                </div>
            </section>
        </div>
    </div>
</body>
</html>
