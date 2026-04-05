<?php
/**
 * API: Detect cash authenticity (dummy/remote bridge).
 *
 * Flow:
 * 1) cashier triggers detection for a cash order
 * 2) detector returns verdict (genuine/counterfeit/uncertain)
 * 3) admin can approve only when verdict is genuine
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
require_once __DIR__ . '/../../includes/CashDetectorService.php';

try {
    // Terima data dari FormData, bukan dari payload JSON
    $orderId = trim((string) ($_POST['order_id'] ?? ''));

    if ($orderId === '') {
        throw new Exception('order_id wajib diisi.');
    }

    // Cek apakah ada file foto/kamera yang dikirim
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Foto uang wajib disertakan untuk deteksi.');
    }
    
    // Ambil lokasi file foto sementara di server PHP
    $imagePath = $_FILES['image']['tmp_name'];
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT t.order_id, t.status, t.amount, t.payment_type, p.nama_paket
        FROM transaksi t
        JOIN paket_voucher p ON p.id = t.paket_id
        WHERE t.order_id = ? AND t.payment_type = 'cash'
        LIMIT 1
    ");
    $stmt->execute([$orderId]);
    $transaksi = $stmt->fetch();

    if (!$transaksi) {
        throw new Exception('Transaksi cash tidak ditemukan.');
    }

    if ($transaksi['status'] !== 'pending') {
        throw new Exception('Deteksi hanya berlaku untuk transaksi cash berstatus pending.');
    }

    $requestedBy = $_SESSION['admin_user'] ?? 'system';
    $detector = new CashDetectorService($pdo);
   $detection = $detector->detectCash($orderId, (int) $transaksi['amount'], $requestedBy, $imagePath);

    $confidencePercent = round(((float) $detection['confidence']) * 100, 2);
    $canApprove = $detection['verdict'] === 'genuine';

    echo json_encode([
        'success' => true,
        'data' => [
            'order_id' => $orderId,
            'paket_nama' => $transaksi['nama_paket'],
            'amount' => (int) $transaksi['amount'],
            'amount_display' => 'Rp ' . number_format($transaksi['amount'], 0, ',', '.'),
            'detection' => [
                'id' => (int) $detection['id'],
                'verdict' => $detection['verdict'],
                'confidence' => (float) $detection['confidence'],
                'confidence_percent' => $confidencePercent,
                'detected_amount' => (int) $detection['detected_amount'],
                'mode' => $detection['detector_mode'],
                'notes' => $detection['notes'],
                'detection_ref' => $detection['detection_ref'],
                'created_at' => $detection['created_at'],
            ],
            'can_approve' => $canApprove,
            'next_action' => $canApprove
                ? 'Uang terdeteksi asli. Admin dapat melanjutkan approval.'
                : 'Uang belum valid. Lakukan scan ulang atau tolak transaksi.',
        ]
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
