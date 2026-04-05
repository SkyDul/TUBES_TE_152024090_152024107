<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Masukkan username dan password.';
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

        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin — RipaNet</title>
    <link rel="icon" type="image/png" href="../assets/img/logo-RipaNet.png">
    <link rel="stylesheet" href="../assets/css/style.css?v=5">
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <section class="auth-showcase">
                <a class="brand" href="../" style="position:relative;z-index:1;color:white;">
                    <img class="brand__logo" src="../assets/img/logo-RipaNet.png" alt="Logo RipaNet">
                    <span class="brand__meta">
                        <strong>RipaNet Admin</strong>
                        <span style="color:rgba(255,255,255,0.75);">Sistem Manajemen Penjualan</span>
                    </span>
                </a>

                <h1 style="margin-top:36px;position:relative;z-index:1;">Kelola penjualan voucher dan transaksi tunai.</h1>
                <p style="margin-top:14px;position:relative;z-index:1;max-width:460px;">
                    Masuk untuk mengakses dashboard, terminal penjualan, dan manajemen produk.
                </p>

                <ul style="position:relative;z-index:1;">
                    <li>Proses penjualan tunai dan konfirmasi pembayaran.</li>
                    <li>Kelola paket voucher dan harga.</li>
                    <li>Pantau pendapatan dan riwayat transaksi.</li>
                </ul>
            </section>

            <section class="auth-form">
                <div>
                    <span class="eyebrow">Login</span>
                    <h2 style="margin-top:14px;">Masuk ke Admin</h2>
                    <p class="form-note" style="margin-top:8px;">Gunakan akun admin atau kasir yang terdaftar.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" style="display:grid;gap:16px;">
                    <div class="field">
                        <label for="username">Username</label>
                        <input id="username" class="input" type="text" name="username" placeholder="Username" required autofocus>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" class="input" type="password" name="password" placeholder="Password" required>
                    </div>

                    <button class="btn btn-primary" type="submit">Masuk</button>
                </form>

                <div class="summary-grid">
                    <article class="summary-item">
                        <h4>Kembali ke Halaman Utama</h4>
                        <p>Lihat halaman pembelian voucher untuk pelanggan.</p>
                        <a class="btn btn-secondary btn-sm" href="../">Halaman Utama</a>
                    </article>
                </div>
            </section>
        </div>
    </div>
</body>
</html>
