<?php
/**
 * API: Get Packages
 * 
 * Ambil daftar paket voucher yang aktif
 * 
 * GET /api/get-packages.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDB();
    
    $stmt = $pdo->query("
        SELECT id, nama_paket, harga, durasi_display, durasi_hari
        FROM paket_voucher 
        WHERE is_active = 1 
        ORDER BY harga ASC
    ");
    
    $packages = $stmt->fetchAll();
    
    // Format harga untuk display
    foreach ($packages as &$pkg) {
        $pkg['harga_display'] = 'Rp ' . number_format($pkg['harga'], 0, ',', '.');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $packages
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => env('APP_DEBUG') ? $e->getMessage() : 'Server error'
    ]);
}
