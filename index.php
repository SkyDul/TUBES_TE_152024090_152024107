<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/MidtransService.php';

$midtrans = new MidtransService();
$clientKey = $midtrans->getClientKey();
$isProduction = filter_var(getenv('MIDTRANS_IS_PRODUCTION'), FILTER_VALIDATE_BOOLEAN);
$snapJsUrl = $isProduction ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';

$pdo = getDB();
$packages = $pdo->query("
    SELECT id, nama_paket, harga, durasi_display, durasi_hari
    FROM paket_voucher
    WHERE is_active = 1
    ORDER BY harga ASC
")->fetchAll();

$packageCount = count($packages);
$cheapestPrice = $packageCount ? min(array_column($packages, 'harga')) : 0;
$featuredPackageId = null;
$bestRatio = null;

foreach ($packages as $package) {
    $days = max((int) $package['durasi_hari'], 1);
    $ratio = $package['harga'] / $days;
    if ($bestRatio === null || $ratio < $bestRatio) {
        $bestRatio = $ratio;
        $featuredPackageId = (int) $package['id'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RipaNet — Voucher WiFi Instan</title>
    <meta name="description" content="Beli voucher WiFi RipaNet secara online via QRIS atau bayar tunai di kasir. Aktif otomatis, tanpa ribet.">
    <link rel="icon" type="image/png" href="assets/img/logo-RipaNet.png">
    <link rel="stylesheet" href="assets/css/style.css?v=6">
    <script src="<?= htmlspecialchars($snapJsUrl) ?>" data-client-key="<?= htmlspecialchars($clientKey) ?>"></script>
</head>
<body>
    <div class="page-shell">
        <header class="topbar">
            <div class="topbar__inner">
                <a class="brand" href="./">
                    <img class="brand__logo" src="assets/img/logo-RipaNet.png" alt="Logo RipaNet">
                    <span class="brand__meta">
                        <strong>RipaNet</strong>
                        <span>Internet Cepat & Terjangkau</span>
                    </span>
                </a>
                <button class="nav-toggle" id="nav-toggle" aria-label="Menu"><span class="icon"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></span></button>
                <div class="nav-actions" id="nav-actions">
                    <a class="nav-link" href="#paket">Paket</a>
                    <a class="nav-link" href="#cara-beli">Cara Beli</a>
                    <a class="btn btn-secondary btn-sm" href="admin/">Login Admin</a>
                </div>
            </div>
        </header>

        <main class="container">
            <section class="hero">
                <div class="hero__layout">
                    <div class="hero__copy">
                        <span class="eyebrow">Voucher WiFi RipaNet</span>
                        <h1 class="hero__title">Internet cepat, beli voucher dalam hitungan detik.</h1>
                        <p class="hero__subtitle">
                            Pilih paket, bayar online atau tunai, dan langsung terhubung ke internet.
                        </p>

                        <div class="hero__actions">
                            <a class="btn btn-primary" href="#paket">Pilih Paket</a>
                            <a class="btn btn-outline" href="#cara-beli">Cara Beli</a>
                        </div>

                        <div class="hero__bullets">
                            <span class="hero__bullet">QRIS & E-Wallet</span>
                            <span class="hero__bullet">Bayar Tunai</span>
                            <span class="hero__bullet">Aktif Otomatis</span>
                        </div>
                    </div>

                    <aside class="hero__panel">
                        <div class="hero-stats">
                            <div class="stat-card">
                                <div class="stat-card__label">Paket Tersedia</div>
                                <div class="stat-card__value"><?= number_format($packageCount) ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card__label">Mulai Dari</div>
                                <div class="stat-card__value">Rp <?= number_format($cheapestPrice, 0, ',', '.') ?></div>
                            </div>
                        </div>

                        <div class="hero-board">
                            <div class="hero-board__row">
                                <span>Pembayaran</span>
                                <strong>QRIS / Tunai</strong>
                            </div>
                            <div class="hero-board__row">
                                <span>Aktivasi</span>
                                <strong>Otomatis</strong>
                            </div>
                            <div class="hero-board__row">
                                <span>Struk</span>
                                <strong>Cetak / Digital</strong>
                            </div>
                        </div>

                        <div class="hero-flow">
                            <div class="flow-card">
                                <span class="flow-card__number">1</span>
                                <div>
                                    <h3>Pilih Paket</h3>
                                    <p>Tentukan paket internet sesuai kebutuhan Anda.</p>
                                </div>
                            </div>
                            <div class="flow-card">
                                <span class="flow-card__number">2</span>
                                <div>
                                    <h3>Bayar</h3>
                                    <p>Bayar via QRIS, e-wallet, atau tunai di kasir.</p>
                                </div>
                            </div>
                            <div class="flow-card">
                                <span class="flow-card__number">3</span>
                                <div>
                                    <h3>Terhubung</h3>
                                    <p>Voucher langsung aktif, tinggal masukkan kode dan online.</p>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </section>

            <section class="section">
                <div class="cards-grid">
                    <article class="info-card">
                        <h3>Internet Cepat</h3>
                        <p>Akses internet stabil dan cepat untuk streaming, kerja, atau belajar online.</p>
                    </article>
                    <article class="info-card info-card--accent">
                        <h3>Pembayaran Mudah</h3>
                        <p>Bayar dengan QRIS, e-wallet, transfer bank, atau langsung tunai di kasir.</p>
                    </article>
                    <article class="info-card">
                        <h3>Aktivasi Instan</h3>
                        <p>Setelah pembayaran berhasil, voucher langsung aktif dan siap digunakan.</p>
                    </article>
                </div>
            </section>

            <section class="section" id="paket">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">Daftar Paket</span>
                        <h2>Pilih paket internet yang sesuai kebutuhan Anda.</h2>
                    </div>
                    <p>Semua paket bisa dibayar secara online maupun tunai. Paket terbaik ditandai khusus.</p>
                </div>

                <div class="packages-wrap">
                    <div class="packages-grid">
                        <?php if (empty($packages)): ?>
                            <div class="empty-state">
                                <strong>Belum ada paket tersedia.</strong>
                                <span>Hubungi admin untuk menambahkan paket voucher.</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($packages as $package): ?>
                                <?php
                                $isFeatured = (int) $package['id'] === $featuredPackageId;
                                $priceDisplay = 'Rp ' . number_format((int) $package['harga'], 0, ',', '.');
                                $perDay = (int) ceil($package['harga'] / max((int) $package['durasi_hari'], 1));
                                ?>
                                <article class="package-card<?= $isFeatured ? ' package-card--featured' : '' ?>">
                                    <div class="package-card__top">
                                        <div>
                                            <span class="package-badge"><?= $isFeatured ? 'Terbaik' : 'Voucher WiFi' ?></span>
                                            <h3 class="package-card__name"><?= htmlspecialchars($package['nama_paket']) ?></h3>
                                            <p class="package-card__duration"><?= htmlspecialchars($package['durasi_display']) ?></p>
                                        </div>
                                        <span class="muted"><?= number_format($perDay, 0, ',', '.') ?>/hari</span>
                                    </div>

                                    <div class="package-card__price"><?= $priceDisplay ?></div>

                                    <ul class="package-feature-list">
                                        <li>Voucher aktif otomatis setelah pembayaran.</li>
                                        <li>Bisa dibayar via QRIS, e-wallet, atau tunai.</li>
                                        <li>Struk dan invoice tersedia setelah transaksi.</li>
                                    </ul>

                                    <div class="package-helper">
                                        <span><?= htmlspecialchars($package['durasi_display']) ?></span>
                                        <span>Online & Tunai</span>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn <?= $isFeatured ? 'btn-accent' : 'btn-primary' ?> buy-package"
                                        data-id="<?= (int) $package['id'] ?>"
                                        data-name="<?= htmlspecialchars($package['nama_paket'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-price="<?= (int) $package['harga'] ?>"
                                        data-price-display="<?= htmlspecialchars($priceDisplay, ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        Beli Sekarang
                                    </button>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="section" id="cara-beli">
                <div class="showcase-grid">
                    <article class="showcase-card showcase-card--dark">
                        <span class="eyebrow" style="background:rgba(255,255,255,0.1);color:#fde68a;border-color:rgba(255,255,255,0.15);">Cara Beli</span>
                        <h2 style="margin-top:14px;">Beli voucher dalam 3 langkah mudah.</h2>
                        <ul>
                            <li>Pilih paket internet yang sesuai kebutuhan Anda.</li>
                            <li>Bayar secara online atau tunai di kasir terdekat.</li>
                            <li>Voucher dan struk tersedia otomatis setelah pembayaran berhasil.</li>
                        </ul>
                    </article>

                    <article class="showcase-card">
                        <span class="eyebrow">Kenapa RipaNet?</span>
                        <h2 style="margin-top:14px;">Mudah, cepat, dan bisa diandalkan.</h2>
                        <ul>
                            <li>Tampilan sederhana, langsung paham cara beli.</li>
                            <li>Harga transparan, tidak ada biaya tersembunyi.</li>
                            <li>Dukungan pembayaran lengkap: QRIS, e-wallet, transfer, dan tunai.</li>
                        </ul>
                    </article>
                </div>
            </section>

            <section class="section">
                <div class="support-grid">
                    <article class="support-card">
                        <strong>Pembayaran Online</strong>
                        <p>Bayar langsung dari HP menggunakan QRIS, GoPay, OVO, atau transfer bank.</p>
                    </article>
                    <article class="support-card">
                        <strong>Pembayaran Tunai</strong>
                        <p>Datang ke kasir, sebutkan paket yang diinginkan, lalu bayar tunai.</p>
                    </article>
                    <article class="support-card">
                        <strong>Voucher & Struk</strong>
                        <p>Setelah pembayaran, voucher langsung aktif dan struk bisa dicetak.</p>
                    </article>
                </div>
            </section>

            <section class="section">
                <div class="cta-band">
                    <div>
                        <h2>Siap terhubung ke internet?</h2>
                        <p>Pilih paket, bayar, dan langsung online. Semudah itu.</p>
                        <div class="cta-band__actions">
                            <a class="btn btn-accent" href="#paket">Lihat Paket</a>
                            <a class="btn btn-secondary" href="admin/">Login Admin</a>
                        </div>
                    </div>
                    <div class="cta-band__stats">
                        <div class="cta-mini">
                            <strong><?= number_format($packageCount) ?> Paket</strong>
                            <span>Pilihan sesuai kebutuhan Anda.</span>
                        </div>
                        <div class="cta-mini">
                            <strong>2 Metode Bayar</strong>
                            <span>Online dan tunai, sesuai kenyamanan.</span>
                        </div>
                        <div class="cta-mini">
                            <strong>Aktif Instan</strong>
                            <span>Voucher langsung aktif setelah bayar.</span>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="footer">
            <div class="container">
                <div class="footer__card">
                    <div>
                        <strong>RipaNet</strong>
                        <p>Voucher WiFi instan — bayar dan langsung online.</p>
                    </div>
                    <div class="footer__links">
                        <a href="#paket">Paket</a>
                        <a href="#cara-beli">Cara Beli</a>
                        <a href="admin/">Admin</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <div class="modal-overlay" id="payment-modal">
        <div class="modal-content">
            <button class="modal-close" type="button" id="payment-modal-close" aria-label="Tutup">&times;</button>
            <div class="modal-header">
                <h3 class="modal-title">Pilih Metode Pembayaran</h3>
                <p class="helper-text">
                    Paket <strong id="modal-package-name">-</strong> — <strong id="modal-package-price">-</strong>
                </p>
            </div>

            <div class="payment-options">
                <div class="payment-option">
                    <strong>Bayar Online</strong>
                    <p>QRIS, e-wallet, virtual account, atau metode online lainnya.</p>
                    <button type="button" class="btn btn-primary payment-action" data-method="online">Bayar Online</button>
                </div>

                <div class="payment-option">
                    <strong>Bayar Tunai</strong>
                    <p>Bayar tunai ke kasir, tunjukkan nomor pesanan Anda.</p>
                    <button type="button" class="btn btn-accent payment-action" data-method="cash">Bayar Tunai</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Mobile nav toggle
    const navToggle = document.getElementById('nav-toggle');
    const navActions = document.getElementById('nav-actions');
    if (navToggle && navActions) {
        navToggle.addEventListener('click', () => navActions.classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if (!navToggle.contains(e.target) && !navActions.contains(e.target)) {
                navActions.classList.remove('open');
            }
        });
    }

    const packageButtons = document.querySelectorAll('.buy-package');
    const paymentModal = document.getElementById('payment-modal');
    const paymentModalClose = document.getElementById('payment-modal-close');
    const modalPackageName = document.getElementById('modal-package-name');
    const modalPackagePrice = document.getElementById('modal-package-price');
    const paymentActions = document.querySelectorAll('.payment-action');

    let selectedPackage = null;
    let activeTriggerButton = null;

    packageButtons.forEach((button) => {
        button.addEventListener('click', () => {
            selectedPackage = {
                id: Number(button.dataset.id),
                name: button.dataset.name,
                price: Number(button.dataset.price),
                priceDisplay: button.dataset.priceDisplay
            };
            activeTriggerButton = button;
            modalPackageName.textContent = selectedPackage.name;
            modalPackagePrice.textContent = selectedPackage.priceDisplay;
            paymentModal.classList.add('active');
        });
    });

    function closePaymentModal() {
        paymentModal.classList.remove('active');
    }

    paymentModalClose.addEventListener('click', closePaymentModal);
    paymentModal.addEventListener('click', (event) => {
        if (event.target === paymentModal) closePaymentModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closePaymentModal();
    });

    paymentActions.forEach((button) => {
        button.addEventListener('click', () => processPayment(button.dataset.method, button));
    });

    async function processPayment(method, actionButton) {
        if (!selectedPackage) return;

        const originalActionLabel = actionButton.innerHTML;
        const originalTriggerLabel = activeTriggerButton ? activeTriggerButton.innerHTML : '';

        actionButton.disabled = true;
        actionButton.innerHTML = '<span class="spinner"></span> Memproses...';

        if (activeTriggerButton) {
            activeTriggerButton.disabled = true;
            activeTriggerButton.innerHTML = '<span class="spinner"></span> Memproses...';
        }

        try {
            const response = await fetch('api/create-transaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    paket_id: selectedPackage.id,
                    payment_method: method
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Gagal membuat transaksi.');
            }

            if (method === 'cash' && data.data.redirect_url) {
                window.location.href = data.data.redirect_url;
                return;
            }

            if (data.data.snap_token && window.snap) {
                window.snap.pay(data.data.snap_token, {
                    onSuccess: function() {
                        window.location.href = `success.php?order_id=${encodeURIComponent(data.data.order_id)}`;
                    },
                    onPending: function() {
                        window.location.href = `checkout.php?order_id=${encodeURIComponent(data.data.order_id)}`;
                    },
                    onClose: function() { restoreButtons(); },
                    onError: function() {
                        alert('Pembayaran gagal atau ditolak.');
                        restoreButtons();
                    }
                });
                return;
            }

            if (data.data.redirect_url) {
                window.location.href = data.data.redirect_url;
                return;
            }

            if (data.data.order_id) {
                window.location.href = `checkout.php?order_id=${encodeURIComponent(data.data.order_id)}`;
                return;
            }

            throw new Error('Respons transaksi tidak lengkap.');
        } catch (error) {
            alert('Gagal memproses: ' + error.message);
            restoreButtons();
        }

        function restoreButtons() {
            actionButton.disabled = false;
            actionButton.innerHTML = originalActionLabel;
            if (activeTriggerButton) {
                activeTriggerButton.disabled = false;
                activeTriggerButton.innerHTML = originalTriggerLabel;
            }
        }
    }
    </script>
</body>
</html>
