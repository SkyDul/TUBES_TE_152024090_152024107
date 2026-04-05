<?php
require_once __DIR__ . '/config/database.php';

$orderId = $_GET['order_id'] ?? '';

if ($orderId === '') {
    header('Location: ./');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT t.*, p.nama_paket, p.durasi_display
    FROM transaksi t
    JOIN paket_voucher p ON t.paket_id = p.id
    WHERE t.order_id = ? AND t.status = 'settlement'
");
$stmt->execute([$orderId]);
$transaksi = $stmt->fetch();

if (!$transaksi) {
    header('Location: checkout.php?order_id=' . urlencode($orderId));
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Berhasil — RipaNet</title>
    <link rel="icon" type="image/png" href="assets/img/logo-RipaNet.png">
    <link rel="stylesheet" href="assets/css/style.css?v=5">
</head>
<body>
    <div class="container">
        <div class="success-shell">
            <div class="topbar__inner" style="width:100%;padding-top:0;">
                <a class="brand" href="./">
                    <img class="brand__logo" src="assets/img/logo-RipaNet.png" alt="Logo RipaNet">
                    <span class="brand__meta">
                        <strong>Transaksi Berhasil</strong>
                        <span>Voucher Anda sudah aktif</span>
                    </span>
                </a>
                <a class="btn btn-secondary btn-sm" href="./">Beli Lagi</a>
            </div>

            <section class="success-banner">
                <span class="eyebrow" style="background:rgba(255,255,255,0.1);color:#a7f3d0;border-color:rgba(255,255,255,0.15);">Pembayaran Berhasil</span>
                <h1 style="margin-top:14px;">Voucher Anda siap digunakan.</h1>
                <p>Gunakan kode di bawah sebagai username dan password di halaman login hotspot RipaNet.</p>
            </section>

            <div class="success-grid">
                <section class="voucher-card">
                    <p class="voucher-card__label">Kode Voucher</p>
                    <div class="voucher-card__code" id="voucher-code"><?= htmlspecialchars($transaksi['mikrotik_user']) ?></div>
                    <button class="btn btn-primary copy-btn" type="button" onclick="copyToClipboard('<?= htmlspecialchars($transaksi['mikrotik_user'], ENT_QUOTES, 'UTF-8') ?>', this)">
                        Salin Kode
                    </button>

                    <div class="voucher-info">
                        <div class="voucher-info__item">
                            <span>Paket</span>
                            <strong><?= htmlspecialchars($transaksi['nama_paket']) ?></strong>
                        </div>
                        <div class="voucher-info__item">
                            <span>Durasi</span>
                            <strong><?= htmlspecialchars($transaksi['durasi_display']) ?></strong>
                        </div>
                        <div class="voucher-info__item">
                            <span>No. Pesanan</span>
                            <strong class="mono"><?= htmlspecialchars($orderId) ?></strong>
                        </div>
                        <div class="voucher-info__item">
                            <span>Waktu Bayar</span>
                            <strong><?= date('d M Y H:i', strtotime($transaksi['paid_at'])) ?></strong>
                        </div>
                    </div>
                </section>

                <aside class="panel">
                    <div class="panel-head">
                        <div>
                            <h3>Cara Menggunakan Voucher</h3>
                            <p>Ikuti langkah berikut untuk terhubung ke internet.</p>
                        </div>
                    </div>

                    <div class="instructions" style="padding:0;border:none;background:transparent;">
                        <ol class="instructions__list">
                            <li>Hubungkan perangkat ke jaringan WiFi RipaNet.</li>
                            <li>Buka browser, halaman login akan muncul otomatis.</li>
                            <li>Masukkan kode voucher sebagai username.</li>
                            <li>Masukkan kode yang sama sebagai password, lalu klik Login.</li>
                        </ol>
                    </div>

                    <div class="actions-row" style="margin-top:16px;">
                        <a class="btn btn-secondary" href="invoice.php?order_id=<?= urlencode($orderId) ?>" target="_blank">Cetak Struk</a>
                        <a class="btn btn-primary" href="./">Selesai</a>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <script src="assets/js/checkout.js"></script>
</body>
</html>
