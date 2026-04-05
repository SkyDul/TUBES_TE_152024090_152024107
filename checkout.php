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

$expiredAt = strtotime($transaksi['expired_at']);
$remainingSeconds = max(0, $expiredAt - time());
$expireMinutes = (int) ceil($remainingSeconds / 60);
$paymentUrl = $transaksi['qr_url'] ?? '';
$isImageUrl = $paymentUrl !== '' && preg_match('/\.(png|jpg|jpeg|svg)(\?.*)?$/i', $paymentUrl);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Online - <?= htmlspecialchars($transaksi['nama_paket']) ?></title>
    <link rel="icon" type="image/png" href="assets/img/logo-RipaNet.png">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container checkout-shell">
        <div class="topbar__inner" style="width: 100%; padding-top: 0;">
            <a class="brand" href="./">
                <img class="brand__logo" src="assets/img/logo-RipaNet.png" alt="Logo RipaNet">
                <span class="brand__meta">
                    <strong>RipaNet Checkout</strong>
                    <span>Pembayaran online untuk voucher hotspot</span>
                </span>
            </a>
            <a class="btn btn-secondary btn-sm" href="./">Kembali ke Paket</a>
        </div>

        <div class="checkout-grid">
            <section class="checkout-card checkout-card--accent">
                <div class="qr-container">
                    <span class="eyebrow" style="background: rgba(255,255,255,0.12); color: #ffe9ca; border-color: rgba(255,255,255,0.16);">Pembayaran Online</span>
                    <div class="qr-card">
                        <h1 class="qr-card__title"><?= htmlspecialchars($transaksi['nama_paket']) ?></h1>
                        <div class="qr-card__amount">Rp <?= number_format($transaksi['amount'], 0, ',', '.') ?></div>

                        <?php if ($isImageUrl): ?>
                            <div class="qr-image-container">
                                <img src="<?= htmlspecialchars($paymentUrl) ?>" alt="QR pembayaran">
                            </div>
                        <?php elseif ($paymentUrl !== ''): ?>
                            <div class="empty-state" style="width: 100%;">
                                <strong>Halaman pembayaran tersedia.</strong>
                                <span>Buka kembali halaman pembayaran Midtrans jika Anda belum menyelesaikan langkah di aplikasi pembayaran.</span>
                                <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($paymentUrl) ?>" target="_blank" style="margin-top: 10px;">Buka Halaman Pembayaran</a>
                            </div>
                        <?php else: ?>
                            <div class="empty-state" style="width: 100%; background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.16); color: rgba(255,255,255,0.84);">
                                <strong style="color: white;">QR Code belum tersedia.</strong>
                                <span>Silakan kembali ke halaman utama lalu ulangi checkout.</span>
                            </div>
                        <?php endif; ?>

                        <div class="qr-timer">
                            <span>Batas bayar:</span>
                            <strong id="countdown-timer"><?= floor($remainingSeconds / 60) ?>:<?= str_pad((string) ($remainingSeconds % 60), 2, '0', STR_PAD_LEFT) ?></strong>
                        </div>

                        <div class="qr-status qr-status--pending" id="payment-status">
                            <span class="spinner"></span>
                            <span>Menunggu pembayaran dari Midtrans...</span>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="checkout-side">
                <div class="panel-head">
                    <div>
                        <h2>Ringkasan Checkout</h2>
                        <p>Setelah pembayaran settle, voucher akan otomatis muncul tanpa reload manual.</p>
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
                        <span>Nominal</span>
                        <strong>Rp <?= number_format($transaksi['amount'], 0, ',', '.') ?></strong>
                    </div>
                </div>

                <div class="instructions" style="margin-top: 20px;">
                    <h3 class="instructions__title">Cara menyelesaikan pembayaran</h3>
                    <ol class="instructions__list">
                        <li>Buka aplikasi e-wallet atau mobile banking yang mendukung QRIS.</li>
                        <li>Scan kode pembayaran yang tampil di sebelah kiri.</li>
                        <li>Konfirmasi nominal sampai transaksi dinyatakan berhasil.</li>
                        <li>Biarkan halaman ini terbuka, voucher akan muncul otomatis setelah settlement.</li>
                    </ol>
                </div>

                <div class="summary-grid" style="margin-top: 20px;">
                    <article class="summary-item">
                        <h4>Status real-time</h4>
                        <p>Halaman ini melakukan polling ke server untuk mengecek status pembayaran secara berkala.</p>
                    </article>
                    <article class="summary-item">
                        <h4>Butuh bantuan?</h4>
                        <p>Jika waktu habis atau transaksi gagal, kembali ke landing page lalu buat transaksi baru.</p>
                    </article>
                </div>
            </aside>
        </div>
    </div>

    <script src="assets/js/checkout.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        new CheckoutHandler('<?= htmlspecialchars($orderId) ?>', <?= $expireMinutes ?>);
    });
    </script>
</body>
</html>
