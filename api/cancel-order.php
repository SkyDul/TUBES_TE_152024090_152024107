<?php
/**
 * API: Cancel Transaction
 * 
 * Endpoint to manually cancel a pending transaction.
 * 
 * POST /api/cancel-order.php
 * Body: {"order_id": "ORDER-xxx"}
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

require_once __DIR__ . '/../config/database.php';

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true) ?? [];
$orderId = $payload['order_id'] ?? '';

if (empty($orderId)) {
    http_response_code(400);
    exit(json_encode(['error' => 'order_id is required']));
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        UPDATE transaksi 
        SET status = 'expire' 
        WHERE order_id = ? AND status = 'pending'
    ");
    $stmt->execute([$orderId]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => env('APP_DEBUG') ? $e->getMessage() : 'Server error'
    ]);
}
