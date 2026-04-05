<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$today = date('Y-m-d');

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
    SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS amt
    FROM transaksi
    WHERE payment_type = 'cash' AND status = 'pending'
");
$pending = $stmtPending->fetch();

$stmtRecent = $pdo->query("
    SELECT t.order_id, t.amount, t.payment_type, t.paid_at, p.nama_paket
    FROM transaksi t
    JOIN paket_voucher p ON p.id = t.paket_id
    WHERE t.status = 'settlement'
    ORDER BY t.paid_at DESC
    LIMIT 8
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
    <link rel="stylesheet" href="../assets/css/style.css?v=6">
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
            <button class="admin-nav-toggle" id="admin-nav-toggle" aria-label="Menu">
                <span class="icon"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></span>
            </button>
            <nav class="admin-topbar__links" id="admin-nav">
                <a href="index.php" class="active">Dashboard</a>
                <a href="pos.php">Penjualan</a>
                <a href="products.php">Produk</a>
                <a href="logout.php" class="danger">Keluar</a>
            </nav>
        </header>

        <section class="admin-kpis" style="grid-template-columns:repeat(3,1fr);">
            <article class="kpi-card">
                <div class="kpi-card__label">Pendapatan Hari Ini</div>
                <div class="kpi-card__value">Rp <?= number_format((int) $todaySummary['total_revenue'], 0, ',', '.') ?></div>
                <div class="kpi-card__hint"><?= number_format((int) $todaySummary['total_sales']) ?> transaksi</div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Antrian Tunai</div>
                <div class="kpi-card__value"><?= number_format((int) $pending['cnt']) ?></div>
                <div class="kpi-card__hint">Rp <?= number_format((int) $pending['amt'], 0, ',', '.') ?></div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Metode Pembayaran</div>
                <div class="kpi-card__value" style="font-size:1.1rem;">Tunai <?= (int)$todaySummary['cash_sales'] ?> / Online <?= (int)$todaySummary['online_sales'] ?></div>
                <div class="kpi-card__hint">Hari ini</div>
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
                        <p>Jual voucher tunai dengan satu klik. Cepat dan tanpa ribet.</p>
                        <a class="btn btn-primary btn-sm" href="pos.php">
                            <span class="icon icon--sm"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
                            Buka Penjualan
                        </a>
                    </article>
                    <article class="summary-item">
                        <h4>Kelola Produk</h4>
                        <p>Tambah, edit, atau nonaktifkan paket voucher.</p>
                        <a class="btn btn-secondary btn-sm" href="products.php">
                            <span class="icon icon--sm"><svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></span>
                            Kelola Produk
                        </a>
                    </article>
                    <article class="summary-item">
                        <h4>Halaman Pelanggan</h4>
                        <p>Lihat halaman pembelian voucher untuk pelanggan.</p>
                        <a class="btn btn-secondary btn-sm" href="../" target="_blank">
                            <span class="icon icon--sm"><svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></span>
                            Buka
                        </a>
                    </article>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h3>Transaksi Terbaru</h3>
                        <p>Transaksi terakhir yang berhasil.</p>
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

    <script>
    const navToggle = document.getElementById('admin-nav-toggle');
    const adminNav = document.getElementById('admin-nav');
    if (navToggle && adminNav) {
        navToggle.addEventListener('click', () => adminNav.classList.toggle('open'));
        document.addEventListener('click', e => {
            if (!navToggle.contains(e.target) && !adminNav.contains(e.target)) adminNav.classList.remove('open');
        });
    }
    </script>
</body>
</html>
