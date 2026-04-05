<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/CashDetectorService.php';

$pdo = getDB();
$today = date('Y-m-d');
$detector = new CashDetectorService($pdo);

$stmtPending = $pdo->query("
    SELECT t.order_id, t.amount, t.created_at, p.nama_paket, p.durasi_display
    FROM transaksi t
    JOIN paket_voucher p ON p.id = t.paket_id
    WHERE t.payment_type = 'cash' AND t.status = 'pending'
    ORDER BY t.created_at ASC
");
$pendingOrders = $stmtPending->fetchAll();
$pendingCount = count($pendingOrders);
$pendingAmount = array_sum(array_map(static fn($row) => (int) $row['amount'], $pendingOrders));

$latestDetections = $detector->getLatestDetectionsByOrderIds(array_column($pendingOrders, 'order_id'));

$stmtHistory = $pdo->prepare("
    SELECT t.*, p.nama_paket, p.durasi_display
    FROM transaksi t
    JOIN paket_voucher p ON t.paket_id = p.id
    WHERE t.payment_type = 'cash' AND t.status = 'settlement' AND DATE(t.paid_at) = ?
    ORDER BY t.paid_at DESC
    LIMIT 10
");
$stmtHistory->execute([$today]);
$paidOrders = $stmtHistory->fetchAll();

$stmtToday = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total_cash_today, COUNT(*) AS total_cash_sales
    FROM transaksi
    WHERE payment_type = 'cash' AND status = 'settlement' AND DATE(paid_at) = ?
");
$stmtToday->execute([$today]);
$cashToday = $stmtToday->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrian Tunai — RipaNet Admin</title>
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
                    <span>Antrian Tunai</span>
                </span>
            </a>
            <nav class="admin-topbar__links">
                <a href="index.php">Dashboard</a>
                <a href="pos.php">Penjualan</a>
                <a href="cash-orders.php" class="active">Antrian</a>
                <a href="products.php">Produk</a>
                <a href="logout.php" class="danger">Keluar</a>
            </nav>
        </header>

        <section class="admin-kpis">
            <article class="kpi-card">
                <div class="kpi-card__label">Antrian Menunggu</div>
                <div class="kpi-card__value" id="pending-kpi"><?= number_format($pendingCount) ?></div>
                <div class="kpi-card__hint">Nominal Rp <?= number_format($pendingAmount, 0, ',', '.') ?></div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Tunai Lunas Hari Ini</div>
                <div class="kpi-card__value">Rp <?= number_format((int) $cashToday['total_cash_today'], 0, ',', '.') ?></div>
                <div class="kpi-card__hint"><?= number_format((int) $cashToday['total_cash_sales']) ?> transaksi</div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Mode Verifikasi</div>
                <div class="kpi-card__value" style="font-size: 1.2rem;"><?= env('CASH_DETECTOR_URL') ? 'Aktif' : 'Demo' ?></div>
                <div class="kpi-card__hint">Sistem deteksi uang</div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Kasir Aktif</div>
                <div class="kpi-card__value" style="font-size: 1.2rem;"><?= htmlspecialchars($adminUser) ?></div>
                <div class="kpi-card__hint">Verifikasi sebelum konfirmasi</div>
            </article>
        </section>

        <section class="dashboard-grid">
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Antrian Pesanan Tunai</h2>
                        <p>Setiap pesanan wajib diverifikasi terlebih dahulu. Konfirmasi aktif setelah lolos verifikasi.</p>
                    </div>
                    <a class="btn btn-secondary btn-sm" href="cash-orders.php">Muat Ulang</a>
                </div>

                <div id="queue-list" class="queue-list">
                    <?php if (empty($pendingOrders)): ?>
                        <div class="empty-state">
                            <strong>Tidak ada antrean cash.</strong>
                            <span>Order tunai baru akan muncul otomatis di sini.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingOrders as $order): ?>
                            <?php
                            $detection = $latestDetections[$order['order_id']] ?? null;
                            $verdict = $detection['verdict'] ?? 'unknown';
                            $badgeClass = 'status-badge status-badge--pending';
                            $badgeLabel = 'Belum discan';
                            if ($verdict === 'genuine') {
                                $badgeClass = 'status-badge status-badge--success';
                                $badgeLabel = 'Asli';
                            } elseif ($verdict === 'counterfeit') {
                                $badgeClass = 'status-badge status-badge--danger';
                                $badgeLabel = 'Palsu';
                            } elseif ($verdict === 'uncertain') {
                                $badgeLabel = 'Belum Pasti';
                            }
                            ?>
                            <article class="queue-item" data-order-id="<?= htmlspecialchars($order['order_id']) ?>" data-verdict="<?= htmlspecialchars($verdict, ENT_QUOTES, 'UTF-8') ?>">
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
                                    <button type="button" class="btn btn-secondary btn-sm detect-cash-btn" data-order-id="<?= htmlspecialchars($order['order_id']) ?>">Verifikasi Uang</button>
                                    <button type="button" class="btn btn-primary btn-sm approve-btn" data-order-id="<?= htmlspecialchars($order['order_id']) ?>" <?= $verdict === 'genuine' ? '' : 'disabled' ?>>Konfirmasi & Lunasi</button>
                                    <a class="btn btn-secondary btn-sm" href="../checkout-cash.php?order_id=<?= urlencode($order['order_id']) ?>" target="_blank">Lihat</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h3>Konsol Verifikasi</h3>
                        <p>Status proses pesanan yang sedang dikerjakan.</p>
                    </div>
                </div>
                <div class="terminal-output">
                    <div class="terminal-output__label">Log Verifikasi</div>
                    <div class="terminal-output__body" id="console-output">
                        <strong>Antrean siap diproses.</strong>
                        <span>Pilih order, lakukan scan uang, lalu lanjutkan approval jika genuine.</span>
                    </div>
                </div>
                <div class="result-card" id="console-result"></div>

                <div class="panel-head" style="margin-top: 20px;">
                    <div>
                        <h3>Riwayat Tunai Hari Ini</h3>
                        <p>Transaksi terakhir yang sudah dikonfirmasi.</p>
                    </div>
                </div>
                <?php if (empty($paidOrders)): ?>
                    <div class="empty-state">
                        <strong>Belum ada riwayat settlement cash hari ini.</strong>
                        <span>Data akan terisi setelah approval berhasil.</span>
                    </div>
                <?php else: ?>
                    <div class="history-list">
                        <?php foreach ($paidOrders as $order): ?>
                            <article class="history-item">
                                <div class="history-item__top">
                                    <div>
                                        <strong><?= htmlspecialchars($order['nama_paket']) ?></strong>
                                        <div class="history-item__meta">
                                            <span class="mono"><?= htmlspecialchars($order['order_id']) ?></span>
                                            <span><?= date('H:i', strtotime($order['paid_at'])) ?></span>
                                        </div>
                                    </div>
                                    <span class="status-badge status-badge--success">Lunas</span>
                                </div>
                                <div class="queue-item__meta">
                                    <span>Rp <?= number_format($order['amount'], 0, ',', '.') ?></span>
                                    <span>Voucher <?= htmlspecialchars($order['mikrotik_user']) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </div>

    <script>
    const queueList = document.getElementById('queue-list');
    const consoleOutput = document.getElementById('console-output');
    const consoleResult = document.getElementById('console-result');
    const pendingKpi = document.getElementById('pending-kpi');

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setPendingCount() {
        const count = queueList.querySelectorAll('.queue-item').length;
        pendingKpi.textContent = count.toLocaleString('id-ID');
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

    function printConsole(title, lines) {
        consoleOutput.innerHTML = `
            <strong>${escapeHtml(title)}</strong>
            ${lines.map((line) => `<span>${escapeHtml(line)}</span>`).join('')}
        `;
    }

    function setResult(title, subtitle, code, linksHtml = '') {
        consoleResult.innerHTML = `
            <strong>${escapeHtml(title)}</strong>
            <span>${escapeHtml(subtitle)}</span>
            ${code ? `<code>${escapeHtml(code)}</code>` : ''}
            <div class="actions-row">${linksHtml}</div>
        `;
        consoleResult.classList.add('active');
    }

    function prependToHistory(approved) {
        let historyList = document.querySelector('.history-list');
        if (!historyList) {
            const panels = document.querySelectorAll('.dashboard-grid .panel');
            if (panels.length >= 2) {
                const emptyState = panels[1].querySelector('.empty-state');
                if (emptyState) {
                    const listHtml = '<div class="history-list"></div>';
                    emptyState.parentElement.insertAdjacentHTML('beforeend', listHtml);
                    emptyState.remove();
                    historyList = panels[1].querySelector('.history-list');
                }
            }
        }
        if (!historyList) return;

        const nowTime = new Date().toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'});
        const html = `
            <article class="history-item">
                <div class="history-item__top">
                    <div>
                        <strong>${escapeHtml(approved.paket_nama)}</strong>
                        <div class="history-item__meta">
                            <span class="mono">${escapeHtml(approved.order_id)}</span>
                            <span>${nowTime}</span>
                        </div>
                    </div>
                    <span class="status-badge status-badge--success">Lunas</span>
                </div>
                <div class="queue-item__meta">
                    <span>Rp ${Number(approved.amount).toLocaleString('id-ID')}</span>
                    <span>Voucher ${escapeHtml(approved.voucher_username)}</span>
                </div>
            </article>
        `;
        historyList.insertAdjacentHTML('afterbegin', html);
    }

    function verdictBadge(verdict) {
        if (verdict === 'genuine') {
            return { cls: 'status-badge status-badge--success detection-badge', text: 'ASLI' };
        }
        if (verdict === 'counterfeit') {
            return { cls: 'status-badge status-badge--danger detection-badge', text: 'PALSU' };
        }
        if (verdict === 'uncertain') {
            return { cls: 'status-badge status-badge--pending detection-badge', text: 'TIDAK DIKENALI' };
        }
        return { cls: 'status-badge status-badge--pending detection-badge', text: 'Belum discan' };
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

    async function detectCash(orderId, imageFile) {
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
        return data.data;
    }

    async function approveCash(orderId, cashReceived = 0) {
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

    function wireQueueItem(item) {
        const detectBtn = item.querySelector('.detect-cash-btn');
        const approveBtn = item.querySelector('.approve-btn');
        const badge = item.querySelector('.detection-badge');
        const meta = item.querySelector('.detection-meta');
        const orderId = item.dataset.orderId;

        detectBtn.addEventListener('click', async () => {
            let userCash = item.dataset.cashReceived;
            if (!userCash) {
                const inputStr = await window.customPrompt('Nominal Uang Tunai', 'Masukkan nominal uang yang diserahkan pelanggan:', 'Misal: 100000');
                if (!inputStr) return;
                const parsed = parseInt(inputStr.replace(/[^0-9]/g, ''), 10);
                if (isNaN(parsed) || parsed <= 0) {
                    await window.customAlert('Pemberitahuan', 'Nominal uang tidak valid!');
                    return;
                }
                userCash = parsed;
                item.dataset.cashReceived = userCash;
            }

            let imageFile;
            try {
                imageFile = await promptForImage();
            } catch (e) {
                return;
            }

            setLoading(detectBtn, true, 'Scanning');
            printConsole('Deteksi uang dimulai...', [
                `Order ${orderId}`, 
                `Nominal Pembeli: Rp ${Number(userCash).toLocaleString('id-ID')}`,
                'Menghubungi AI.',
            ]);

            try {
                const data = await detectCash(orderId, imageFile);
                item.dataset.verdict = data.detection.verdict;

                const mapped = verdictBadge(data.detection.verdict);
                badge.className = mapped.cls;
                badge.textContent = mapped.text;
                meta.innerHTML = `
                    <span>Scan: ${escapeHtml(new Date(data.detection.created_at).toLocaleTimeString('id-ID'))}</span>
                    <span>Mode: ${escapeHtml(data.detection.mode)}</span>
                `;

                approveBtn.disabled = data.detection.verdict !== 'genuine';
                const statusLabel = data.detection.verdict === 'genuine' ? 'ASLI' : (data.detection.verdict === 'counterfeit' ? 'PALSU' : 'TIDAK DIKENALI');

                printConsole('Deteksi selesai.', [
                    `Hasil: ${statusLabel}`,
                    data.next_action,
                ]);

                setResult('Hasil Deteksi', data.next_action, `${statusLabel}`);
                
                if (data.detection.verdict === 'genuine') {
                    await window.customAlert('Pemberitahuan', `Hasil Scan: ASLI\nUang pembeli dicatat: Rp ${Number(item.dataset.cashReceived).toLocaleString('id-ID')}\n\nOrder bisa dilanjutkan untuk pelunasan.`);
                } else if (data.detection.verdict === 'counterfeit') {
                    await window.customAlert('Pemberitahuan', `PERINGATAN! Hasil Scan: PALSU\n\nPeringatkan pelanggan dan JANGAN terima transaksi.`);
                } else {
                    await window.customAlert('Pemberitahuan', `Hasil Scan: ${statusLabel}\nSistem kesulitan mengecek. Silakan foto ulang agak dekat.`);
                }
            } catch (error) {
                printConsole('Deteksi gagal.', [error.message]);
                await window.customAlert('Pemberitahuan', error.message);
            } finally {
                setLoading(detectBtn, false, 'Scanning');
            }
        });

        approveBtn.addEventListener('click', async () => {
            if (!confirm(`Tandai LUNAS order ${orderId} dan terbitkan tiket?`)) {
                return;
            }

            setLoading(approveBtn, true, 'Melunasi');
            printConsole('Meneruskan ke proses pelunasan...', [`Order ${orderId}`, 'Memvalidasi deteksi...']);

            try {
                const cashRcv = parseInt(item.dataset.cashReceived, 10) || 0;
                const data = await approveCash(orderId, cashRcv);
                const links = `
                    <a class="btn btn-primary btn-sm" href="${escapeHtml(data.success_url)}" target="_blank">Buka Voucher</a>
                    <a class="btn btn-secondary btn-sm" href="${escapeHtml(data.invoice_url)}" target="_blank">Cetak Invoice</a>
                `;
                setResult('Approval Berhasil', 'Voucher diterbitkan setelah lolos deteksi.', data.voucher_username, links);
                printConsole('Pelunasan selesai.', [`Voucher ${data.voucher_username}`, 'Order dipindahkan ke riwayat settlement.']);

                item.remove();
                prependToHistory(data);
                setPendingCount();

                if (!queueList.querySelector('.queue-item')) {
                    queueList.innerHTML = `
                        <div class="empty-state">
                            <strong>Tidak ada antrean cash.</strong>
                            <span>Semua order pending sudah selesai.</span>
                        </div>
                    `;
                }
                
                await window.customAlert('Pemberitahuan', `Transaksi Berhasil Lunas!\nVoucher: ${data.voucher_username}`);
            } catch (error) {
                printConsole('Approval ditolak.', [error.message]);
                await window.customAlert('Pemberitahuan', error.message);
            } finally {
                setLoading(approveBtn, false, 'Approving');
            }
        });
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
            
            input.onkeyup = (e) => { if (e.key === 'Enter') okHandler(); };
            
            modal.classList.add('active');
            input.focus();
        });
    };

    // Make window prompt safe globally
    window.alert = function(msg) { window.customAlert('Pemberitahuan', msg); };

    document.querySelectorAll('.queue-item').forEach((item) => wireQueueItem(item));
    setPendingCount();
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
