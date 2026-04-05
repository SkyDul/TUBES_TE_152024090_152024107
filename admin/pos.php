<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$today = date('Y-m-d');

$packages = $pdo->query("
    SELECT id, nama_paket, harga, durasi_display, durasi_hari
    FROM paket_voucher
    WHERE is_active = 1
    ORDER BY harga ASC
")->fetchAll();

$stmtSummary = $pdo->prepare("
    SELECT
        COALESCE(SUM(amount), 0) AS total_cash,
        COUNT(*) AS total_transactions
    FROM transaksi
    WHERE status = 'settlement' AND payment_type = 'cash' AND DATE(paid_at) = ?
");
$stmtSummary->execute([$today]);
$cashSummary = $stmtSummary->fetch();

$stmtPending = $pdo->query("
    SELECT t.order_id, t.amount, t.created_at, p.nama_paket, p.durasi_display
    FROM transaksi t
    JOIN paket_voucher p ON p.id = t.paket_id
    WHERE t.payment_type = 'cash' AND t.status = 'pending'
    ORDER BY t.created_at ASC
");
$pendingOrders = $stmtPending->fetchAll();
$pendingCount = count($pendingOrders);

$stmtRecent = $pdo->prepare("
    SELECT t.*, p.nama_paket, p.durasi_display
    FROM transaksi t
    JOIN paket_voucher p ON t.paket_id = p.id
    WHERE t.payment_type = 'cash' AND t.status = 'settlement' AND DATE(t.paid_at) = ?
    ORDER BY t.paid_at DESC
    LIMIT 10
");
$stmtRecent->execute([$today]);
$recentCashSales = $stmtRecent->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjualan — RipaNet Admin</title>
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
                    <span>Penjualan</span>
                </span>
            </a>
            <button class="admin-nav-toggle" id="admin-nav-toggle" aria-label="Menu">
                <span class="icon"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></span>
            </button>
            <nav class="admin-topbar__links" id="admin-nav">
                <a href="index.php">Dashboard</a>
                <a href="pos.php" class="active">Penjualan</a>
                <a href="products.php">Produk</a>
                <a href="logout.php" class="danger">Keluar</a>
            </nav>
        </header>

        <section class="admin-kpis" style="grid-template-columns:repeat(3,1fr);">
            <article class="kpi-card">
                <div class="kpi-card__label">Tunai Hari Ini</div>
                <div class="kpi-card__value">Rp <?= number_format((int) $cashSummary['total_cash'], 0, ',', '.') ?></div>
                <div class="kpi-card__hint"><?= number_format((int) $cashSummary['total_transactions']) ?> transaksi</div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Antrian Pending</div>
                <div class="kpi-card__value" id="pending-kpi"><?= number_format($pendingCount) ?></div>
                <div class="kpi-card__hint">Menunggu konfirmasi</div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Kasir</div>
                <div class="kpi-card__value" style="font-size:1.2rem;"><?= htmlspecialchars($adminUser) ?></div>
                <div class="kpi-card__hint">Sesi aktif</div>
            </article>
        </section>

        <section class="pos-layout">
            <!-- LEFT: Sell Panel -->
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Jual Voucher</h2>
                        <p>Klik tombol untuk langsung menjual dan menerbitkan voucher.</p>
                    </div>
                </div>

                <div class="sell-grid">
                    <?php foreach ($packages as $package): ?>
                        <?php $priceDisplay = 'Rp ' . number_format((int) $package['harga'], 0, ',', '.'); ?>
                        <article class="product-option">
                            <span class="package-badge">Paket WiFi</span>
                            <div>
                                <h3><?= htmlspecialchars($package['nama_paket']) ?></h3>
                                <p class="helper-text"><?= htmlspecialchars($package['durasi_display']) ?></p>
                            </div>
                            <div class="product-option__price"><?= $priceDisplay ?></div>
                            <button
                                type="button"
                                class="btn btn-primary btn-sm quick-sell-btn"
                                data-id="<?= (int) $package['id'] ?>"
                                data-name="<?= htmlspecialchars($package['nama_paket'], ENT_QUOTES, 'UTF-8') ?>"
                                data-price-display="<?= htmlspecialchars($priceDisplay, ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <span class="icon icon--sm"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
                                Jual
                            </button>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="sell-result" id="sell-result"></div>
            </section>

            <!-- RIGHT: Queue + History -->
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h3>Antrian & Riwayat</h3>
                        <p>Pesanan pending dari pelanggan dan riwayat hari ini.</p>
                    </div>
                </div>

                <?php if ($pendingCount > 0): ?>
                <div class="panel-head" style="margin-bottom:0;">
                    <div><strong style="font-size:0.85rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Menunggu Konfirmasi (<?= $pendingCount ?>)</strong></div>
                </div>
                <div id="queue-list" class="queue-list" style="margin-bottom:20px;">
                    <?php foreach ($pendingOrders as $order): ?>
                        <article class="queue-item" data-order-id="<?= htmlspecialchars($order['order_id']) ?>">
                            <div class="queue-item__top">
                                <div>
                                    <strong><?= htmlspecialchars($order['nama_paket']) ?></strong>
                                    <div class="queue-item__meta">
                                        <span class="mono"><?= htmlspecialchars($order['order_id']) ?></span>
                                        <span>Rp <?= number_format($order['amount'], 0, ',', '.') ?></span>
                                    </div>
                                </div>
                                <span class="status-badge status-badge--pending">Pending</span>
                            </div>
                            <div class="queue-actions">
                                <button type="button" class="btn btn-primary btn-sm approve-btn" data-order-id="<?= htmlspecialchars($order['order_id']) ?>">
                                    <span class="icon icon--sm"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
                                    Konfirmasi Lunas
                                </button>
                                <a class="btn btn-secondary btn-sm" href="../checkout-cash.php?order_id=<?= urlencode($order['order_id']) ?>" target="_blank">
                                    <span class="icon icon--sm"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
                                    Lihat
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div id="queue-list" class="queue-list" style="margin-bottom:20px;">
                    <div class="empty-state" id="queue-empty">
                        <strong>Tidak ada antrian.</strong>
                        <span>Pesanan tunai dari pelanggan akan muncul di sini.</span>
                    </div>
                </div>
                <?php endif; ?>

                <div class="panel-head" style="margin-bottom:0;">
                    <div><strong style="font-size:0.85rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Riwayat Hari Ini</strong></div>
                </div>
                <?php if (empty($recentCashSales)): ?>
                    <div class="empty-state">
                        <strong>Belum ada transaksi.</strong>
                        <span>Riwayat muncul setelah penjualan berhasil.</span>
                    </div>
                <?php else: ?>
                    <div class="history-list" id="history-list">
                        <?php foreach ($recentCashSales as $sale): ?>
                            <article class="history-item">
                                <div class="history-item__top">
                                    <div>
                                        <strong><?= htmlspecialchars($sale['nama_paket']) ?></strong>
                                        <div class="history-item__meta">
                                            <span class="mono"><?= htmlspecialchars($sale['order_id']) ?></span>
                                            <span><?= date('H:i', strtotime($sale['paid_at'])) ?></span>
                                        </div>
                                    </div>
                                    <span class="status-badge status-badge--success">Lunas</span>
                                </div>
                                <div class="queue-item__meta">
                                    <span>Rp <?= number_format($sale['amount'], 0, ',', '.') ?></span>
                                    <span>Voucher <?= htmlspecialchars($sale['mikrotik_user']) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </div>

    <script>
    // --- Utility ---
    function escapeHtml(v) {
        return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function showToast(msg, type = 'success') {
        const existing = document.querySelector('.toast-v2');
        if (existing) existing.remove();
        const el = document.createElement('div');
        el.className = 'toast-v2 toast-v2--' + type;
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => { el.classList.add('hiding'); setTimeout(() => el.remove(), 300); }, 3000);
    }

    function setLoading(btn, loading) {
        if (loading) {
            btn.disabled = true;
            btn.dataset.origLabel = btn.innerHTML;
            btn.innerHTML = '<span class="spinner"></span> Proses...';
        } else {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.origLabel || 'Jual';
        }
    }

    const sellResult = document.getElementById('sell-result');
    const queueList = document.getElementById('queue-list');
    const pendingKpi = document.getElementById('pending-kpi');

    function updatePendingCount() {
        const c = queueList.querySelectorAll('.queue-item').length;
        pendingKpi.textContent = c.toLocaleString('id-ID');
    }

    // --- Quick Sell ---
    function showSellResult(data) {
        sellResult.innerHTML = `
            <div class="sell-result__header">
                <span class="icon icon--lg"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                Voucher Diterbitkan
            </div>
            <div class="sell-result__voucher">${escapeHtml(data.voucher_username)}</div>
            <div class="sell-result__meta">
                <span>${escapeHtml(data.paket_nama)}</span>
                <span>${escapeHtml(data.amount_display)}</span>
                <span>${escapeHtml(data.order_id)}</span>
            </div>
            <div class="actions-row">
                <a class="btn btn-primary btn-sm" href="${escapeHtml(data.success_url)}" target="_blank">
                    <span class="icon icon--sm"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
                    Buka Voucher
                </a>
                <a class="btn btn-secondary btn-sm" href="${escapeHtml(data.invoice_url)}" target="_blank">
                    <span class="icon icon--sm"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                    Cetak Struk
                </a>
            </div>
        `;
        sellResult.classList.add('active');
    }

    function prependHistory(data) {
        let list = document.getElementById('history-list');
        if (!list) {
            const emptyState = document.querySelector('.pos-layout .panel:last-child .empty-state:last-of-type');
            if (emptyState) {
                const wrapper = document.createElement('div');
                wrapper.className = 'history-list';
                wrapper.id = 'history-list';
                emptyState.parentElement.insertBefore(wrapper, emptyState);
                emptyState.remove();
                list = wrapper;
            }
        }
        if (!list) return;
        const t = new Date().toLocaleTimeString('id-ID', {hour:'2-digit',minute:'2-digit'});
        list.insertAdjacentHTML('afterbegin', `
            <article class="history-item">
                <div class="history-item__top">
                    <div>
                        <strong>${escapeHtml(data.paket_nama)}</strong>
                        <div class="history-item__meta">
                            <span class="mono">${escapeHtml(data.order_id)}</span>
                            <span>${t}</span>
                        </div>
                    </div>
                    <span class="status-badge status-badge--success">Lunas</span>
                </div>
                <div class="queue-item__meta">
                    <span>${escapeHtml(data.amount_display)}</span>
                    <span>Voucher ${escapeHtml(data.voucher_username)}</span>
                </div>
            </article>
        `);
    }

    document.querySelectorAll('.quick-sell-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            setLoading(btn, true);
            try {
                const res = await fetch('api/quick-sell.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ paket_id: Number(btn.dataset.id) })
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.error || 'Gagal menjual.');
                showSellResult(data.data);
                prependHistory(data.data);
                showToast('Voucher ' + data.data.voucher_username + ' berhasil diterbitkan!', 'success');
            } catch (e) {
                showToast('Gagal: ' + e.message, 'error');
            } finally {
                setLoading(btn, false);
            }
        });
    });

    // --- Queue Approval ---
    function wireApproveBtn(btn) {
        btn.addEventListener('click', async () => {
            const orderId = btn.dataset.orderId;
            setLoading(btn, true);
            try {
                const res = await fetch('api/approve-cash.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ order_id: orderId, cash_received: 0 })
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.error || 'Gagal mengkonfirmasi.');
                const item = btn.closest('.queue-item');
                if (item) item.remove();
                prependHistory(data.data);
                updatePendingCount();
                if (!queueList.querySelector('.queue-item')) {
                    queueList.innerHTML = '<div class="empty-state"><strong>Tidak ada antrian.</strong><span>Semua pesanan sudah diproses.</span></div>';
                }
                showToast('Voucher ' + data.data.voucher_username + ' — Transaksi lunas!', 'success');
            } catch (e) {
                showToast('Gagal: ' + e.message, 'error');
            } finally {
                setLoading(btn, false);
            }
        });
    }
    document.querySelectorAll('.approve-btn').forEach(wireApproveBtn);

    // --- Admin nav toggle ---
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
