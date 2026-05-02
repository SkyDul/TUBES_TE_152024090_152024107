<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    
    if (in_array($action, ['approved', 'rejected', 'pending'])) {
        try {
            $stmt = $pdo->prepare("UPDATE agents SET status = ? WHERE id = ?");
            $stmt->execute([$action, $id]);
            $success_msg = "Status agen berhasil diubah menjadi: " . ucfirst($action);
        } catch (PDOException $e) {
            $error_msg = "Gagal mengubah status: " . $e->getMessage();
        }
    }
}

// Fetch agents
$agents = $pdo->query("SELECT * FROM agents ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Agen — RipaNet Admin</title>
    <link rel="icon" type="image/png" href="../assets/img/logo-RipaNet.png">
    <link rel="stylesheet" href="../assets/css/style.css?v=6">
    <style>
        .agent-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .agent-info h4 { margin: 0 0 8px 0; color: var(--text); }
        .agent-info p { margin: 0 0 4px 0; color: var(--text-muted); font-size: 0.9rem; }
        .agent-actions {
            display: flex;
            gap: 8px;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-badge--pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
        .status-badge--approved { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .status-badge--rejected { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
    </style>
</head>
<body>
    <div class="container admin-shell">
        <header class="admin-topbar">
            <a class="brand" href="index.php">
                <img class="brand__logo" src="../assets/img/logo-RipaNet.png" alt="Logo RipaNet">
                <span class="brand__meta">
                    <strong>RipaNet Admin</strong>
                    <span>Kelola Agen</span>
                </span>
            </a>
            <button class="admin-nav-toggle" id="admin-nav-toggle" aria-label="Menu">
                <span class="icon"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></span>
            </button>
            <nav class="admin-topbar__links" id="admin-nav">
                <a href="index.php">Dashboard</a>
                <a href="pos.php">Penjualan</a>
                <a href="products.php">Produk</a>
                <a href="agents.php" class="active">Agen</a>
                <a href="logout.php" class="danger">Keluar</a>
            </nav>
        </header>

        <section class="admin-kpis" style="grid-template-columns:repeat(3,1fr);">
            <article class="kpi-card">
                <div class="kpi-card__label">Total Agen</div>
                <div class="kpi-card__value"><?= count($agents) ?></div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Agen Aktif (Approved)</div>
                <div class="kpi-card__value"><?= count(array_filter($agents, fn($a) => $a['status'] === 'approved')) ?></div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card__label">Menunggu Persetujuan</div>
                <div class="kpi-card__value"><?= count(array_filter($agents, fn($a) => $a['status'] === 'pending')) ?></div>
            </article>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>Daftar Agen Offline</h2>
                    <p>Kelola permohonan dan daftar agen penjualan voucher Anda.</p>
                </div>
            </div>

            <?php if (isset($success_msg)): ?>
                <div style="padding: 12px; background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 6px; margin-bottom: 20px;">
                    <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error_msg)): ?>
                <div style="padding: 12px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 6px; margin-bottom: 20px;">
                    <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($agents)): ?>
                <div class="empty-state">
                    <strong>Belum ada agen.</strong>
                    <span>Pendaftar agen akan muncul di sini.</span>
                </div>
            <?php else: ?>
                <div>
                    <?php foreach ($agents as $agent): ?>
                        <div class="agent-card">
                            <div class="agent-info">
                                <h4><?= htmlspecialchars($agent['nama_lengkap']) ?> 
                                    <span class="status-badge status-badge--<?= $agent['status'] ?>"><?= ucfirst($agent['status']) ?></span>
                                </h4>
                                <p><strong>WA:</strong> <?= htmlspecialchars($agent['no_wa']) ?></p>
                                <p><strong>NIK:</strong> <?= htmlspecialchars($agent['nik_ktp']) ?></p>
                                <p><strong>Alamat:</strong> <?= nl2br(htmlspecialchars($agent['alamat'])) ?></p>
                                <p><strong>Terdaftar:</strong> <?= date('d M Y H:i', strtotime($agent['created_at'])) ?></p>
                                <?php if (!empty($agent['contract_file'])): ?>
                                    <p style="margin-top: 8px;">
                                        <a href="../<?= htmlspecialchars($agent['contract_file']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px;">
                                            <span class="icon icon--sm"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
                                            Lihat Surat Kontrak
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="agent-actions">
                                <form method="POST" action="" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $agent['id'] ?>">
                                    <?php if ($agent['status'] === 'pending'): ?>
                                        <button type="submit" name="action" value="approved" class="btn btn-primary btn-sm">Setujui</button>
                                        <button type="submit" name="action" value="rejected" class="btn btn-danger btn-sm">Tolak</button>
                                    <?php elseif ($agent['status'] === 'approved'): ?>
                                        <button type="submit" name="action" value="rejected" class="btn btn-danger btn-sm">Cabut Akses (Tolak)</button>
                                    <?php elseif ($agent['status'] === 'rejected'): ?>
                                        <button type="submit" name="action" value="approved" class="btn btn-primary btn-sm">Pulihkan (Setujui)</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
