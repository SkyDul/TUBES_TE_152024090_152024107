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
    LIMIT 100
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
    <link rel="stylesheet" href="../assets/css/style.css?v=7">
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
                <div class="panel-head" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                    <div>
                        <h3>Antrian & Riwayat</h3>
                        <p>Pesanan pending dari pelanggan dan riwayat hari ini.</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.location.reload();" style="flex-shrink: 0;" title="Muat ulang data antrian">
                        <span class="icon icon--sm"><svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><polyline points="3 3 3 8 8 8"/></svg></span>
                        Muat Ulang
                    </button>
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
                                <button type="button" class="btn btn-accent btn-sm verify-btn" data-order-id="<?= htmlspecialchars($order['order_id']) ?>" data-amount="<?= (int) $order['amount'] ?>" data-paket="<?= htmlspecialchars($order['nama_paket']) ?>">
                                    <span class="icon icon--sm"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                                    Verifikasi
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
                    <?php if (count($recentCashSales) > 5): ?>
                    <div class="history-pagination" style="display: flex; justify-content: center; align-items: center; gap: 12px; margin-top: 15px;">
                        <button type="button" class="btn btn-secondary btn-sm" id="hist-prev" style="padding: 4px 8px;">&laquo; Prev</button>
                        <span id="hist-page-info" style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted); min-width: 40px; text-align: center;">1 / 1</span>
                        <button type="button" class="btn btn-secondary btn-sm" id="hist-next" style="padding: 4px 8px;">Next &raquo;</button>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </section>
    </div>

    <!-- Verification Modal -->
    <div class="modal-overlay" id="verify-modal">
        <div class="modal-content verify-modal-content">
            <button class="modal-close" id="verify-modal-close" type="button">&times;</button>
            
            <!-- Step 1: Input Cash -->
            <div id="verify-step-input">
                <div class="verify-modal-icon">
                    <svg viewBox="0 0 24 24" width="32" height="32" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <h3 class="verify-modal-title">Verifikasi Uang Tunai</h3>
                <p class="verify-modal-subtitle" id="verify-paket-info"></p>
                <div class="verify-amount-badge" id="verify-amount-badge"></div>
                
                <div class="verify-camera-box" style="margin-bottom: 16px;">
                    <input type="file" id="verify-image-input" accept="image/*" style="display:none;">
                    <div id="verify-image-preview" style="width: 100%; border-radius: 8px; background: var(--bg-alt); height: 180px; display: flex; align-items: center; justify-content: center; cursor: pointer; overflow: hidden; border: 2px dashed var(--border); transition: all 0.2s;">
                        <div style="text-align: center; color: var(--text-muted);" id="verify-image-placeholder">
                            <svg viewBox="0 0 24 24" width="32" height="32" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 8px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <div style="font-size: 0.85rem; font-weight: 600;">Ambil Foto / Pilih File</div>
                        </div>
                        <img id="verify-image-display" src="" style="display:none; width: 100%; height: 100%; object-fit: contain;">
                    </div>
                </div>

                <label class="verify-label" for="verify-cash-input">Jumlah Uang dari Pelanggan</label>
                <input type="number" class="verify-input" id="verify-cash-input" placeholder="Contoh: 50000" min="0" autocomplete="off">
                <div class="verify-hint" id="verify-change-hint"></div>
                <div class="verify-actions">
                    <button type="button" class="btn btn-secondary" id="verify-cancel-btn">Batal</button>
                    <button type="button" class="btn btn-primary" id="verify-detect-btn" disabled>
                        <span class="icon icon--sm"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                        Deteksi Keaslian
                    </button>
                </div>
            </div>

            <!-- Step 2: Detection in progress -->
            <div id="verify-step-detecting" style="display:none;">
                <div class="verify-modal-icon verify-modal-icon--scanning">
                    <svg viewBox="0 0 24 24" width="32" height="32" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </div>
                <h3 class="verify-modal-title">Mendeteksi Uang...</h3>
                <p class="verify-modal-subtitle">Memverifikasi keaslian uang tunai. Mohon tunggu sebentar.</p>
                <div class="verify-progress">
                    <div class="verify-progress-bar"></div>
                </div>
            </div>

            <!-- Step 3: Result -->
            <div id="verify-step-result" style="display:none;">
                <div class="verify-modal-icon" id="verify-result-icon"></div>
                <h3 class="verify-modal-title" id="verify-result-title"></h3>
                <p class="verify-modal-subtitle" id="verify-result-desc"></p>
                <div class="verify-result-details" id="verify-result-details"></div>
                <div class="verify-actions" id="verify-result-actions"></div>
            </div>

            <!-- Step 4: Approving -->
            <div id="verify-step-approving" style="display:none;">
                <div class="verify-modal-icon verify-modal-icon--scanning">
                    <svg viewBox="0 0 24 24" width="32" height="32" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h3 class="verify-modal-title">Memproses Transaksi...</h3>
                <p class="verify-modal-subtitle">Mengkonfirmasi pembayaran dan menerbitkan voucher.</p>
                <div class="verify-progress">
                    <div class="verify-progress-bar"></div>
                </div>
            </div>
        </div>
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
        currentHistoryPage = 1;
        updateHistoryPagination();
    }

    // --- Pagination Logic ---
    let currentHistoryPage = 1;
    const historyItemsPerPage = 5;

    function updateHistoryPagination() {
        const list = document.getElementById('history-list');
        if (!list) return;
        const items = list.querySelectorAll('.history-item');
        if (items.length === 0) return;
        
        const totalPages = Math.ceil(items.length / historyItemsPerPage);
        if (currentHistoryPage > totalPages) currentHistoryPage = totalPages;
        if (currentHistoryPage < 1) currentHistoryPage = 1;

        items.forEach((item, index) => {
            const page = Math.floor(index / historyItemsPerPage) + 1;
            if (page === currentHistoryPage) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });

        const prevBtn = document.getElementById('hist-prev');
        const nextBtn = document.getElementById('hist-next');
        const info = document.getElementById('hist-page-info');

        if (prevBtn && nextBtn && info) {
            info.textContent = currentHistoryPage + ' / ' + totalPages;
            prevBtn.disabled = currentHistoryPage === 1;
            nextBtn.disabled = currentHistoryPage === totalPages;
            
            const pagContainer = prevBtn.parentElement;
            if (totalPages <= 1 && pagContainer) {
                pagContainer.style.display = 'none';
            } else if (pagContainer) {
                pagContainer.style.display = 'flex';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateHistoryPagination();
        
        const prevBtn = document.getElementById('hist-prev');
        const nextBtn = document.getElementById('hist-next');
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentHistoryPage > 1) { currentHistoryPage--; updateHistoryPagination(); }
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const items = document.querySelectorAll('#history-list .history-item');
                const totalPages = Math.ceil(items.length / historyItemsPerPage);
                if (currentHistoryPage < totalPages) { currentHistoryPage++; updateHistoryPagination(); }
            });
        }
    });

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
                removeQueueItem(orderId);
                prependHistory(data.data);
                showToast('Voucher ' + data.data.voucher_username + ' — Transaksi lunas!', 'success');
            } catch (e) {
                showToast('Gagal: ' + e.message, 'error');
            } finally {
                setLoading(btn, false);
            }
        });
    }
    document.querySelectorAll('.approve-btn').forEach(wireApproveBtn);

    function removeQueueItem(orderId) {
        const item = queueList.querySelector(`.queue-item[data-order-id="${orderId}"]`);
        if (item) item.remove();
        updatePendingCount();
        if (!queueList.querySelector('.queue-item')) {
            queueList.innerHTML = '<div class="empty-state"><strong>Tidak ada antrian.</strong><span>Semua pesanan sudah diproses.</span></div>';
        }
    }

    // --- Verification Flow ---
    const verifyModal = document.getElementById('verify-modal');
    const verifyCloseBtn = document.getElementById('verify-modal-close');
    const verifyCancelBtn = document.getElementById('verify-cancel-btn');
    const verifyCashInput = document.getElementById('verify-cash-input');
    const verifyDetectBtn = document.getElementById('verify-detect-btn');
    const verifyChangeHint = document.getElementById('verify-change-hint');
    const verifyPaketInfo = document.getElementById('verify-paket-info');
    const verifyAmountBadge = document.getElementById('verify-amount-badge');

    let currentVerifyOrderId = '';
    let currentVerifyAmount = 0;
    let selectedImageFile = null;

    const verifyImagePreview = document.getElementById('verify-image-preview');
    const verifyImageInput = document.getElementById('verify-image-input');
    const verifyImagePlaceholder = document.getElementById('verify-image-placeholder');
    const verifyImageDisplay = document.getElementById('verify-image-display');

    function showVerifyStep(step) {
        ['input','detecting','result','approving'].forEach(s => {
            document.getElementById('verify-step-' + s).style.display = s === step ? '' : 'none';
        });
    }

    verifyImagePreview.addEventListener('click', () => {
        verifyImageInput.click();
    });

    verifyImageInput.addEventListener('change', (e) => {
        if (e.target.files && e.target.files[0]) {
            selectedImageFile = e.target.files[0];
            const reader = new FileReader();
            reader.onload = function(e) {
                verifyImageDisplay.src = e.target.result;
                verifyImageDisplay.style.display = 'block';
                verifyImagePlaceholder.style.display = 'none';
                verifyImagePreview.style.borderColor = 'var(--primary)';
                checkVerifyReady();
            }
            reader.readAsDataURL(selectedImageFile);
        }
    });

    function checkVerifyReady() {
        const val = parseInt(verifyCashInput.value) || 0;
        verifyDetectBtn.disabled = (val <= 0 || !selectedImageFile);
    }

    function openVerifyModal(orderId, amount, paketName) {
        currentVerifyOrderId = orderId;
        currentVerifyAmount = amount;
        selectedImageFile = null;
        
        verifyPaketInfo.textContent = paketName + ' — ' + orderId;
        verifyAmountBadge.textContent = 'Total: Rp ' + amount.toLocaleString('id-ID');
        verifyCashInput.value = '';
        verifyChangeHint.textContent = '';
        
        // Reset image
        verifyImageInput.value = '';
        verifyImageDisplay.src = '';
        verifyImageDisplay.style.display = 'none';
        verifyImagePlaceholder.style.display = 'block';
        verifyImagePreview.style.borderColor = 'var(--border)';
        
        verifyDetectBtn.disabled = true;
        showVerifyStep('input');
        verifyModal.classList.add('active');
        setTimeout(() => verifyCashInput.focus(), 200);
    }

    function closeVerifyModal() {
        verifyModal.classList.remove('active');
    }

    verifyCloseBtn.addEventListener('click', closeVerifyModal);
    verifyCancelBtn.addEventListener('click', closeVerifyModal);
    verifyModal.addEventListener('click', e => { if (e.target === verifyModal) closeVerifyModal(); });

    verifyCashInput.addEventListener('input', () => {
        const val = parseInt(verifyCashInput.value) || 0;
        checkVerifyReady();
        if (val > 0 && val >= currentVerifyAmount) {
            const change = val - currentVerifyAmount;
            verifyChangeHint.innerHTML = change > 0
                ? `<span style="color:var(--success)">✓ Kembalian: Rp ${change.toLocaleString('id-ID')}</span>`
                : `<span style="color:var(--success)">✓ Uang pas</span>`;
        } else if (val > 0) {
            verifyChangeHint.innerHTML = `<span style="color:var(--danger)">✗ Kurang Rp ${(currentVerifyAmount - val).toLocaleString('id-ID')}</span>`;
        } else {
            verifyChangeHint.textContent = '';
        }
    });

    // Detect cash authenticity
    verifyDetectBtn.addEventListener('click', async () => {
        const cashReceived = parseInt(verifyCashInput.value) || 0;
        if (cashReceived <= 0) return;
        if (cashReceived < currentVerifyAmount) {
            showToast('Jumlah uang kurang dari total!', 'error');
            return;
        }

        showVerifyStep('detecting');

        try {
            const formData = new FormData();
            formData.append('order_id', currentVerifyOrderId);
            
            // Name the file properly for the PHP backend to accept
            formData.append('image', selectedImageFile, selectedImageFile.name || 'cash-verify.jpg');

            const res = await fetch('api/detect-cash.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Deteksi gagal.');
            }

            const det = data.data.detection;
            const isGenuine = det.verdict === 'genuine';
            showVerifyResult(isGenuine, det, cashReceived);

        } catch (e) {
            // If detection API fails, allow manual override
            showVerifyResult(true, { verdict: 'genuine', confidence: 1, confidence_percent: 100 }, cashReceived, true);
        }
    });

    function showVerifyResult(isGenuine, detection, cashReceived, isManual = false) {
        showVerifyStep('result');
        const iconEl = document.getElementById('verify-result-icon');
        const titleEl = document.getElementById('verify-result-title');
        const descEl = document.getElementById('verify-result-desc');
        const detailsEl = document.getElementById('verify-result-details');
        const actionsEl = document.getElementById('verify-result-actions');

        const change = Math.max(0, cashReceived - currentVerifyAmount);

        if (isGenuine) {
            iconEl.className = 'verify-modal-icon verify-modal-icon--success';
            iconEl.innerHTML = '<svg viewBox="0 0 24 24" width="32" height="32" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
            titleEl.textContent = isManual ? 'Verifikasi Manual' : 'Uang Terdeteksi Asli!';
            descEl.textContent = isManual
                ? 'Deteksi otomatis tidak tersedia. Anda dapat mengkonfirmasi secara manual.'
                : `Keaslian uang dikonfirmasi (${detection.confidence_percent}% yakin). Transaksi siap dilanjutkan.`;
            detailsEl.innerHTML = `
                <div class="verify-detail-row">
                    <span>Uang Diterima</span>
                    <strong>Rp ${cashReceived.toLocaleString('id-ID')}</strong>
                </div>
                <div class="verify-detail-row">
                    <span>Total Bayar</span>
                    <strong>Rp ${currentVerifyAmount.toLocaleString('id-ID')}</strong>
                </div>
                <div class="verify-detail-row verify-detail-row--highlight">
                    <span>Kembalian</span>
                    <strong>Rp ${change.toLocaleString('id-ID')}</strong>
                </div>
            `;
            actionsEl.innerHTML = `
                <button type="button" class="btn btn-secondary" onclick="closeVerifyModal()">Batal</button>
                <button type="button" class="btn btn-primary" id="verify-confirm-btn">
                    <span class="icon icon--sm"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
                    Konfirmasi & Selesaikan
                </button>
            `;

            // Wire confirm button
            document.getElementById('verify-confirm-btn').addEventListener('click', () => {
                doVerifiedApproval(currentVerifyOrderId, cashReceived);
            });
        } else {
            iconEl.className = 'verify-modal-icon verify-modal-icon--danger';
            iconEl.innerHTML = '<svg viewBox="0 0 24 24" width="32" height="32" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
            titleEl.textContent = 'Uang Terdeteksi Palsu!';
            descEl.textContent = `Keaslian uang tidak dapat dipastikan (${detection.confidence_percent}%). Tolak transaksi atau scan ulang.`;
            detailsEl.innerHTML = `
                <div class="verify-detail-row verify-detail-row--danger">
                    <span>Status</span>
                    <strong>TERDETEKSI PALSU</strong>
                </div>
            `;
            actionsEl.innerHTML = `
                <button type="button" class="btn btn-secondary" onclick="closeVerifyModal()">Tutup</button>
                <button type="button" class="btn btn-primary" onclick="showVerifyStep('input')">Scan Ulang</button>
            `;
        }
    }

    async function doVerifiedApproval(orderId, cashReceived) {
        showVerifyStep('approving');
        try {
            const res = await fetch('api/approve-cash.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ order_id: orderId, cash_received: cashReceived })
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.error || 'Gagal mengkonfirmasi.');

            closeVerifyModal();
            removeQueueItem(orderId);
            prependHistory(data.data);
            showSellResult(data.data);
            showToast('✅ Voucher ' + data.data.voucher_username + ' — Terverifikasi & Lunas!', 'success');
        } catch (e) {
            showVerifyStep('result');
            showToast('Gagal: ' + e.message, 'error');
        }
    }

    // Wire verify buttons
    function wireVerifyBtn(btn) {
        btn.addEventListener('click', () => {
            openVerifyModal(btn.dataset.orderId, parseInt(btn.dataset.amount), btn.dataset.paket);
        });
    }
    document.querySelectorAll('.verify-btn').forEach(wireVerifyBtn);

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
