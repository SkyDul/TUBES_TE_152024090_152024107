<?php
/**
 * API: Midtrans Notification Handler (Webhook/Callback)
 * 
 * Endpoint ini menerima notifikasi dari Midtrans saat ada pembayaran
 * URL ini harus didaftarkan di Dashboard Midtrans
 * 
 * POST /api/notification.php
 */

// Log semua request untuk debugging
error_log("=== MIDTRANS NOTIFICATION RECEIVED ===");

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/MidtransService.php';
require_once __DIR__ . '/../includes/MikrotikAPI.php';
require_once __DIR__ . '/../includes/VoucherGenerator.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

try {
    // Get raw input
    $rawBody = file_get_contents('php://input');
    error_log("Notification Body: " . $rawBody);
    
    $notification = json_decode($rawBody, true);
    
    if (!$notification) {
        throw new Exception('Invalid JSON payload');
    }
    
    // Extract required fields
    $orderId = $notification['order_id'] ?? '';
    $transactionStatus = $notification['transaction_status'] ?? '';
    $statusCode = $notification['status_code'] ?? '';
    $grossAmount = $notification['gross_amount'] ?? '';
    $signatureKey = $notification['signature_key'] ?? '';
    $fraudStatus = $notification['fraud_status'] ?? 'accept';
    $paymentType = $notification['payment_type'] ?? '';
    
    // Validate required fields
    if (empty($orderId) || empty($signatureKey)) {
        throw new Exception('Missing required fields');
    }
    
    // VALIDATE SIGNATURE - CRITICAL!
    $midtrans = new MidtransService();
    
    if (!$midtrans->verifySignature($orderId, $statusCode, $grossAmount, $signatureKey)) {
        error_log("INVALID SIGNATURE for order: $orderId");
        http_response_code(403);
        exit(json_encode(['error' => 'Invalid signature']));
    }
    
    $pdo = getDB();
    
    // Begin transaction for atomicity
    $pdo->beginTransaction();
    
    try {
        // Get transaction with lock (prevent race condition)
        $stmt = $pdo->prepare("
            SELECT t.*, p.mikrotik_profile, p.durasi_hari 
            FROM transaksi t
            JOIN paket_voucher p ON t.paket_id = p.id
            WHERE t.order_id = ? 
            FOR UPDATE
        ");
        $stmt->execute([$orderId]);
        $transaksi = $stmt->fetch();
        
        if (!$transaksi) {
            throw new Exception("Transaction not found: $orderId");
        }
        
        // VALIDATE AMOUNT - Prevent manipulation!
        if ((int)$grossAmount !== (int)$transaksi['amount']) {
            throw new Exception("Amount mismatch! Expected: {$transaksi['amount']}, Got: $grossAmount");
        }
        
        // Check if already processed (idempotency)
        if ($transaksi['status'] === 'settlement') {
            // Already processed, just return success
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Already processed']);
            exit;
        }
        
        // Process based on transaction status
        if ($transactionStatus === 'capture' || $transactionStatus === 'settlement') {
            // Check fraud status
            if ($fraudStatus !== 'accept') {
                throw new Exception("Transaction flagged as fraud: $fraudStatus");
            }
            
            // PAYMENT SUCCESS! Generate voucher
            $voucher = VoucherGenerator::generateUnique($pdo, $transaksi['durasi_hari']);
            
            // Convert durasi_hari ke format Mikrotik (contoh: 1d 00:00:00)
            $limitUptime = $transaksi['durasi_hari'] . 'd 00:00:00';
            
            // Add user to Mikrotik
            $mikrotik = new MikrotikAPI();
            $mikrotikSuccess = $mikrotik->addHotspotUser(
                $voucher['user'],
                $voucher['pass'],
                $transaksi['mikrotik_profile'],
                $limitUptime
            );
            
            if (!$mikrotikSuccess) {
                // Log but don't fail - we can retry manually
                error_log("WARNING: Failed to add Mikrotik user for order: $orderId");
                // Still proceed to update database
            }
            
            // Update database
            $stmt = $pdo->prepare("
                UPDATE transaksi SET 
                    status = 'settlement',
                    mikrotik_user = ?,
                    mikrotik_pass = ?,
                    paid_at = NOW(),
                    payment_type = ?,
                    raw_notification = ?
                WHERE order_id = ?
            ");
            $stmt->execute([
                $voucher['user'],
                $voucher['pass'],
                $paymentType,
                $rawBody,
                $orderId
            ]);
            
            error_log("SUCCESS: Voucher created for order $orderId - User: {$voucher['user']}");
            
        } elseif (in_array($transactionStatus, ['deny', 'cancel', 'expire'])) {
            // Payment failed
            $stmt = $pdo->prepare("
                UPDATE transaksi SET 
                    status = ?,
                    raw_notification = ?
                WHERE order_id = ?
            ");
            $stmt->execute([$transactionStatus, $rawBody, $orderId]);
            
            error_log("Payment failed for order $orderId - Status: $transactionStatus");
            
        } elseif ($transactionStatus === 'pending') {
            // Still pending, just log
            error_log("Payment pending for order $orderId");
        }
        
        $pdo->commit();
        
        // Return success to Midtrans
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Notification Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
