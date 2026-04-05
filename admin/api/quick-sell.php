<?php
/**
 * API: Quick Sell — One-click cash transaction + voucher generation
 * Creates order, settles it immediately, and returns voucher credentials.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/MikrotikAPI.php';
require_once __DIR__ . '/../../includes/VoucherGenerator.php';

try {
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);
    $paketId = (int) ($payload['paket_id'] ?? 0);

    if ($paketId <= 0) {
        throw new Exception("Paket ID tidak valid.");
    }

    $pdo = getDB();
    $pdo->beginTransaction();

    // Get package
    $stmt = $pdo->prepare("
        SELECT id, nama_paket, harga, mikrotik_profile, durasi_hari, durasi_display
        FROM paket_voucher
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$paketId]);
    $package = $stmt->fetch();

    if (!$package) {
        throw new Exception("Paket tidak ditemukan atau tidak aktif.");
    }

    // Generate order ID
    $orderId = 'CASH-' . strtoupper(bin2hex(random_bytes(4))) . '-' . time();

    // Generate voucher
    $voucher = VoucherGenerator::generateUnique($pdo, $package['durasi_hari']);
    $limitUptime = $package['durasi_hari'] . 'd 00:00:00';

    // Add to Mikrotik
    $mikrotik = new MikrotikAPI();
    $mikrotikSuccess = $mikrotik->addHotspotUser(
        $voucher['user'],
        $voucher['pass'],
        $package['mikrotik_profile'],
        $limitUptime
    );

    if (!$mikrotikSuccess) {
        error_log("Gagal menambahkan userman mikrotik untuk quick-sell $orderId");
    }

    $adminName = $_SESSION['admin_user'] ?? 'Unknown';

    $approvalNotes = json_encode([
        'approved_by' => $adminName,
        'method' => 'quick_sell',
        'detection' => 'skipped',
    ]);

    // Insert transaction as already settled
    $stmt = $pdo->prepare("
        INSERT INTO transaksi (
            order_id, paket_id, amount, payment_type,
            status, mikrotik_user, mikrotik_pass,
            paid_at, raw_notification, ip_address
        ) VALUES (?, ?, ?, 'cash', 'settlement', ?, ?, NOW(), ?, ?)
    ");
    $stmt->execute([
        $orderId,
        $package['id'],
        $package['harga'],
        $voucher['user'],
        $voucher['pass'],
        $approvalNotes,
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Voucher berhasil diterbitkan!',
        'data' => [
            'order_id' => $orderId,
            'paket_nama' => $package['nama_paket'],
            'durasi_display' => $package['durasi_display'],
            'amount' => (int) $package['harga'],
            'amount_display' => 'Rp ' . number_format($package['harga'], 0, ',', '.'),
            'voucher_username' => $voucher['user'],
            'voucher_password' => $voucher['pass'],
            'approved_by' => $adminName,
            'paid_at' => date('Y-m-d H:i:s'),
            'success_url' => '../success.php?order_id=' . urlencode($orderId),
            'invoice_url' => '../invoice.php?order_id=' . urlencode($orderId),
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
