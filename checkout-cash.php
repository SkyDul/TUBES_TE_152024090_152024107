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
    WHERE t.order_id = ?
");
$stmt->execute([$orderId]);
$transaksi = $stmt->fetch();

if (!$transaksi) {
    header('Location: ./');
    exit;
}

if ($transaksi['status'] === 'settlement') {
    header('Location: success.php?order_id=' . urlencode($orderId));
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Tunai — <?= htmlspecialchars($transaksi['nama_paket']) ?></title>
    <link rel="icon" type="image/png" href="assets/img/logo-RipaNet.png">
    <link rel="stylesheet" href="assets/css/style.css?v=5">
</head>
<body>
    <div class="container checkout-shell">
        <div class="topbar__inner" style="width:100%;padding-top:0;">
            <a class="brand" href="./">
                <img class="brand__logo" src="assets/img/logo-RipaNet.png" alt="Logo RipaNet">
                <span class="brand__meta">
                    <strong>Pembayaran Tunai</strong>
                    <span>Menunggu konfirmasi kasir</span>
                </span>
            </a>
            <a class="btn btn-secondary btn-sm" href="./">Kembali</a>
        </div>

        <div class="checkout-grid">
            <section class="checkout-card checkout-card--accent">
                <div class="qr-container">
                    <span class="eyebrow" style="background:rgba(255,255,255,0.1);color:#fde68a;border-color:rgba(255,255,255,0.15);">Pembayaran Tunai</span>
                    <div class="qr-card">
                        <h1 class="qr-card__title"><?= htmlspecialchars($transaksi['nama_paket']) ?></h1>
                        <div class="qr-card__amount">Rp <?= number_format($transaksi['amount'], 0, ',', '.') ?></div>
                        <div class="qr-image-container" style="padding:24px 28px;">
                            <strong style="display:block;font-size:0.88rem;color:var(--text-muted);">Nomor Pesanan</strong>
                            <div class="voucher-card__code" style="margin-top:10px;font-size:1.3rem;color:var(--primary);background:var(--primary-light);">
                                <?= htmlspecialchars($orderId) ?>
                            </div>
                        </div>
                        <div class="qr-status qr-status--pending" id="payment-status">
                            <span class="spinner"></span>
                            <span>Menunggu konfirmasi kasir...</span>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="checkout-side">
                <div class="panel-head">
                    <div>
                        <h2>Cara Pembayaran Tunai</h2>
                        <p>Tunjukkan nomor pesanan ini ke kasir dan bayar tunai.</p>
                    </div>
                </div>

                <div class="checkout-summary">
                    <div class="checkout-summary__row">
                        <span>No. Pesanan</span>
                        <strong class="mono"><?= htmlspecialchars($orderId) ?></strong>
                    </div>
                    <div class="checkout-summary__row">
                        <span>Paket</span>
                        <strong><?= htmlspecialchars($transaksi['nama_paket']) ?></strong>
                    </div>
                    <div class="checkout-summary__row">
                        <span>Durasi</span>
                        <strong><?= htmlspecialchars($transaksi['durasi_display']) ?></strong>
                    </div>
                    <div class="checkout-summary__row">
                        <span>Total Bayar</span>
                        <strong>Rp <?= number_format($transaksi['amount'], 0, ',', '.') ?></strong>
                    </div>
                </div>

                <div class="instructions" style="margin-top:16px;">
                    <h3 class="instructions__title">Langkah-langkah</h3>
                    <ol class="instructions__list">
                        <li>Datangi kasir atau petugas terdekat.</li>
                        <li>Tunjukkan nomor pesanan yang tampil di halaman ini.</li>
                        <li>Bayarkan uang tunai sesuai total tagihan.</li>
                        <li>Kasir akan mengkonfirmasi dan voucher akan muncul otomatis di layar ini.</li>
                    </ol>
                </div>

                <div class="summary-grid" style="margin-top:16px;">
                    <article class="summary-item">
                        <h4>Jangan Tutup Halaman</h4>
                        <p>Halaman ini akan memperbarui status secara otomatis.</p>
                    </article>
                    <article class="summary-item">
                        <h4>Perlu Buat Ulang?</h4>
                        <p>Jika ada kendala, kembali ke halaman utama dan buat pesanan baru.</p>
                    </article>
                </div>
            </aside>
        </div>
    </div>

    <script src="assets/js/checkout.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        new CheckoutHandler('<?= htmlspecialchars($orderId) ?>', 60);
    });
    </script>
</body>
</html>
