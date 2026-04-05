<?php
/**
 * API: Terima Uang Tunai & Generate Voucher
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
require_once __DIR__ . '/../../includes/CashDetectorService.php';

try {
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);
    $orderId = $payload['order_id'] ?? '';
    $userCashReceived = (int) ($payload['cash_received'] ?? 0);
    
    if (empty($orderId)) {
        throw new Exception("Order ID kosong");
    }
    
    $pdo = getDB();
    $pdo->beginTransaction();
    
    // Get transaction with lock
    $stmt = $pdo->prepare("
        SELECT t.*, p.nama_paket, p.durasi_display, p.mikrotik_profile, p.durasi_hari 
        FROM transaksi t
        JOIN paket_voucher p ON t.paket_id = p.id
        WHERE t.order_id = ? AND t.payment_type = 'cash'
        FOR UPDATE
    ");
    $stmt->execute([$orderId]);
    $transaksi = $stmt->fetch();
    
    if (!$transaksi) {
        throw new Exception("Transaksi uang tunai tidak ditemukan.");
    }
    
    if ($transaksi['status'] === 'settlement') {
        $pdo->commit();
        exit(json_encode([
            'success' => true,
            'message' => 'Sudah dilunasi sebelumnya',
            'data' => [
                'order_id' => $transaksi['order_id'],
                'paket_nama' => $transaksi['nama_paket'],
                'durasi_display' => $transaksi['durasi_display'],
                'amount' => (int) $transaksi['amount'],
                'amount_display' => 'Rp ' . number_format($transaksi['amount'], 0, ',', '.'),
                'voucher_username' => $transaksi['mikrotik_user'],
                'voucher_password' => $transaksi['mikrotik_pass'],
                'paid_at' => $transaksi['paid_at'],
                'success_url' => '../success.php?order_id=' . urlencode($transaksi['order_id']),
                'invoice_url' => '../invoice.php?order_id=' . urlencode($transaksi['order_id'])
            ]
        ]));
    }
    
    if ($transaksi['status'] !== 'pending') {
        throw new Exception("Status transaksi sudah " . $transaksi['status']);
    }

    // Optional: check if detection exists (no longer mandatory)
    $detector = new CashDetectorService($pdo);
    $latestDetection = null;
    try {
        $maxAgeMinutes = (int) env('CASH_DETECTOR_MAX_AGE_MINUTES', 30);
        $latestDetection = $detector->assertLatestIsGenuine($orderId, $maxAgeMinutes);
    } catch (Exception $detectionEx) {
        // Detection not required — proceed without it
        $latestDetection = null;
    }
    
    // Generate voucher
    $voucher = VoucherGenerator::generateUnique($pdo, $transaksi['durasi_hari']);
    $limitUptime = $transaksi['durasi_hari'] . 'd 00:00:00';
    
    // Add to Mikrotik
    $mikrotik = new MikrotikAPI();
    $mikrotikSuccess = $mikrotik->addHotspotUser(
        $voucher['user'],
        $voucher['pass'],
        $transaksi['mikrotik_profile'],
        $limitUptime
    );
    
    if (!$mikrotikSuccess) {
        error_log("Gagal menambahkan userman mikrotik untuk $orderId");
    }
    
    // Catat siapa Kasir nya di raw_notification/notes dan ubah status
    $adminName = $_SESSION['admin_user'] ?? 'Unknown Admin';
    $detectedAmount = $latestDetection ? (int) ($latestDetection['detected_amount'] ?? 0) : 0;
    $finalCashReceived = $userCashReceived > 0 ? $userCashReceived : $detectedAmount;
    
    $detectionInfo = $latestDetection ? [
        'id' => (int) $latestDetection['id'],
        'verdict' => $latestDetection['verdict'],
        'confidence' => (float) $latestDetection['confidence'],
        'detector_mode' => $latestDetection['detector_mode'],
        'detection_ref' => $latestDetection['detection_ref'],
        'detected_at' => $latestDetection['created_at'],
    ] : 'skipped';

    $approvalNotes = json_encode([
        'approved_by' => $adminName,
        'method' => 'cash_manual',
        'cash_received' => $finalCashReceived,
        'detection' => $detectionInfo,
    ]);
    
    $stmt = $pdo->prepare("
        UPDATE transaksi SET 
            status = 'settlement',
            mikrotik_user = ?,
            mikrotik_pass = ?,
            paid_at = NOW(),
            raw_notification = ?
        WHERE order_id = ?
    ");
    $stmt->execute([
        $voucher['user'],
        $voucher['pass'],
        $approvalNotes,
        $orderId
    ]);
    
    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Berhasil melunasi dan mencetak voucher!',
        'data' => [
            'order_id' => $orderId,
            'paket_nama' => $transaksi['nama_paket'],
            'durasi_display' => $transaksi['durasi_display'],
            'amount' => (int) $transaksi['amount'],
            'amount_display' => 'Rp ' . number_format($transaksi['amount'], 0, ',', '.'),
            'voucher_username' => $voucher['user'],
            'voucher_password' => $voucher['pass'],
            'approved_by' => $adminName,
            'detection' => $detectionInfo,
            'paid_at' => date('Y-m-d H:i:s'),
            'success_url' => '../success.php?order_id=' . urlencode($orderId),
            'invoice_url' => '../invoice.php?order_id=' . urlencode($orderId)
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $msg = $e->getMessage();
    $code = 500;
    if (
        stripos($msg, 'deteksi') !== false ||
        stripos($msg, 'palsu') !== false ||
        stripos($msg, 'scan ulang') !== false ||
        stripos($msg, 'pending') !== false
    ) {
        $code = 409;
    }
    http_response_code($code);
    echo json_encode(['error' => $e->getMessage()]);
}
