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
    <title>Pembayaran Tunai - <?= htmlspecialchars($transaksi['nama_paket']) ?></title>
    <link rel="icon" type="image/png" href="assets/img/logo-RipaNet.png">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container checkout-shell">
        <div class="topbar__inner" style="width: 100%; padding-top: 0;">
            <a class="brand" href="./">
                <img class="brand__logo" src="assets/img/logo-RipaNet.png" alt="Logo RipaNet">
                <span class="brand__meta">
                    <strong>RipaNet Cash Checkout</strong>
                    <span>Order tunai yang menunggu konfirmasi kasir</span>
                </span>
            </a>
            <a class="btn btn-secondary btn-sm" href="./">Kembali ke Paket</a>
        </div>

        <div class="checkout-grid">
            <section class="checkout-card checkout-card--accent">
                <div class="qr-container">
                    <span class="eyebrow" style="background: rgba(255,255,255,0.12); color: #ffe9ca; border-color: rgba(255,255,255,0.16);">Pembayaran Tunai</span>
                    <div class="qr-card">
                        <h1 class="qr-card__title"><?= htmlspecialchars($transaksi['nama_paket']) ?></h1>
                        <div class="qr-card__amount">Rp <?= number_format($transaksi['amount'], 0, ',', '.') ?></div>
                        <div class="qr-image-container" style="padding: 24px 28px;">
                            <strong style="display: block; font-size: 0.95rem; color: var(--text-secondary);">Order ID</strong>
                            <div class="voucher-card__code" style="margin-top: 12px; font-size: 1.5rem; color: var(--brand-blue); background: rgba(24, 119, 183, 0.08);">
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
                        <h2>Instruksi untuk pelanggan</h2>
                        <p>Sebutkan order ID ini ke kasir lalu lakukan pembayaran tunai sesuai nominal.</p>
                    </div>
                </div>

                <div class="checkout-summary">
                    <div class="checkout-summary__row">
                        <span>Order ID</span>
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
                        <span>Total bayar</span>
                        <strong>Rp <?= number_format($transaksi['amount'], 0, ',', '.') ?></strong>
                    </div>
                </div>

                <div class="instructions" style="margin-top: 20px;">
                    <h3 class="instructions__title">Cara bayar tunai</h3>
                    <ol class="instructions__list">
                        <li>Datangi kasir atau admin terdekat.</li>
                        <li>Sebutkan order ID yang tampil di halaman ini.</li>
                        <li>Bayarkan uang tunai sesuai nominal tagihan.</li>
                        <li>Kasir akan melakukan konfirmasi dari terminal POS dan voucher akan muncul otomatis di layar ini.</li>
                    </ol>
                </div>

                <div class="summary-grid" style="margin-top: 20px;">
                    <article class="summary-item">
                        <h4>Jangan tutup halaman</h4>
                        <p>Halaman ini akan mengecek status settlement secara berkala sampai voucher siap.</p>
                    </article>
                    <article class="summary-item">
                        <h4>Butuh ulang order?</h4>
                        <p>Jika terjadi pembatalan, pelanggan bisa kembali ke landing page dan membuat order cash baru.</p>
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
