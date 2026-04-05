<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/CashDetectorService.php';

$pdo = getDB();
$today = date('Y-m-d');

$detector = new CashDetectorService($pdo);

$stmtToday = $pdo->prepare("
    SELECT
        COALESCE(SUM(amount), 0) AS total_revenue,
        COUNT(*) AS total_sales,
        SUM(CASE WHEN payment_type = 'cash' THEN 1 ELSE 0 END) AS cash_sales,
        SUM(CASE WHEN payment_type <> 'cash' THEN 1 ELSE 0 END) AS online_sales
    FROM transaksi
    WHERE status = 'settlement' AND DATE(paid_at) = ?
");
$stmtToday->execute([$today]);
$todaySummary = $stmtToday->fetch();

$stmtPending = $pdo->query("
    SELECT t.order_id, t.amount, p.nama_paket
    FROM transaksi t
    JOIN paket_voucher p ON p.id = t.paket_id
    WHERE t.payment_type = 'cash' AND t.status = 'pending'
    ORDER BY t.created_at ASC
");
$pendingOrders = $stmtPending->fetchAll();
$pendingCount = count($pendingOrders);
$pendingAmount = array_sum(array_map(static fn($o) => (int) $o['amount'], $pendingOrders));

$latestDetections = $detector->getLatestDetectionsByOrderIds(array_column($pendingOrders, 'order_id'));
$readyToApprove = 0;
$blockedCounterfeit = 0;
$needRescan = 0;

foreach ($pendingOrders as $order) {
    $detection = $latestDetections[$order['order_id']] ?? null;
    if (!$detection) {
        $needRescan++;
        continue;
    }

    if ($detection['verdict'] === 'genuine') {
        $readyToApprove++;
    } elseif ($detection['verdict'] === 'counterfeit') {
        $blockedCounterfeit++;
    } else {
        $needRescan++;
    }
}

$stmtRecent = $pdo->query("
    SELECT t.order_id, t.amount, t.payment_type, t.paid_at, p.nama_paket
    FROM transaksi t
    JOIN paket_voucher p ON p.id = t.paket_id
    WHERE t.status = 'settlement'
    ORDER BY t.paid_at DESC
    LIMIT 6
");
$recentSales = $stmtRecent->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — RipaNet Admin</title>
    <link rel="icon" type="image/png" href="../assets/img/logo-RipaNet.png">
    <link rel="stylesheet" href="../assets/css/style.css?v=5">
</head>
<body>
    <div class="container admin-shell">
        <header class="admin-topbar">
            <a class="brand" href="index.php">
                <img class="brand__logo" src="../assets/img/logo-RipaNet.png" alt="Logo RipaNet">
                <span class="brand__meta">
                    <strong>RipaNet Admin</strong>
                    <span>Dashboard</span>
                </span>
            </a>
            <nav class="admin-topbar__links">
                <a href="index.php" class="active">Dashboard</a>
                <a href="pos.php">Penjualan</a>
                <a href="cash-orders.php">Antrian</a>
                <a href="products.php">Produk</a>
                <a href="logout.php" class="danger">Keluar</a>
            </nav>
        </header>

        <section class="admin-kpis">
            <article class="kpi-card">
                <div class="kpi-card__label">Pendapatan Hari Ini</div>
                <div class="kpi-card__value">Rp <?= number_format((int) $todaySummary['total_revenue'], 0, ',', '.') ?></div>
                <div class="kpi-card__hint"><?= number_format((int) $todaySummary['total_sales']) ?> transaksi</div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Antrian Tunai</div>
                <div class="kpi-card__value"><?= number_format($pendingCount) ?></div>
                <div class="kpi-card__hint">Rp <?= number_format($pendingAmount, 0, ',', '.') ?></div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Siap Dikonfirmasi</div>
                <div class="kpi-card__value"><?= number_format($readyToApprove) ?></div>
                <div class="kpi-card__hint">Sudah terverifikasi</div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Perlu Tindakan</div>
                <div class="kpi-card__value"><?= number_format($blockedCounterfeit + $needRescan) ?></div>
                <div class="kpi-card__hint">Ditolak: <?= number_format($blockedCounterfeit) ?> | Scan ulang: <?= number_format($needRescan) ?></div>
            </article>
        </section>

        <section class="dashboard-grid">
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Menu Utama</h2>
                        <p>Akses cepat ke fitur yang tersedia.</p>
                    </div>
                </div>
                <div class="summary-grid">
                    <article class="summary-item">
                        <h4>Terminal Penjualan</h4>
                        <p>Buat pesanan tunai, verifikasi uang, dan konfirmasi pembayaran.</p>
                        <a class="btn btn-primary btn-sm" href="pos.php">Buka Penjualan</a>
                    </article>
                    <article class="summary-item">
                        <h4>Antrian Tunai</h4>
                        <p>Lihat dan proses pesanan tunai dari pelanggan.</p>
                        <a class="btn btn-secondary btn-sm" href="cash-orders.php">Buka Antrian</a>
                    </article>
                    <article class="summary-item">
                        <h4>Kelola Produk</h4>
                        <p>Tambah, edit, atau nonaktifkan paket voucher.</p>
                        <a class="btn btn-secondary btn-sm" href="products.php">Kelola Produk</a>
                    </article>
                    <article class="summary-item">
                        <h4>Halaman Pelanggan</h4>
                        <p>Lihat halaman pembelian voucher untuk pelanggan.</p>
                        <a class="btn btn-secondary btn-sm" href="../" target="_blank">Buka</a>
                    </article>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h3>Transaksi Terbaru</h3>
                        <p>6 transaksi terakhir yang berhasil.</p>
                    </div>
                </div>

                <?php if (empty($recentSales)): ?>
                    <div class="empty-state">
                        <strong>Belum ada transaksi.</strong>
                        <span>Transaksi akan muncul setelah pembayaran berhasil.</span>
                    </div>
                <?php else: ?>
                    <div class="history-list">
                        <?php foreach ($recentSales as $sale): ?>
                            <article class="history-item">
                                <div class="history-item__top">
                                    <div>
                                        <strong><?= htmlspecialchars($sale['nama_paket']) ?></strong>
                                        <div class="history-item__meta">
                                            <span class="mono"><?= htmlspecialchars($sale['order_id']) ?></span>
                                            <span><?= date('d M H:i', strtotime($sale['paid_at'])) ?></span>
                                        </div>
                                    </div>
                                    <span class="status-badge status-badge--success"><?= strtoupper(htmlspecialchars($sale['payment_type'] ?: 'online')) ?></span>
                                </div>
                                <div class="queue-item__meta">
                                    <span>Rp <?= number_format($sale['amount'], 0, ',', '.') ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </div>
</body>
</html>
