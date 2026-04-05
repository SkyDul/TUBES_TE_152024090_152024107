<?php
require_once __DIR__ . '/config/database.php';

$orderId = $_GET['order_id'] ?? '';

if ($orderId === '') {
    die('Nomor pesanan tidak valid');
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
    die('Transaksi tidak ditemukan');
}

$isPaid = $transaksi['status'] === 'settlement';
$cashReceived = $transaksi['amount'];
if (!empty($transaksi['raw_notification'])) {
    $raw = json_decode($transaksi['raw_notification'], true);
    if (isset($raw['cash_received']) && $raw['cash_received'] > 0) {
        $cashReceived = (int) $raw['cash_received'];
    } elseif (isset($raw['detection']['detected_amount']) && $raw['detection']['detected_amount'] > 0) {
        $cashReceived = (int) $raw['detection']['detected_amount'];
    }
}
$change = max(0, $cashReceived - $transaksi['amount']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk — <?= htmlspecialchars($orderId) ?></title>
    <link rel="icon" type="image/png" href="assets/img/logo-RipaNet.png">
    <link rel="stylesheet" href="assets/css/style.css?v=5">
</head>
<body class="receipt-page">
    <div class="receipt-shell">
        <div class="receipt-toolbar">
            <a class="btn btn-secondary btn-sm" href="success.php?order_id=<?= urlencode($orderId) ?>">Kembali</a>
            <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Cetak Struk</button>
        </div>

        <div class="receipt-card">
            <div class="receipt-card__brand">
                <img src="assets/img/logo-RipaNet.png" alt="Logo RipaNet" style="width:60px;margin:0 auto;">
                <strong>RipaNet</strong>
                <span>Struk Pembelian Voucher</span>
            </div>

            <div class="receipt-divider"></div>

            <div class="receipt-row">
                <span>No. Pesanan</span>
                <strong><?= htmlspecialchars($orderId) ?></strong>
            </div>
            <div class="receipt-row">
                <span>Status</span>
                <strong><?= $isPaid ? 'LUNAS' : strtoupper(htmlspecialchars($transaksi['status'])) ?></strong>
            </div>
            <div class="receipt-row">
                <span>Metode</span>
                <strong><?= strtoupper(htmlspecialchars($transaksi['payment_type'] ?: '-')) ?></strong>
            </div>
            <div class="receipt-row">
                <span>Waktu</span>
                <strong><?= date('d M Y H:i', strtotime($transaksi['created_at'])) ?></strong>
            </div>

            <div class="receipt-divider"></div>

            <div class="receipt-row">
                <span>Paket</span>
                <strong><?= htmlspecialchars($transaksi['nama_paket']) ?></strong>
            </div>
            <div class="receipt-row">
                <span>Durasi</span>
                <strong><?= htmlspecialchars($transaksi['durasi_display']) ?></strong>
            </div>
            <div class="receipt-row receipt-total">
                <span>Total Bayar</span>
                <strong>Rp <?= number_format($transaksi['amount'], 0, ',', '.') ?></strong>
            </div>

            <?php if ($isPaid && $transaksi['payment_type'] === 'cash'): ?>
            <div class="receipt-row">
                <span>Uang Diterima</span>
                <strong>Rp <?= number_format($cashReceived, 0, ',', '.') ?></strong>
            </div>
            <div class="receipt-row">
                <span>Kembalian</span>
                <strong>Rp <?= number_format($change, 0, ',', '.') ?></strong>
            </div>
            <?php endif; ?>

            <?php if ($isPaid && !empty($transaksi['mikrotik_user'])): ?>
                <div class="receipt-divider"></div>
                <div class="receipt-voucher">
                    <span>Kode Voucher</span>
                    <code><?= htmlspecialchars($transaksi['mikrotik_user']) ?></code>
                </div>
            <?php endif; ?>

            <div class="receipt-divider"></div>

            <div style="text-align:center;color:var(--text-muted);font-size:0.85rem;">
                <div>Terima kasih telah menggunakan RipaNet.</div>
                <div>Simpan struk ini sebagai bukti transaksi.</div>
            </div>
        </div>
    </div>

    <script>
    window.addEventListener('load', function() {
        setTimeout(function() { window.print(); }, 400);
    });
    </script>
</body>
</html>
