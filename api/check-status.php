<?php
/**
 * API: Check Transaction Status
 * 
 * Endpoint untuk polling status transaksi dari frontend
 * 
 * GET /api/check-status.php?order_id=ORDER-xxx
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

$orderId = $_GET['order_id'] ?? '';

if (empty($orderId)) {
    http_response_code(400);
    echo json_encode(['error' => 'order_id is required']);
    exit;
}

try {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        SELECT 
            t.order_id,
            t.status,
            t.mikrotik_user,
            t.mikrotik_pass,
            t.amount,
            t.expired_at,
            t.paid_at,
            p.nama_paket,
            p.durasi_display
        FROM transaksi t
        JOIN paket_voucher p ON t.paket_id = p.id
        WHERE t.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $transaksi = $stmt->fetch();
    
    if (!$transaksi) {
        http_response_code(404);
        echo json_encode(['error' => 'Transaksi tidak ditemukan']);
        exit;
    }
    
    // Check if expired
    $isExpired = strtotime($transaksi['expired_at']) < time();
    
    // Build response
    $response = [
        'success' => true,
        'data' => [
            'order_id' => $transaksi['order_id'],
            'status' => $transaksi['status'],
            'is_paid' => $transaksi['status'] === 'settlement',
            'is_expired' => $isExpired && $transaksi['status'] === 'pending',
            'paket_nama' => $transaksi['nama_paket'],
            'durasi' => $transaksi['durasi_display'],
            'amount_display' => 'Rp ' . number_format($transaksi['amount'], 0, ',', '.'),
            'expired_at' => $transaksi['expired_at'],
        ]
    ];
    
    // Include voucher if paid
    if ($transaksi['status'] === 'settlement') {
        $response['data']['voucher'] = [
            'username' => $transaksi['mikrotik_user'],
            'password' => $transaksi['mikrotik_pass'],
        ];
        $response['data']['paid_at'] = $transaksi['paid_at'];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => env('APP_DEBUG') ? $e->getMessage() : 'Server error'
    ]);
}
