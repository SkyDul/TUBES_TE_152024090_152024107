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
    <title>Voucher WiFi RipaNet | Beli Online atau Cash</title>
    <meta name="description" content="Landing page voucher WiFi RipaNet dengan pembelian QRIS, pembayaran tunai, dan alur POS kasir yang lebih rapi.">
    <link rel="icon" type="image/png" href="assets/img/logo-RipaNet.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="<?= htmlspecialchars($snapJsUrl) ?>" data-client-key="<?= htmlspecialchars($clientKey) ?>"></script>
</head>
<body>
    <div class="page-shell">
        <header class="topbar">
            <div class="topbar__inner">
                <a class="brand" href="./">
                    <img class="brand__logo" src="assets/img/logo-RipaNet.png" alt="Logo RipaNet">
                    <span class="brand__meta">
                        <strong>RipaNet Hotspot</strong>
                        <span>Voucher instan, checkout cepat, kasir lebih rapi</span>
                    </span>
                </a>
                <div class="nav-actions">
                    <a class="nav-link" href="#paket">Paket</a>
                    <a class="nav-link" href="#cara-beli">Cara Beli</a>
                    <a class="btn btn-secondary btn-sm" href="admin/">Masuk POS Kasir</a>
                </div>
            </div>
        </header>

        <main class="container">
            <section class="hero">
                <div class="hero__layout">
                    <div class="hero__copy">
                        <span class="eyebrow">Hotspot Voucher RipaNet</span>
                        <h1 class="hero__title">Beli voucher WiFi dalam hitungan detik, online maupun langsung di kasir.</h1>
                        <p class="hero__subtitle">
                            Halaman ini sekarang difokuskan untuk konversi: pengunjung bisa langsung pilih paket, bayar via QRIS atau tunai,
                            lalu voucher muncul otomatis tanpa alur yang membingungkan.
                        </p>

                        <div class="hero__actions">
                            <a class="btn btn-primary" href="#paket">Pilih Paket Sekarang</a>
                            <a class="btn btn-outline" href="admin/">Buka Sistem POS</a>
                        </div>

                        <div class="hero__bullets">
                            <span class="hero__bullet">QRIS dan e-wallet</span>
                            <span class="hero__bullet">Tunai via kasir</span>
                            <span class="hero__bullet">Voucher aktif otomatis</span>
                        </div>
                    </div>

                    <aside class="hero__panel">
                        <div class="hero-stats">
                            <div class="stat-card">
                                <div class="stat-card__label">Paket aktif</div>
                                <div class="stat-card__value"><?= number_format($packageCount) ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card__label">Harga mulai</div>
                                <div class="stat-card__value">Rp <?= number_format($cheapestPrice, 0, ',', '.') ?></div>
                            </div>
                        </div>

                        <div class="hero-board">
                            <div class="hero-board__row">
                                <span>Checkout publik</span>
                                <strong>QRIS / Cash</strong>
                            </div>
                            <div class="hero-board__row">
                                <span>POS kasir</span>
                                <strong>Approve dan cetak cepat</strong>
                            </div>
                            <div class="hero-board__row">
                                <span>Voucher</span>
                                <strong>Otomatis setelah lunas</strong>
                            </div>
                        </div>

                        <div class="hero-flow">
                            <div class="flow-card">
                                <span class="flow-card__number">1</span>
                                <div>
                                    <h3>Pilih paket</h3>
                                    <p>Pelanggan langsung melihat opsi yang paling jelas dan paling relevan.</p>
                                </div>
                            </div>
                            <div class="flow-card">
                                <span class="flow-card__number">2</span>
                                <div>
                                    <h3>Pilih metode bayar</h3>
                                    <p>Online pakai Midtrans atau tunai lewat meja kasir.</p>
                                </div>
                            </div>
                            <div class="flow-card">
                                <span class="flow-card__number">3</span>
                                <div>
                                    <h3>Voucher langsung keluar</h3>
                                    <p>Setelah settlement, kode voucher tampil dan bisa dicetak jadi struk.</p>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </section>

            <section class="section">
                <div class="cards-grid">
                    <article class="info-card">
                        <h3>Tampilan lebih meyakinkan</h3>
                        <p>Hero, CTA, dan kartu paket sekarang disusun supaya pelanggan paham alurnya tanpa perlu bertanya dulu.</p>
                    </article>
                    <article class="info-card info-card--accent">
                        <h3>Lebih cocok buat jualan</h3>
                        <p>Paket unggulan diberi penekanan, harga lebih tegas, dan informasi pembayaran tidak tercecer.</p>
                    </article>
                    <article class="info-card">
                        <h3>Nyambung ke POS</h3>
                        <p>Pengunjung yang ingin bayar cash tetap masuk ke antrean kasir yang sudah dipoles ulang di area admin.</p>
                    </article>
                </div>
            </section>

            <section class="section" id="paket">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">Daftar Paket</span>
                        <h2>Pilih durasi yang paling pas untuk pelanggan Anda.</h2>
                    </div>
                    <p>Setiap paket di bawah bisa dibayar online atau tunai. Paket dengan nilai terbaik kami sorot supaya keputusan beli lebih cepat.</p>
                </div>

                <div class="packages-wrap">
                    <div class="packages-grid">
                        <?php if (empty($packages)): ?>
                            <div class="empty-state">
                                <strong>Belum ada paket aktif.</strong>
                                <span>Tambahkan paket voucher di database supaya halaman jualannya tampil.</span>
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
                                            <span class="package-badge"><?= $isFeatured ? 'Best Value' : 'Voucher WiFi' ?></span>
                                            <h3 class="package-card__name"><?= htmlspecialchars($package['nama_paket']) ?></h3>
                                            <p class="package-card__duration"><?= htmlspecialchars($package['durasi_display']) ?></p>
                                        </div>
                                        <span class="muted">Mulai <?= number_format($perDay, 0, ',', '.') ?>/hari</span>
                                    </div>

                                    <div class="package-card__price"><?= $priceDisplay ?></div>
                                    <p class="package-caption">Cocok untuk pembelian cepat di halaman ini maupun penjualan manual dari meja kasir.</p>

                                    <ul class="package-feature-list">
                                        <li>Voucher otomatis muncul setelah pembayaran sukses.</li>
                                        <li>Dapat dibayar dengan QRIS, e-wallet, transfer, atau tunai.</li>
                                        <li>Struk dan invoice tetap bisa dicetak setelah transaksi selesai.</li>
                                    </ul>

                                    <div class="package-helper">
                                        <span><?= htmlspecialchars($package['durasi_display']) ?></span>
                                        <span>Checkout fleksibel</span>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn <?= $isFeatured ? 'btn-accent' : 'btn-primary' ?> buy-package"
                                        data-id="<?= (int) $package['id'] ?>"
                                        data-name="<?= htmlspecialchars($package['nama_paket'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-price="<?= (int) $package['harga'] ?>"
                                        data-price-display="<?= htmlspecialchars($priceDisplay, ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        Beli Paket Ini
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
                        <span class="eyebrow" style="background: rgba(255,255,255,0.12); color: #ffe9ca; border-color: rgba(255,255,255,0.16);">Cara Beli</span>
                        <h2 style="margin-top: 16px;">Dua jalur pembelian, satu pengalaman yang tetap rapi.</h2>
                        <ul>
                            <li>Pilih paket lalu lanjut ke pembayaran online jika pelanggan ingin self-service.</li>
                            <li>Jika pelanggan bayar tunai, sistem membuat order cash dan menunggu kasir melakukan konfirmasi.</li>
                            <li>Begitu lunas, voucher dan invoice tersedia tanpa input manual tambahan.</li>
                        </ul>
                    </article>

                    <article class="showcase-card">
                        <span class="eyebrow">Kenapa Lebih Enak Dipakai</span>
                        <h2 style="margin-top: 16px;">UI publik dan POS sekarang saling terhubung secara visual.</h2>
                        <ul>
                            <li>Bahasa visual lebih konsisten antara landing page, checkout, success page, dan admin.</li>
                            <li>Informasi penting seperti harga, status, dan order ID tampil lebih tegas.</li>
                            <li>Tombol aksi utama diprioritaskan supaya user tidak bingung langkah berikutnya.</li>
                        </ul>
                    </article>
                </div>
            </section>

            <section class="section">
                <div class="support-grid">
                    <article class="support-card">
                        <strong>Pembayaran Online</strong>
                        <p>Midtrans Snap tetap dipakai untuk QRIS, e-wallet, dan metode online lainnya.</p>
                    </article>
                    <article class="support-card">
                        <strong>Pembayaran Tunai</strong>
                        <p>Masuk ke antrean kasir, lalu admin tinggal approve dari terminal POS yang baru.</p>
                    </article>
                    <article class="support-card">
                        <strong>Voucher & Struk</strong>
                        <p>Setelah settlement, pelanggan bisa langsung melihat voucher dan membuka invoice cetak.</p>
                    </article>
                </div>
            </section>

            <section class="section">
                <div class="cta-band">
                    <div>
                        <h2>Siap jual voucher lebih cepat dan lebih rapi?</h2>
                        <p>Mulai dari halaman publik ini untuk pelanggan, atau masuk ke sistem POS untuk penjualan dan approval kasir.</p>
                        <div class="cta-band__actions">
                            <a class="btn btn-accent" href="#paket">Mulai dari Paket</a>
                            <a class="btn btn-secondary" href="admin/">Masuk POS Kasir</a>
                        </div>
                    </div>
                    <div class="cta-band__stats">
                        <div class="cta-mini">
                            <strong>1 halaman jualan</strong>
                            <span>Lebih kuat untuk pengunjung baru.</span>
                        </div>
                        <div class="cta-mini">
                            <strong>1 sistem POS</strong>
                            <span>Lebih ringkas untuk kasir dan admin.</span>
                        </div>
                        <div class="cta-mini">
                            <strong>0 langkah mubazir</strong>
                            <span>Checkout, settlement, voucher, dan print tersambung.</span>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="footer">
            <div class="container">
                <div class="footer__card">
                    <div>
                        <strong>RipaNet Hotspot Billing</strong>
                        <p>Pembayaran diproses oleh Midtrans dan voucher dihasilkan otomatis setelah lunas.</p>
                    </div>
                    <div class="footer__links">
                        <a href="#paket">Paket</a>
                        <a href="#cara-beli">Cara Beli</a>
                        <a href="admin/">POS Kasir</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <div class="modal-overlay" id="payment-modal">
        <div class="modal-content">
            <button class="modal-close" type="button" id="payment-modal-close" aria-label="Tutup modal">&times;</button>
            <div class="modal-header">
                <h3 class="modal-title">Pilih metode pembayaran</h3>
                <p class="helper-text">
                    Paket <strong id="modal-package-name">-</strong> dengan harga <strong id="modal-package-price">-</strong>
                </p>
            </div>

            <div class="payment-options">
                <div class="payment-option">
                    <strong>Bayar online</strong>
                    <p>Gunakan QRIS, e-wallet, virtual account, atau metode online lain dari Midtrans.</p>
                    <button type="button" class="btn btn-primary payment-action" data-method="online">Lanjut Bayar Online</button>
                </div>

                <div class="payment-option">
                    <strong>Bayar tunai</strong>
                    <p>Sistem akan membuat order cash, lalu pelanggan menunjukkan order ID ke kasir.</p>
                    <button type="button" class="btn btn-accent payment-action" data-method="cash">Buat Order Tunai</button>
                </div>
            </div>
        </div>
    </div>

    <script>
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
        if (event.target === paymentModal) {
            closePaymentModal();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePaymentModal();
        }
    });

    paymentActions.forEach((button) => {
        button.addEventListener('click', () => processPayment(button.dataset.method, button));
    });

    async function processPayment(method, actionButton) {
        if (!selectedPackage) {
            return;
        }

        const originalActionLabel = actionButton.innerHTML;
        const originalTriggerLabel = activeTriggerButton ? activeTriggerButton.innerHTML : '';

        actionButton.disabled = true;
        actionButton.innerHTML = '<span class="spinner"></span> Memproses...';

        if (activeTriggerButton) {
            activeTriggerButton.disabled = true;
            activeTriggerButton.innerHTML = '<span class="spinner"></span> Menyiapkan...';
        }

        try {
            const response = await fetch('api/create-transaction.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
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
                    onClose: function() {
                        restoreButtons();
                    },
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
            alert('Gagal memproses transaksi: ' + error.message);
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
