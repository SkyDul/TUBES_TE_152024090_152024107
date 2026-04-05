<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$packages = $pdo->query("
    SELECT id, nama_paket, harga, mikrotik_profile, durasi_hari, durasi_display, is_active
    FROM paket_voucher
    ORDER BY harga ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk — RipaNet Admin</title>
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
                    <span>Kelola Produk</span>
                </span>
            </a>
            <button class="admin-nav-toggle" id="admin-nav-toggle" aria-label="Menu">
                <span class="icon"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></span>
            </button>
            <nav class="admin-topbar__links" id="admin-nav">
                <a href="index.php">Dashboard</a>
                <a href="pos.php">Penjualan</a>
                <a href="products.php" class="active">Produk</a>
                <a href="logout.php" class="danger">Keluar</a>
            </nav>
        </header>

        <section class="admin-kpis" style="grid-template-columns:repeat(3,1fr);">
            <article class="kpi-card">
                <div class="kpi-card__label">Total Paket</div>
                <div class="kpi-card__value"><?= count($packages) ?></div>
                <div class="kpi-card__hint">Semua paket</div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Paket Aktif</div>
                <div class="kpi-card__value"><?= count(array_filter($packages, fn($p) => $p['is_active'])) ?></div>
                <div class="kpi-card__hint">Tampil di halaman pelanggan</div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Paket Nonaktif</div>
                <div class="kpi-card__value"><?= count(array_filter($packages, fn($p) => !$p['is_active'])) ?></div>
                <div class="kpi-card__hint">Tidak tampil</div>
            </article>
        </section>

        <section class="dashboard-grid" style="grid-template-columns:1.1fr 0.9fr;">
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Daftar Paket Voucher</h2>
                        <p>Kelola paket yang tersedia untuk dijual.</p>
                    </div>
                    <button class="btn btn-primary btn-sm" type="button" id="btn-add">+ Tambah Paket</button>
                </div>

                <div class="product-card-list" id="product-tbody">
                    <?php foreach ($packages as $pkg): ?>
                    <div class="product-card-item" data-id="<?= $pkg['id'] ?>">
                        <div class="product-card-item__top">
                            <div class="product-card-item__info">
                                <div class="product-card-item__name"><?= htmlspecialchars($pkg['nama_paket']) ?></div>
                                <div class="product-card-item__tags">
                                    <span class="product-tag"><?= htmlspecialchars($pkg['durasi_display']) ?> (<?= $pkg['durasi_hari'] ?> hari)</span>
                                    <span class="product-tag product-tag--muted"><?= htmlspecialchars($pkg['mikrotik_profile']) ?></span>
                                </div>
                            </div>
                            <div class="product-card-item__right">
                                <div class="product-card-item__price">Rp <?= number_format($pkg['harga'], 0, ',', '.') ?></div>
                                <span class="status-badge <?= $pkg['is_active'] ? 'status-badge--success' : 'status-badge--danger' ?>">
                                    <?= $pkg['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </div>
                        </div>
                        <div class="product-card-item__actions">
                            <button class="btn btn-secondary btn-sm edit-btn"
                                data-id="<?= $pkg['id'] ?>"
                                data-name="<?= htmlspecialchars($pkg['nama_paket'], ENT_QUOTES) ?>"
                                data-price="<?= $pkg['harga'] ?>"
                                data-profile="<?= htmlspecialchars($pkg['mikrotik_profile'], ENT_QUOTES) ?>"
                                data-days="<?= $pkg['durasi_hari'] ?>"
                                data-display="<?= htmlspecialchars($pkg['durasi_display'], ENT_QUOTES) ?>"
                                data-active="<?= $pkg['is_active'] ?>"
                            ><span class="icon icon--sm"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></span> Edit</button>
                            <button class="btn btn-ghost btn-sm delete-btn" data-id="<?= $pkg['id'] ?>" data-name="<?= htmlspecialchars($pkg['nama_paket'], ENT_QUOTES) ?>"><span class="icon icon--sm"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></span> Hapus</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($packages)): ?>
                    <div class="empty-state" id="empty-row">
                        <strong>Belum ada paket.</strong>
                        <span>Klik "Tambah Paket" untuk mulai menambahkan.</span>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel" id="form-panel">
                <div class="panel-head">
                    <div>
                        <h3 id="form-title">Tambah Paket Baru</h3>
                        <p id="form-subtitle">Isi data paket voucher yang ingin dijual.</p>
                    </div>
                </div>

                <form class="product-form" id="product-form">
                    <input type="hidden" id="pkg-id" value="">

                    <div class="field">
                        <label for="pkg-name">Nama Paket</label>
                        <input id="pkg-name" class="input" type="text" placeholder="Contoh: Harian 5 Ribu" required>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="pkg-price">Harga (Rp)</label>
                            <input id="pkg-price" class="input" type="number" min="1000" step="500" placeholder="5000" required>
                        </div>
                        <div class="field">
                            <label for="pkg-days">Durasi (Hari)</label>
                            <input id="pkg-days" class="input" type="number" min="1" placeholder="1" required>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="pkg-display">Label Durasi</label>
                            <input id="pkg-display" class="input" type="text" placeholder="Contoh: 1 Hari" required>
                        </div>
                        <div class="field">
                            <label for="pkg-profile">Profil Mikrotik</label>
                            <input id="pkg-profile" class="input" type="text" placeholder="Contoh: 1hari" required>
                        </div>
                    </div>

                    <div class="field" style="flex-direction:row;align-items:center;gap:12px;">
                        <label class="toggle-switch">
                            <input type="checkbox" id="pkg-active" checked>
                            <span class="slider"></span>
                        </label>
                        <span style="font-weight:600;font-size:0.9rem;">Paket Aktif</span>
                    </div>

                    <div class="actions-row" style="margin-top:8px;">
                        <button type="submit" class="btn btn-primary" id="btn-save">Simpan Paket</button>
                        <button type="button" class="btn btn-ghost" id="btn-cancel" style="display:none;">Batal</button>
                    </div>
                </form>
            </section>
        </section>
    </div>

    <!-- Delete confirmation modal -->
    <div class="modal-overlay modal-alert" id="delete-modal">
        <div class="modal-content">
            <div class="modal-icon modal-icon--danger"><span class="icon icon--lg"><svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span></div>
            <h3 class="modal-title">Hapus Paket</h3>
            <div class="modal-body">Apakah Anda yakin ingin menghapus paket <strong id="delete-pkg-name">-</strong>?</div>
            <div class="modal-actions">
                <button class="btn btn-ghost" id="delete-cancel">Batal</button>
                <button class="btn btn-danger" id="delete-confirm">Hapus</button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="toast" style="display:none;"></div>

    <script>
    const form = document.getElementById('product-form');
    const tbody = document.getElementById('product-tbody');
    const formTitle = document.getElementById('form-title');
    const formSubtitle = document.getElementById('form-subtitle');
    const btnAdd = document.getElementById('btn-add');
    const btnCancel = document.getElementById('btn-cancel');
    const btnSave = document.getElementById('btn-save');
    const deleteModal = document.getElementById('delete-modal');
    const toastEl = document.getElementById('toast');

    let deleteTargetId = null;

    function resetForm() {
        document.getElementById('pkg-id').value = '';
        document.getElementById('pkg-name').value = '';
        document.getElementById('pkg-price').value = '';
        document.getElementById('pkg-days').value = '';
        document.getElementById('pkg-display').value = '';
        document.getElementById('pkg-profile').value = '';
        document.getElementById('pkg-active').checked = true;
        formTitle.textContent = 'Tambah Paket Baru';
        formSubtitle.textContent = 'Isi data paket voucher yang ingin dijual.';
        btnSave.textContent = 'Simpan Paket';
        btnCancel.style.display = 'none';
    }

    function showToast(msg, type = '') {
        toastEl.textContent = msg;
        toastEl.className = 'toast' + (type ? ' toast--' + type : '');
        toastEl.style.display = 'block';
        setTimeout(() => { toastEl.style.display = 'none'; }, 3000);
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function fmtPrice(n) {
        return 'Rp ' + Number(n).toLocaleString('id-ID');
    }

    // Add button
    btnAdd.addEventListener('click', () => {
        resetForm();
        document.getElementById('pkg-name').focus();
    });

    // Cancel edit
    btnCancel.addEventListener('click', resetForm);

    // Edit button
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.edit-btn');
        if (!btn) return;

        document.getElementById('pkg-id').value = btn.dataset.id;
        document.getElementById('pkg-name').value = btn.dataset.name;
        document.getElementById('pkg-price').value = btn.dataset.price;
        document.getElementById('pkg-days').value = btn.dataset.days;
        document.getElementById('pkg-display').value = btn.dataset.display;
        document.getElementById('pkg-profile').value = btn.dataset.profile;
        document.getElementById('pkg-active').checked = btn.dataset.active === '1';

        formTitle.textContent = 'Edit Paket';
        formSubtitle.textContent = 'Ubah data paket kemudian klik simpan.';
        btnSave.textContent = 'Perbarui Paket';
        btnCancel.style.display = 'inline-flex';
        document.getElementById('pkg-name').focus();

        document.getElementById('form-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // Delete button
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.delete-btn');
        if (!btn) return;

        deleteTargetId = btn.dataset.id;
        document.getElementById('delete-pkg-name').textContent = btn.dataset.name;
        deleteModal.classList.add('active');
    });

    document.getElementById('delete-cancel').addEventListener('click', () => {
        deleteModal.classList.remove('active');
        deleteTargetId = null;
    });

    deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) {
            deleteModal.classList.remove('active');
            deleteTargetId = null;
        }
    });

    document.getElementById('delete-confirm').addEventListener('click', async () => {
        if (!deleteTargetId) return;

        const btn = document.getElementById('delete-confirm');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Menghapus...';

        try {
            const res = await fetch('api/packages.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(deleteTargetId) })
            });
            const data = await res.json();

            if (!data.success) throw new Error(data.error);

            const row = tbody.querySelector(`.product-card-item[data-id="${deleteTargetId}"]`);
            if (row) row.remove();

            if (!tbody.querySelector('.product-card-item[data-id]')) {
                tbody.innerHTML = '<div class="empty-state" id="empty-row"><strong>Belum ada paket.</strong><span>Klik "Tambah Paket" untuk mulai menambahkan.</span></div>';
            }

            showToast(data.message, 'success');
            resetForm();
        } catch (err) {
            showToast('Gagal: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Hapus';
            deleteModal.classList.remove('active');
            deleteTargetId = null;
        }
    });

    // Save form
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const payload = {
            id: parseInt(document.getElementById('pkg-id').value) || 0,
            nama_paket: document.getElementById('pkg-name').value.trim(),
            harga: parseInt(document.getElementById('pkg-price').value) || 0,
            durasi_hari: parseInt(document.getElementById('pkg-days').value) || 1,
            durasi_display: document.getElementById('pkg-display').value.trim(),
            mikrotik_profile: document.getElementById('pkg-profile').value.trim(),
            is_active: document.getElementById('pkg-active').checked ? 1 : 0
        };

        if (!payload.nama_paket || payload.harga <= 0 || !payload.durasi_display || !payload.mikrotik_profile) {
            showToast('Lengkapi semua field.', 'error');
            return;
        }

        btnSave.disabled = true;
        btnSave.innerHTML = '<span class="spinner"></span> Menyimpan...';

        try {
            const res = await fetch('api/packages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (!data.success) throw new Error(data.error);

            // Reload to get fresh data
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 600);
        } catch (err) {
            showToast('Gagal: ' + err.message, 'error');
            btnSave.disabled = false;
            btnSave.textContent = payload.id ? 'Perbarui Paket' : 'Simpan Paket';
        }
    });
    // Admin nav toggle
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
