<?php
/**
 * Package/Product CRUD API
 * POST: create or update package
 * DELETE: deactivate package
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Tidak terautentikasi.']);
    exit;
}

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $packages = $pdo->query("
            SELECT id, nama_paket, harga, mikrotik_profile, durasi_hari, durasi_display, is_active, created_at, updated_at
            FROM paket_voucher
            ORDER BY harga ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $packages]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $name = trim($input['nama_paket'] ?? '');
        $price = (int) ($input['harga'] ?? 0);
        $profile = trim($input['mikrotik_profile'] ?? '');
        $days = (int) ($input['durasi_hari'] ?? 1);
        $durationDisplay = trim($input['durasi_display'] ?? '');
        $isActive = isset($input['is_active']) ? (int) $input['is_active'] : 1;

        if ($name === '' || $price <= 0 || $profile === '' || $durationDisplay === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Semua field wajib diisi dengan benar.']);
            exit;
        }

        if ($days < 1) $days = 1;

        if ($id > 0) {
            // Update existing
            $stmt = $pdo->prepare("
                UPDATE paket_voucher
                SET nama_paket = ?, harga = ?, mikrotik_profile = ?, durasi_hari = ?, durasi_display = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $price, $profile, $days, $durationDisplay, $isActive, $id]);

            echo json_encode(['success' => true, 'message' => 'Paket berhasil diperbarui.', 'id' => $id]);
        } else {
            // Create new
            $stmt = $pdo->prepare("
                INSERT INTO paket_voucher (nama_paket, harga, mikrotik_profile, durasi_hari, durasi_display, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $price, $profile, $days, $durationDisplay, $isActive]);
            $newId = (int) $pdo->lastInsertId();

            echo json_encode(['success' => true, 'message' => 'Paket berhasil ditambahkan.', 'id' => $newId]);
        }
        exit;
    }

    if ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID paket tidak valid.']);
            exit;
        }

        // The user specifically requested hard-delete 'hapus hapus di dbnya'
        $pdo->beginTransaction();
        try {
            // Delete dependent cash_detection_logs first
            $stmt = $pdo->prepare("DELETE cdl FROM cash_detection_logs cdl INNER JOIN transaksi t ON cdl.order_id = t.order_id WHERE t.paket_id = ?");
            $stmt->execute([$id]);

            // Hard delete dependent transactions
            $stmt = $pdo->prepare("DELETE FROM transaksi WHERE paket_id = ?");
            $stmt->execute([$id]);
            
            // Hard delete the package
            $stmt = $pdo->prepare("DELETE FROM paket_voucher WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Paket berhasil dihapus permanen.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method tidak diizinkan.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Kesalahan server: ' . $e->getMessage()]);
}
