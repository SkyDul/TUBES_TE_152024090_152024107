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
    <title>Pembayaran — <?= htmlspecialchars($transaksi['nama_paket']) ?></title>
    <link rel="icon" type="image/png" href="assets/img/logo-RipaNet.png">
    <link rel="stylesheet" href="assets/css/style.css?v=6">
</head>
<body>
    <div class="container checkout-shell">
        <div class="topbar__inner" style="width:100%;padding-top:0;">
            <a class="brand" href="./">
                <img class="brand__logo" src="assets/img/logo-RipaNet.png" alt="Logo RipaNet">
                <span class="brand__meta">
                    <strong>Pembayaran Online</strong>
                    <span>Selesaikan pembayaran untuk mendapat voucher</span>
                </span>
            </a>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" id="cancel-btn" style="color:var(--danger); border-color:rgba(239,68,64,0.3);">Batalkan</button>
                <a class="btn btn-secondary btn-sm" href="./">Kembali</a>
            </div>
        </div>

        <div class="checkout-grid">
            <section class="checkout-card checkout-card--accent">
                <div class="qr-container">
                    <span class="eyebrow" style="background:rgba(255,255,255,0.1);color:#fde68a;border-color:rgba(255,255,255,0.15);">Pembayaran Online</span>
                    <div class="qr-card">
                        <h1 class="qr-card__title"><?= htmlspecialchars($transaksi['nama_paket']) ?></h1>
                        <div class="qr-card__amount">Rp <?= number_format($transaksi['amount'], 0, ',', '.') ?></div>

                        <?php if ($isImageUrl): ?>
                            <div class="qr-image-container">
                                <img src="<?= htmlspecialchars($paymentUrl) ?>" alt="Kode QR pembayaran">
                            </div>
                        <?php elseif ($paymentUrl !== ''): ?>
                            <div class="empty-state" style="width:100%;">
                                <strong>Halaman pembayaran tersedia.</strong>
                                <span>Klik tombol di bawah untuk membuka halaman pembayaran.</span>
                                <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($paymentUrl) ?>" target="_blank" style="margin-top:8px;">Buka Pembayaran</a>
                            </div>
                        <?php else: ?>
                            <div class="empty-state" style="width:100%;background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.15);color:rgba(255,255,255,0.8);">
                                <strong style="color:white;">Kode QR belum tersedia.</strong>
                                <span>Kembali ke halaman utama dan coba lagi.</span>
                            </div>
                        <?php endif; ?>

                        <div class="qr-timer">
                            <span>Batas bayar:</span>
                            <strong id="countdown-timer"><?= floor($remainingSeconds / 60) ?>:<?= str_pad((string) ($remainingSeconds % 60), 2, '0', STR_PAD_LEFT) ?></strong>
                        </div>

                        <div class="qr-status qr-status--pending" id="payment-status">
                            <span class="spinner"></span>
                            <span>Menunggu pembayaran...</span>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="checkout-side">
                <div class="panel-head">
                    <div>
                        <h2>Ringkasan Pesanan</h2>
                        <p>Voucher akan muncul otomatis setelah pembayaran berhasil.</p>
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
                        <span>Total</span>
                        <strong>Rp <?= number_format($transaksi['amount'], 0, ',', '.') ?></strong>
                    </div>
                </div>

                <div class="instructions" style="margin-top:16px;">
                    <h3 class="instructions__title">Cara Bayar</h3>
                    <ol class="instructions__list">
                        <li>Buka aplikasi e-wallet atau mobile banking yang mendukung QRIS.</li>
                        <li>Scan kode QR yang tampil di sebelah kiri.</li>
                        <li>Konfirmasi pembayaran sesuai nominal.</li>
                        <li>Biarkan halaman ini terbuka, voucher akan muncul otomatis.</li>
                    </ol>
                </div>

                <div class="summary-grid" style="margin-top:16px;">
                    <article class="summary-item">
                        <h4>Status Otomatis</h4>
                        <p>Halaman ini mengecek status pembayaran secara otomatis.</p>
                    </article>
                    <article class="summary-item">
                        <h4>Butuh Bantuan?</h4>
                        <p>Jika waktu habis, kembali ke halaman utama dan buat pesanan baru.</p>
                    </article>
                </div>
            </aside>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div class="modal-overlay" id="cancel-modal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <button class="modal-close" id="cancel-modal-close" type="button">&times;</button>
            <div style="margin: 0 auto 16px; width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center; background: rgba(239, 68, 64, 0.1); color: var(--danger);">
                <svg viewBox="0 0 24 24" width="32" height="32" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <h3 style="margin-bottom: 8px; font-size: 1.25rem;">Batalkan Pesanan?</h3>
            <p style="color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 24px;">Apakah Anda yakin ingin membatalkan pesanan ini? Aksi ini tidak dapat dibatalkan.</p>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <button type="button" class="btn btn-secondary" id="cancel-modal-no">Tidak, Kembali</button>
                <button type="button" class="btn btn-primary" id="cancel-modal-yes" style="background: var(--danger); border-color: var(--danger);">Ya, Batalkan</button>
            </div>
        </div>
    </div>

    <script src="assets/js/checkout.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        new CheckoutHandler('<?= htmlspecialchars($orderId) ?>', <?= $expireMinutes ?>);
        
        const cancelBtn = document.getElementById('cancel-btn');
        const cancelModal = document.getElementById('cancel-modal');
        const btnNo = document.getElementById('cancel-modal-no');
        const btnYes = document.getElementById('cancel-modal-yes');
        const btnClose = document.getElementById('cancel-modal-close');

        if (cancelBtn && cancelModal) {
            const hideModal = () => cancelModal.classList.remove('active');
            
            cancelBtn.addEventListener('click', () => cancelModal.classList.add('active'));
            btnClose.addEventListener('click', hideModal);
            btnNo.addEventListener('click', hideModal);
            
            btnYes.addEventListener('click', async () => {
                try {
                    btnYes.disabled = true;
                    btnYes.innerHTML = '<span class="spinner"></span> Proses...';
                    
                    await fetch('api/cancel-order.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ order_id: '<?= htmlspecialchars($orderId) ?>' })
                    });
                    window.location.href = './';
                } catch (e) {
                    window.location.href = './';
                }
            });
        }
    });
    </script>
</body>
</html>
