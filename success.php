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
    <title>Voucher Siap Dipakai - RipaNet</title>
    <link rel="icon" type="image/png" href="assets/img/logo-RipaNet.png">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="success-shell">
            <div class="topbar__inner" style="width: 100%; padding-top: 0;">
                <a class="brand" href="./">
                    <img class="brand__logo" src="assets/img/logo-RipaNet.png" alt="Logo RipaNet">
                    <span class="brand__meta">
                        <strong>RipaNet Voucher</strong>
                        <span>Pembayaran berhasil dan voucher sudah aktif</span>
                    </span>
                </a>
                <a class="btn btn-secondary btn-sm" href="./">Beli Lagi</a>
            </div>

            <section class="success-banner">
                <span class="eyebrow" style="background: rgba(255,255,255,0.12); color: #eafff5; border-color: rgba(255,255,255,0.16);">Pembayaran Berhasil</span>
                <h1 style="margin-top: 16px;">Voucher Anda siap dipakai sekarang juga.</h1>
                <p>Gunakan kode yang sama sebagai username dan password di halaman login hotspot RipaNet.</p>
            </section>

            <div class="success-grid">
                <section class="voucher-card">
                    <p class="voucher-card__label">Kode Voucher</p>
                    <div class="voucher-card__code" id="voucher-code"><?= htmlspecialchars($transaksi['mikrotik_user']) ?></div>
                    <button class="btn btn-primary copy-btn" type="button" onclick="copyToClipboard('<?= htmlspecialchars($transaksi['mikrotik_user'], ENT_QUOTES, 'UTF-8') ?>', this)">
                        Salin Kode Voucher
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
                            <span>Order ID</span>
                            <strong class="mono"><?= htmlspecialchars($orderId) ?></strong>
                        </div>
                        <div class="voucher-info__item">
                            <span>Dibayar pada</span>
                            <strong><?= date('d M Y H:i', strtotime($transaksi['paid_at'])) ?></strong>
                        </div>
                    </div>
                </section>

                <aside class="panel">
                    <div class="panel-head">
                        <div>
                            <h3>Cara pakai voucher</h3>
                            <p>Ikuti langkah berikut agar voucher langsung aktif di hotspot.</p>
                        </div>
                    </div>

                    <div class="instructions" style="padding: 0; border: none; background: transparent;">
                        <ol class="instructions__list">
                            <li>Hubungkan perangkat ke jaringan hotspot RipaNet.</li>
                            <li>Buka browser sampai halaman login hotspot muncul.</li>
                            <li>Masukkan kode voucher di atas sebagai username.</li>
                            <li>Masukkan kode yang sama lagi sebagai password, lalu login.</li>
                        </ol>
                    </div>

                    <div class="actions-row" style="margin-top: 20px;">
                        <a class="btn btn-secondary" href="invoice.php?order_id=<?= urlencode($orderId) ?>" target="_blank">Cetak Invoice</a>
                        <a class="btn btn-primary" href="./">Selesai & Beli Lagi</a>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <script src="assets/js/checkout.js"></script>
</body>
</html>
