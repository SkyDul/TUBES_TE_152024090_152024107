<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/CashDetectorService.php';

$pdo = getDB();
$today = date('Y-m-d');
$detector = new CashDetectorService($pdo);

$packages = $pdo->query("
    SELECT id, nama_paket, harga, durasi_display, durasi_hari
    FROM paket_voucher
    WHERE is_active = 1
    ORDER BY harga ASC
")->fetchAll();

$stmtSummary = $pdo->prepare("
    SELECT
        COALESCE(SUM(amount), 0) AS total_cash,
        COUNT(*) AS total_transactions,
        COALESCE(AVG(amount), 0) AS average_ticket
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
$totalPendingAmount = array_sum(array_map(static fn($order) => (int) $order['amount'], $pendingOrders));

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

$stmtRecent = $pdo->prepare("
    SELECT t.*, p.nama_paket, p.durasi_display
    FROM transaksi t
    JOIN paket_voucher p ON t.paket_id = p.id
    WHERE t.payment_type = 'cash' AND t.status = 'settlement' AND DATE(t.paid_at) = ?
    ORDER BY t.paid_at DESC
    LIMIT 8
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
    <link rel="stylesheet" href="../assets/css/style.css?v=5">
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
            <nav class="admin-topbar__links">
                <a href="index.php">Dashboard</a>
                <a href="pos.php" class="active">Penjualan</a>
                <a href="cash-orders.php">Antrian</a>
                <a href="products.php">Produk</a>
                <a href="logout.php" class="danger">Keluar</a>
            </nav>
        </header>

        <section class="admin-hero">
            <div class="admin-hero__main">
                <span class="eyebrow" style="background:rgba(255,255,255,0.1);color:#fde68a;border-color:rgba(255,255,255,0.15);">Penjualan Tunai</span>
                <h1 style="margin-top:14px;">Buat pesanan tunai, verifikasi uang, dan konfirmasi pembayaran.</h1>
                <p>Proses penjualan tunai dari kasir dengan verifikasi uang sebelum konfirmasi.</p>
                <div class="admin-chip-row">
                    <span class="admin-chip">Kasir: <?= htmlspecialchars($adminUser) ?></span>
                    <span class="admin-chip">Menunggu: <span id="pending-count"><?= number_format($pendingCount) ?></span></span>
                    <span class="admin-chip">Siap konfirmasi: <span id="ready-count"><?= number_format($readyToApprove) ?></span></span>
                    <span class="admin-chip">Perlu tindakan: <span id="attention-count"><?= number_format($blockedCounterfeit + $needRescan) ?></span></span>
                </div>
            </div>
            <aside class="admin-hero__side">
                <h3>Alur Penjualan</h3>
                <div class="summary-grid" style="margin-top:16px;">
                    <article class="summary-item">
                        <h4>1. Buat Pesanan</h4>
                        <p>Pilih paket, pesanan tunai dibuat dengan status menunggu.</p>
                    </article>
                    <article class="summary-item">
                        <h4>2. Verifikasi Uang</h4>
                        <p>Foto uang dari pelanggan untuk memastikan keaslian.</p>
                    </article>
                    <article class="summary-item">
                        <h4>3. Konfirmasi</h4>
                        <p>Setelah lolos verifikasi, konfirmasi untuk menerbitkan voucher.</p>
                    </article>
                </div>
            </aside>
        </section>

        <section class="admin-kpis">
            <article class="kpi-card">
                <div class="kpi-card__label">Menunggu Proses</div>
                <div class="kpi-card__value" id="pending-kpi"><?= number_format($pendingCount) ?></div>
                <div class="kpi-card__hint">Rp <?= number_format($totalPendingAmount, 0, ',', '.') ?></div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Siap Dikonfirmasi</div>
                <div class="kpi-card__value" id="ready-kpi"><?= number_format($readyToApprove) ?></div>
                <div class="kpi-card__hint">Sudah terverifikasi</div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Ditolak</div>
                <div class="kpi-card__value" id="blocked-kpi"><?= number_format($blockedCounterfeit) ?></div>
                <div class="kpi-card__hint">Tidak bisa dikonfirmasi</div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Tunai Hari Ini</div>
                <div class="kpi-card__value">Rp <?= number_format((int) $cashSummary['total_cash'], 0, ',', '.') ?></div>
                <div class="kpi-card__hint"><?= number_format((int) $cashSummary['total_transactions']) ?> transaksi</div>
            </article>
        </section>

        <section class="pos-layout">
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Buat Pesanan Baru</h2>
                        <p>Pilih paket, lalu proses secara manual atau otomatis.</p>
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
                            <div class="product-option__meta">
                                <span><?= htmlspecialchars($package['durasi_display']) ?></span>
                                <span>Tunai</span>
                            </div>
                            <div class="queue-actions">
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm create-cash-btn"
                                    data-id="<?= (int) $package['id'] ?>"
                                    data-name="<?= htmlspecialchars($package['nama_paket'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-duration="<?= htmlspecialchars($package['durasi_display'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-price-display="<?= htmlspecialchars($priceDisplay, ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    Buat Pesanan
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm quick-pipeline-btn"
                                    data-id="<?= (int) $package['id'] ?>"
                                    data-name="<?= htmlspecialchars($package['nama_paket'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-duration="<?= htmlspecialchars($package['durasi_display'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-price-display="<?= htmlspecialchars($priceDisplay, ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    Proses Cepat
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="terminal-output" style="margin-top: 22px;">
                    <div class="terminal-output__label">Log Aktivitas</div>
                    <div class="terminal-output__body" id="terminal-output-body">
                        <strong>Terminal siap.</strong>
                        <span>Pilih paket untuk membuat pesanan baru.</span>
                    </div>
                </div>

                <div class="result-card" id="terminal-result"></div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h3>Antrian Pesanan</h3>
                        <p>Pesanan yang belum dikonfirmasi. Verifikasi uang terlebih dahulu.</p>
                    </div>
                    <a class="btn btn-secondary btn-sm" href="pos.php">Muat Ulang</a>
                </div>

                <div id="queue-list" class="queue-list">
                    <?php if (empty($pendingOrders)): ?>
                        <div class="empty-state">
                            <strong>Tidak ada pesanan menunggu.</strong>
                            <span>Buat pesanan baru dari panel di sebelah kiri.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingOrders as $order): ?>
                            <?php
                            $detection = $latestDetections[$order['order_id']] ?? null;
                            $verdict = $detection['verdict'] ?? 'unknown';
                            $isGenuine = $verdict === 'genuine';
                            $isCounterfeit = $verdict === 'counterfeit';
                            $badgeClass = 'status-badge status-badge--pending';
                            $badgeLabel = 'Belum discan';
                            if ($isGenuine) {
                                $badgeClass = 'status-badge status-badge--success';
                                $badgeLabel = 'Asli';
                            } elseif ($isCounterfeit) {
                                $badgeClass = 'status-badge status-badge--danger';
                                $badgeLabel = 'Palsu';
                            } elseif ($verdict === 'uncertain') {
                                $badgeLabel = 'Belum Pasti';
                            }
                            ?>
                            <article
                                class="queue-item"
                                data-order-id="<?= htmlspecialchars($order['order_id']) ?>"
                                data-amount="<?= (int) $order['amount'] ?>"
                                data-package-name="<?= htmlspecialchars($order['nama_paket'], ENT_QUOTES, 'UTF-8') ?>"
                                data-duration="<?= htmlspecialchars($order['durasi_display'], ENT_QUOTES, 'UTF-8') ?>"
                                data-verdict="<?= htmlspecialchars($verdict, ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <div class="queue-item__top">
                                    <div>
                                        <strong><?= htmlspecialchars($order['nama_paket']) ?></strong>
                                        <div class="queue-item__meta">
                                            <span class="mono"><?= htmlspecialchars($order['order_id']) ?></span>
                                            <span><?= date('d M Y H:i', strtotime($order['created_at'])) ?></span>
                                            <span>Rp <?= number_format($order['amount'], 0, ',', '.') ?></span>
                                        </div>
                                    </div>
                                    <span class="<?= $badgeClass ?> detection-badge"><?= $badgeLabel ?></span>
                                </div>

                                <div class="queue-item__meta detection-meta">
                                    <?php if ($detection): ?>
                                        <span>Scan: <?= date('H:i:s', strtotime($detection['created_at'])) ?></span>
                                        <span>Confidence: <?= number_format(((float) $detection['confidence']) * 100, 2) ?>%</span>
                                        <span>Mode: <?= htmlspecialchars($detection['detector_mode']) ?></span>
                                    <?php else: ?>
                                        <span>Belum ada hasil deteksi untuk order ini.</span>
                                    <?php endif; ?>
                                </div>

                                <div class="queue-actions">
                                    <button type="button" class="btn btn-secondary btn-sm detect-cash-btn" data-order-id="<?= htmlspecialchars($order['order_id']) ?>">
                                        Verifikasi
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm approve-queue-btn"
                                        data-order-id="<?= htmlspecialchars($order['order_id']) ?>"
                                        <?= $isGenuine ? '' : 'disabled' ?>
                                    >
                                        Konfirmasi & Lunasi
                                    </button>
                                    <a class="btn btn-secondary btn-sm" href="../checkout-cash.php?order_id=<?= urlencode($order['order_id']) ?>" target="_blank">Lihat</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </section>

        <section class="dashboard-grid">
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Riwayat Tunai Hari Ini</h2>
                        <p>Transaksi tunai yang berhasil hari ini.</p>
                    </div>
                </div>

                <?php if (empty($recentCashSales)): ?>
                    <div class="empty-state">
                        <strong>Belum ada transaksi tunai hari ini.</strong>
                        <span>Riwayat akan muncul setelah konfirmasi pembayaran.</span>
                    </div>
                <?php else: ?>
                    <div class="history-list">
                        <?php foreach ($recentCashSales as $sale): ?>
                            <?php
                            $approvedBy = '-';
                            $detectionLabel = '-';
                            if (!empty($sale['raw_notification'])) {
                                $raw = json_decode($sale['raw_notification'], true);
                                if (is_array($raw)) {
                                    if (isset($raw['approved_by'])) {
                                        $approvedBy = $raw['approved_by'];
                                    }
                                    if (isset($raw['detection']['verdict'])) {
                                        $detectionLabel = strtoupper($raw['detection']['verdict']);
                                    }
                                }
                            }
                            ?>
                            <article class="history-item">
                                <div class="history-item__top">
                                    <div>
                                        <strong><?= htmlspecialchars($sale['nama_paket']) ?></strong>
                                        <div class="history-item__meta">
                                            <span class="mono"><?= htmlspecialchars($sale['order_id']) ?></span>
                                            <span><?= date('H:i', strtotime($sale['paid_at'])) ?></span>
                                            <span><?= htmlspecialchars($sale['durasi_display']) ?></span>
                                        </div>
                                    </div>
                                    <span class="status-badge status-badge--success">Lunas</span>
                                </div>
                                <div class="queue-item__meta">
                                    <span>Nominal Rp <?= number_format($sale['amount'], 0, ',', '.') ?></span>
                                    <span>Oleh: <?= htmlspecialchars($approvedBy) ?></span>
                                    <span>Verifikasi: <?= htmlspecialchars($detectionLabel) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h3>Status Sistem</h3>
                        <p>Informasi tentang sistem verifikasi pembayaran.</p>
                    </div>
                </div>
                <div class="summary-grid">
                    <article class="summary-item">
                        <h4>Verifikasi Uang</h4>
                        <p>Sistem memverifikasi keaslian uang sebelum konfirmasi pembayaran.</p>
                    </article>
                    <article class="summary-item">
                        <h4>Konfirmasi Aman</h4>
                        <p>Hanya pesanan yang sudah lolos verifikasi yang bisa dikonfirmasi.</p>
                    </article>
                    <article class="summary-item">
                        <h4>Voucher Otomatis</h4>
                        <p>Setelah konfirmasi, voucher langsung diterbitkan dan siap digunakan.</p>
                    </article>
                </div>
            </section>
        </section>
    </div>

    <script>
    const terminalBody = document.getElementById('terminal-output-body');
    const terminalResult = document.getElementById('terminal-result');
    const queueList = document.getElementById('queue-list');

    const pendingCountEl = document.getElementById('pending-count');
    const pendingKpiEl = document.getElementById('pending-kpi');
    const readyCountEl = document.getElementById('ready-count');
    const readyKpiEl = document.getElementById('ready-kpi');
    const attentionCountEl = document.getElementById('attention-count');
    const blockedKpiEl = document.getElementById('blocked-kpi');

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setIntegerText(node, value) {
        node.textContent = Number(value).toLocaleString('id-ID');
    }

    function recalcCounters() {
        const items = Array.from(queueList.querySelectorAll('.queue-item'));
        let pending = items.length;
        let ready = 0;
        let blocked = 0;
        let attention = 0;

        items.forEach((item) => {
            const verdict = item.dataset.verdict || 'unknown';
            if (verdict === 'genuine') {
                ready += 1;
            } else if (verdict === 'counterfeit') {
                blocked += 1;
                attention += 1;
            } else {
                attention += 1;
            }
        });

        setIntegerText(pendingCountEl, pending);
        setIntegerText(pendingKpiEl, pending);
        setIntegerText(readyCountEl, ready);
        setIntegerText(readyKpiEl, ready);
        setIntegerText(blockedKpiEl, blocked);
        setIntegerText(attentionCountEl, attention);
    }

    function writeTerminal(title, lines) {
        terminalBody.innerHTML = `
            <strong>${escapeHtml(title)}</strong>
            ${lines.map((line) => `<span>${escapeHtml(line)}</span>`).join('')}
        `;
    }

    function showResult(title, subtitle, code, linksHtml = '') {
        terminalResult.innerHTML = `
            <strong>${escapeHtml(title)}</strong>
            <span>${escapeHtml(subtitle)}</span>
            ${code ? `<code>${escapeHtml(code)}</code>` : ''}
            <div class="actions-row">${linksHtml}</div>
        `;
        terminalResult.classList.add('active');
    }

    function prependToHistory(approved) {
        let historyList = document.querySelector('.history-list');
        if (!historyList) {
            const emptyState = document.querySelector('.dashboard-grid .panel .empty-state');
            if (emptyState) {
                const listHtml = '<div class="history-list"></div>';
                emptyState.parentElement.insertAdjacentHTML('beforeend', listHtml);
                emptyState.remove();
                historyList = document.querySelector('.history-list');
            }
        }
        if (!historyList) return;

        const detectionVerdict = approved.detection && approved.detection.verdict ? approved.detection.verdict.toUpperCase() : '-';
        const nowTime = new Date().toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'});
        
        const html = `
            <article class="history-item">
                <div class="history-item__top">
                    <div>
                        <strong>${escapeHtml(approved.paket_nama)}</strong>
                        <div class="history-item__meta">
                            <span class="mono">${escapeHtml(approved.order_id)}</span>
                            <span>${nowTime}</span>
                            <span>${escapeHtml(approved.durasi_display)}</span>
                        </div>
                    </div>
                    <span class="status-badge status-badge--success">Lunas</span>
                </div>
                <div class="queue-item__meta">
                    <span>Nominal Rp ${Number(approved.amount).toLocaleString('id-ID')}</span>
                    <span>Kasir ${escapeHtml(approved.approved_by || '-')}</span>
                    <span>Deteksi ${escapeHtml(detectionVerdict)}</span>
                </div>
            </article>
        `;
        historyList.insertAdjacentHTML('afterbegin', html);
    }

    function verdictToBadge(verdict) {
        if (verdict === 'genuine') {
            return { label: 'ASLI', className: 'status-badge status-badge--success' };
        }
        if (verdict === 'counterfeit') {
            return { label: 'PALSU', className: 'status-badge status-badge--danger' };
        }
        if (verdict === 'uncertain') {
            return { label: 'TIDAK DIKENALI', className: 'status-badge status-badge--pending' };
        }
        if (verdict === 'error') {
            return { label: 'Error', className: 'status-badge status-badge--danger' };
        }
        return { label: 'Belum discan', className: 'status-badge status-badge--pending' };
    }

    function setQueueDetection(item, detection) {
        const verdict = detection ? detection.verdict : 'unknown';
        item.dataset.verdict = verdict;

        const badge = item.querySelector('.detection-badge');
        const meta = item.querySelector('.detection-meta');
        const approveButton = item.querySelector('.approve-queue-btn');

        const mapped = verdictToBadge(verdict);
        badge.className = mapped.className + ' detection-badge';
        badge.textContent = mapped.label;

        if (meta) {
            if (detection) {
                meta.innerHTML = `
                    <span>Scan: ${escapeHtml(new Date(detection.created_at).toLocaleTimeString('id-ID'))}</span>
                    <span>Mode: ${escapeHtml(detection.mode)}</span>
                `;
            } else {
                meta.innerHTML = '<span>Belum ada hasil deteksi untuk order ini.</span>';
            }
        }

        if (approveButton) {
            approveButton.disabled = verdict !== 'genuine';
        }

        recalcCounters();
    }

    function setLoading(button, loading, label) {
        if (loading) {
            button.disabled = true;
            button.dataset.originalLabel = button.innerHTML;
            button.innerHTML = `<span class="spinner"></span> ${label}`;
            return;
        }

        button.disabled = false;
        button.innerHTML = button.dataset.originalLabel || label;
    }

    async function createCashTransaction(packageId) {
        const response = await fetch('../api/create-transaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ paket_id: packageId, payment_method: 'cash' }),
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Gagal membuat order cash.');
        }
        return data.data;
    }

    function promptForImage() {
        return new Promise((resolve, reject) => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.capture = 'environment';
            input.onchange = () => {
                if (input.files && input.files.length > 0) {
                    resolve(input.files[0]);
                } else {
                    reject(new Error('Foto uang dibatalkan.'));
                }
            };
            input.click();
        });
    }

    async function detectCashOrder(orderId, imageFile) {
        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('image', imageFile);

        const response = await fetch('api/detect-cash.php', {
            method: 'POST',
            body: formData,
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Deteksi uang gagal.');
        }
        }
        return data.data;
    }

    // --- Custom Modal Implementations ---
    window.customAlert = function(title, message) {
        return new Promise((resolve) => {
            const modal = document.getElementById('global-alert-modal');
            document.getElementById('global-alert-title').textContent = title;
            document.getElementById('global-alert-message').textContent = message;
            
            const btnOk = document.getElementById('global-alert-ok');
            const handler = () => {
                modal.classList.remove('active');
                btnOk.removeEventListener('click', handler);
                resolve();
            };
            btnOk.addEventListener('click', handler);
            modal.classList.add('active');
            btnOk.focus();
        });
    };

    window.customPrompt = function(title, message, placeholder = '') {
        return new Promise((resolve) => {
            const modal = document.getElementById('global-prompt-modal');
            document.getElementById('global-prompt-title').textContent = title;
            document.getElementById('global-prompt-message').textContent = message;
            
            const input = document.getElementById('global-prompt-input');
            input.value = '';
            input.placeholder = placeholder;
            
            const btnOk = document.getElementById('global-prompt-ok');
            const btnCancel = document.getElementById('global-prompt-cancel');
            
            const cleanup = () => {
                modal.classList.remove('active');
                btnOk.removeEventListener('click', okHandler);
                btnCancel.removeEventListener('click', cancelHandler);
            };
            
            const okHandler = () => { cleanup(); resolve(input.value); };
            const cancelHandler = () => { cleanup(); resolve(null); };
            
            btnOk.addEventListener('click', okHandler);
            btnCancel.addEventListener('click', cancelHandler);
            
            // Allow Enter key to submit
            input.onkeyup = (e) => { if (e.key === 'Enter') okHandler(); };
            
            modal.classList.add('active');
            input.focus();
        });
    };

    // Make window prompt safe globally
    window.alert = function(msg) { window.customAlert('Pemberitahuan', msg); };

    async function approveCashOrder(orderId, cashReceived = 0) {
        const response = await fetch('api/approve-cash.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, cash_received: cashReceived }),
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Approval cash gagal.');
        }
        return data.data;
    }

    function appendQueueItem(orderData) {
        const html = `
            <article
                class="queue-item"
                data-order-id="${escapeHtml(orderData.order_id)}"
                data-amount="${escapeHtml(String(orderData.amount))}"
                data-package-name="${escapeHtml(orderData.paket_nama)}"
                data-duration="-"
                data-verdict="unknown"
            >
                <div class="queue-item__top">
                    <div>
                        <strong>${escapeHtml(orderData.paket_nama)}</strong>
                        <div class="queue-item__meta">
                            <span class="mono">${escapeHtml(orderData.order_id)}</span>
                            <span>${escapeHtml(new Date().toLocaleString('id-ID'))}</span>
                            <span>${escapeHtml(orderData.amount_display)}</span>
                        </div>
                    </div>
                    <span class="status-badge status-badge--pending detection-badge">Belum discan</span>
                </div>
                <div class="queue-item__meta detection-meta">
                    <span>Belum ada hasil deteksi untuk order ini.</span>
                </div>
                <div class="queue-actions">
                    <button type="button" class="btn btn-secondary btn-sm detect-cash-btn" data-order-id="${escapeHtml(orderData.order_id)}">Scan Uang</button>
                    <button type="button" class="btn btn-primary btn-sm approve-queue-btn" data-order-id="${escapeHtml(orderData.order_id)}" disabled>Serahkan ke Admin & Lunasi</button>
                    <a class="btn btn-secondary btn-sm" href="../checkout-cash.php?order_id=${encodeURIComponent(orderData.order_id)}" target="_blank">Buka Order</a>
                </div>
            </article>
        `;

        const empty = queueList.querySelector('.empty-state');
        if (empty) {
            empty.remove();
        }

        queueList.insertAdjacentHTML('beforeend', html);
        attachQueueActions(queueList.lastElementChild);
        recalcCounters();
    }

    function attachQueueActions(queueItem) {
        if (!queueItem) {
            return;
        }

        const detectButton = queueItem.querySelector('.detect-cash-btn');
        const approveButton = queueItem.querySelector('.approve-queue-btn');
        const orderId = queueItem.dataset.orderId;

        if (detectButton) {
            detectButton.addEventListener('click', async () => {
                let userCash = queueItem.dataset.cashReceived;
                if (!userCash) {
                    const inputStr = await window.customPrompt('Nominal Uang Tunai', 'Masukkan nominal uang yang diserahkan pelanggan:', 'Misal: 100000');
                    if (!inputStr) return;
                    const parsed = parseInt(inputStr.replace(/[^0-9]/g, ''), 10);
                    if (isNaN(parsed) || parsed <= 0) {
                        await window.customAlert('Error', 'Nominal uang tidak valid!');
                        return;
                    }
                    userCash = parsed;
                    queueItem.dataset.cashReceived = userCash;
                }

                let imageFile;
                try {
                    imageFile = await promptForImage();
                } catch (e) {
                    return;
                }

                setLoading(detectButton, true, 'Scanning');
                writeTerminal('Memulai deteksi uang...', [
                    `Order ID ${orderId}`,
                    `Nominal Pembeli: Rp ${Number(userCash).toLocaleString('id-ID')}`,
                    'Mengirim request ke AI.',
                ]);

                try {
                    const detectionData = await detectCashOrder(orderId, imageFile);
                    setQueueDetection(queueItem, detectionData.detection);

                    const verdict = detectionData.detection.verdict;
                    let statusLabelText = verdict === 'genuine' ? 'ASLI' : (verdict === 'counterfeit' ? 'PALSU' : 'TIDAK DIKENALI');

                    if (verdict === 'genuine') {
                        writeTerminal('Deteksi selesai: ASLI.', [
                            'Order siap diserahkan ke admin untuk approval.',
                        ]);
                        await window.customAlert('Pemberitahuan', `Hasil Scan Uang: ASLI\nUang dari pembeli tercatat: Rp ${Number(queueItem.dataset.cashReceived).toLocaleString('id-ID')}\n\nOrder siap dilunasi.`);
                    } else if (verdict === 'counterfeit') {
                        writeTerminal('Deteksi selesai: PALSU.', [
                            'Order diblokir. Tolak transaksi atau lakukan scan ulang.',
                        ]);
                        await window.customAlert('Pemberitahuan', `PERINGATAN! Hasil Scan Uang: PALSU\n\nSistem mengunci transaksi ini. Jangan setujui pesanan.`);
                    } else {
                        writeTerminal('Deteksi belum valid.', [
                            `Verdict: ${statusLabelText}`,
                            'Silakan lakukan foto ulang sebelum approval.',
                        ]);
                        await window.customAlert('Pemberitahuan', `Hasil Scan Uang: ${statusLabelText}\nFokus atau pencahayaan kurang, silakan foto uang kembali.`);
                    }

                    showResult(
                        'Hasil deteksi uang',
                        `${detectionData.next_action}`,
                        `${statusLabelText}`
                    );
                } catch (error) {
                    writeTerminal('Deteksi gagal.', [error.message, 'Silakan cek koneksi detector atau coba lagi.']);
                    await window.customAlert('Pemberitahuan', error.message);
                } finally {
                    setLoading(detectButton, false, 'Scanning');
                }
            });
        }

        if (approveButton) {
            approveButton.addEventListener('click', async () => {
                if (!confirm(`Lanjutkan pencetakan struk dan voucher untuk ${orderId}?`)) {
                    return;
                }

                setLoading(approveButton, true, 'Approving');
                writeTerminal('Melunasi order...', [
                    `Order ID ${orderId}`,
                ]);

                try {
                    const cashRcv = parseInt(queueItem.dataset.cashReceived, 10) || 0;
                    const approved = await approveCashOrder(orderId, cashRcv);
                    const detectionVerdict = approved.detection && approved.detection.verdict
                        ? approved.detection.verdict
                        : 'genuine';
                    writeTerminal('Approval berhasil.', [
                        `Voucher ${approved.voucher_username}`,
                        `Detection ${detectionVerdict}`,
                        'Order dipindahkan ke riwayat settlement.',
                    ]);

                    const links = `
                        <a class="btn btn-primary btn-sm" href="${escapeHtml(approved.success_url)}" target="_blank">Buka Voucher</a>
                        <a class="btn btn-secondary btn-sm" href="${escapeHtml(approved.invoice_url)}" target="_blank">Cetak Invoice</a>
                    `;
                    showResult('Transaksi Lunas', 'Voucher berhasil diterbitkan setelah verifikasi uang.', approved.voucher_username, links);

                    queueItem.remove();
                    prependToHistory(approved);
                    recalcCounters();

                    if (!queueList.querySelector('.queue-item')) {
                        queueList.innerHTML = `
                            <div class="empty-state">
                                <strong>Tidak ada order pending.</strong>
                                <span>Semua order cash sudah diproses.</span>
                            </div>
                        `;
                    }
                    await window.customAlert('Pemberitahuan', `Transaksi berhasil dan valid!\nVoucher: ${approved.voucher_username}`);
                } catch (error) {
                    writeTerminal('Approval ditolak.', [error.message, 'Biasanya karena hasil deteksi belum genuine atau sudah expired.']);
                    await window.customAlert('Pemberitahuan', error.message);
                } finally {
                    setLoading(approveButton, false, 'Approving');
                }
            });
        }
    }

    document.querySelectorAll('.queue-item').forEach((item) => attachQueueActions(item));

    document.querySelectorAll('.create-cash-btn').forEach((button) => {
        button.addEventListener('click', async () => {
            setLoading(button, true, 'Creating');
            writeTerminal('Membuat order cash baru...', [
                `Paket ${button.dataset.name}`,
                `Nominal ${button.dataset.priceDisplay}`,
                'Order akan masuk ke antrean dan menunggu deteksi.',
            ]);

            try {
                const orderData = await createCashTransaction(button.dataset.id);
                appendQueueItem(orderData);
                writeTerminal('Order cash berhasil dibuat.', [
                    `Order ID ${orderData.order_id}`,
                    'Langkah berikutnya: klik tombol Scan Uang pada antrean.',
                ]);
                showResult('Order cash dibuat', 'Order menunggu scan uang sebelum bisa diapprove.', orderData.order_id);
            } catch (error) {
                writeTerminal('Gagal membuat order cash.', [error.message]);
                await window.customAlert('Gagal', error.message);
            } finally {
                setLoading(button, false, 'Creating');
            }
        });
    });

    document.querySelectorAll('.quick-pipeline-btn').forEach((button) => {
        button.addEventListener('click', async () => {
            const inputStr = await window.customPrompt('Nominal Uang Tunai', 'Masukkan nominal uang yang diserahkan pelanggan:', 'Misal: 100000');
            if (!inputStr) return;
            const userCash = parseInt(inputStr.replace(/[^0-9]/g, ''), 10);
            if (isNaN(userCash) || userCash <= 0) {
                await window.customAlert('Error', 'Nominal uang tidak valid!');
                return;
            }

            let imageFile;
            try {
                imageFile = await promptForImage();
            } catch (e) {
                return;
            }

            setLoading(button, true, 'Running');
            writeTerminal('Menjalankan mode otomatis...', [
                `Paket ${button.dataset.name}`,
                `Titipan pelanggan: Rp ${Number(userCash).toLocaleString('id-ID')}`,
                'Step: order -> validasi foto -> cetak otomatis',
            ]);

            try {
                const orderData = await createCashTransaction(button.dataset.id);
                // Langsung simpan data cash received agar bisa diambil di approveCashOrder nanti
                orderData.cashReceived = userCash;
                appendQueueItem(orderData);

                const queueItem = queueList.querySelector(`.queue-item[data-order-id="${orderData.order_id}"]`);
                if (queueItem) {
                    queueItem.dataset.cashReceived = userCash;
                }
                const detectData = await detectCashOrder(orderData.order_id, imageFile);

                if (queueItem) {
                    setQueueDetection(queueItem, detectData.detection);
                }

                if (detectData.detection.verdict !== 'genuine') {
                    const statusLabel = detectData.detection.verdict === 'counterfeit' ? 'PALSU' : 'TIDAK DIKENALI';
                    writeTerminal('Mode cepat terhenti.', [
                        `Hasil: ${statusLabel}`,
                        'Sistem tidak bisa langsung cetak tiket. Tinjau di antrean.',
                    ]);
                    showResult(
                        'Transaksi ditunda',
                        'Uang mencurigakan, pesanan tertahan di antrean.',
                        `${statusLabel}`
                    );
                    await window.customAlert('Pemberitahuan', `Proses Otomatis Terhenti: Uang terdeteksi ${statusLabel}. Silakan foto kembali secara manual dari tabel antrean.`);
                    setLoading(button, false, 'Running');
                    return;
                }

                await window.customAlert('Pemberitahuan', `Scan uang berhasil (ASLI). Lanjut mencetak voucher otomatis...`);

                const approved = await approveCashOrder(orderData.order_id, userCash);
                if (queueItem) {
                    queueItem.remove();
                }
                prependToHistory(approved);
                recalcCounters();

                writeTerminal('Mode otomatis Selesai.', [
                    `Order ${approved.order_id} lunas`,
                    `Voucher ${approved.voucher_username}`,
                ]);

                const links = `
                    <a class="btn btn-primary btn-sm" href="${escapeHtml(approved.success_url)}" target="_blank">Buka Voucher</a>
                    <a class="btn btn-secondary btn-sm" href="${escapeHtml(approved.invoice_url)}" target="_blank">Cetak Invoice</a>
                `;
                showResult('Lunas Otomatis', 'Voucher dan Invoice berhasil dibuat.', approved.voucher_username, links);
                await window.customAlert('Transaksi Selesai!', `Voucher: ${approved.voucher_username}\nJangan lupa berikan kembalian di struk.`);
            } catch (error) {
                writeTerminal('Mode cepat gagal.', [error.message, 'Silakan proses manual dari antrean.']);
                await window.customAlert('Gagal', error.message);
            } finally {
                setLoading(button, false, 'Running');
            }
        });
    });

    recalcCounters();
    </script>
    
    <!-- Global Alert Modal -->
    <div class="custom-prompt-modal" id="global-alert-modal">
        <div class="custom-prompt-content">
            <div class="custom-prompt-title" id="global-alert-title">Pemberitahuan</div>
            <div class="custom-prompt-desc" id="global-alert-message">...</div>
            <div class="custom-prompt-actions">
                <button class="btn btn-primary" id="global-alert-ok">Mengerti</button>
            </div>
        </div>
    </div>

    <!-- Global Prompt Modal -->
    <div class="custom-prompt-modal" id="global-prompt-modal">
        <div class="custom-prompt-content">
            <div class="custom-prompt-title" id="global-prompt-title">Input Dibutuhkan</div>
            <div class="custom-prompt-desc" id="global-prompt-message">...</div>
            <input type="text" class="custom-prompt-input" id="global-prompt-input" autocomplete="off">
            <div class="custom-prompt-actions">
                <button class="btn btn-ghost" id="global-prompt-cancel">Batal</button>
                <button class="btn btn-primary" id="global-prompt-ok">Simpan</button>
            </div>
        </div>
    </div>
</body>
</html>
