<?php
/**
 * API: Create Transaction
 * 
 * Buat transaksi baru dan request QRIS ke Midtrans
 * 
 * POST /api/create-transaction.php
 * Body: { "paket_id": 1, "phone": "08xxx" (optional) }
 */

// DEBUG: Show all errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/MidtransService.php';

try {
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['paket_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'paket_id is required']);
        exit;
    }
    
    $paketId = (int) $input['paket_id'];
    $phone = $input['phone'] ?? '';
    $paymentMethod = $input['payment_method'] ?? 'online';
    
    $pdo = getDB();
    
    // Get paket info
    $stmt = $pdo->prepare("SELECT * FROM paket_voucher WHERE id = ? AND is_active = 1");
    $stmt->execute([$paketId]);
    $paket = $stmt->fetch();
    
    if (!$paket) {
        http_response_code(404);
        echo json_encode(['error' => 'Paket tidak ditemukan']);
        exit;
    }
    
    // Generate unique order ID
    $orderId = 'ORDER-' . time() . '-' . strtoupper(bin2hex(random_bytes(4)));
    
    // Calculate expire time
    $expireMinutes = (int) env('TRANSACTION_EXPIRE_MINUTES', 15);
    $expiredAt = date('Y-m-d H:i:s', strtotime("+$expireMinutes minutes"));
    
    // Insert to database (status: pending)
    $stmt = $pdo->prepare("
        INSERT INTO transaksi (order_id, paket_id, amount, customer_phone, expired_at, ip_address, payment_type)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $orderId,
        $paketId,
        $paket['harga'],
        $phone,
        $expiredAt,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $paymentMethod === 'cash' ? 'cash' : 'online'
    ]);
    
    if ($paymentMethod === 'cash') {
        echo json_encode([
            'success' => true,
            'data' => [
                'order_id' => $orderId,
                'redirect_url' => "checkout-cash.php?order_id=$orderId",
                'amount' => $paket['harga'],
                'amount_display' => 'Rp ' . number_format($paket['harga'], 0, ',', '.'),
                'paket_nama' => $paket['nama_paket'],
                'needs_cash_detection' => true,
                'expired_at' => $expiredAt,
                'expire_minutes' => $expireMinutes
            ]
        ]);
        exit;
    }
    
    // Create Midtrans transaction
    $midtrans = new MidtransService();
    
    $itemDetails = [[
        'id' => (string) $paket['id'],
        'price' => $paket['harga'],
        'quantity' => 1,
        'name' => $paket['nama_paket']
    ]];
    
    $response = $midtrans->createSnapTransaction($orderId, $paket['harga'], $itemDetails);
    
    // Check for error
    if (isset($response['error']) || isset($response['error_messages'])) {
        error_log("Midtrans Snap Error: " . json_encode($response));
        
        // Update status to failed
        $pdo->prepare("UPDATE transaksi SET status = 'deny' WHERE order_id = ?")
            ->execute([$orderId]);
        
        http_response_code(500);
        echo json_encode([
            'error' => 'Gagal membuat transaksi',
            'details' => env('APP_DEBUG') ? $response : null
        ]);
        exit;
    }
    
    // Extract Snap URL
    $redirectUrl = $response['redirect_url'] ?? null;
    $snapToken = $response['token'] ?? null;
    
    // Update database with Snap URL and Midtrans token
    $stmt = $pdo->prepare("
        UPDATE transaksi 
        SET qr_url = ?, midtrans_transaction_id = ?
        WHERE order_id = ?
    ");
    $stmt->execute([
        $redirectUrl,
        $snapToken,
        $orderId
    ]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => [
            'order_id' => $orderId,
            'qr_url' => $redirectUrl, // keep field name for backward compatibility or debugging
            'redirect_url' => $redirectUrl,
            'snap_token' => $snapToken,
            'amount' => $paket['harga'],
            'amount_display' => 'Rp ' . number_format($paket['harga'], 0, ',', '.'),
            'paket_nama' => $paket['nama_paket'],
            'expired_at' => $expiredAt,
            'expire_minutes' => $expireMinutes
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Create Transaction Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => env('APP_DEBUG') ? $e->getMessage() : 'Server error'
    ]);
}
