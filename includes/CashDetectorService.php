<?php
/**
 * Cash detector bridge service.
 *
 * This service keeps local detection logs and can call a remote Python API
 * when CASH_DETECTOR_URL is configured. If not configured, it runs a dummy
 * detector so FE/BE flow can be tested end-to-end.
 */
class CashDetectorService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureDetectionTable();
    }

    public function ensureDetectionTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS cash_detection_logs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                order_id VARCHAR(50) NOT NULL,
                requested_by VARCHAR(50) NOT NULL DEFAULT 'system',
                detector_mode VARCHAR(20) NOT NULL DEFAULT 'dummy',
                verdict ENUM('genuine', 'counterfeit', 'uncertain', 'error') NOT NULL DEFAULT 'uncertain',
                confidence DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
                detected_amount INT NOT NULL DEFAULT 0,
                detection_ref VARCHAR(120) NULL,
                notes VARCHAR(255) NULL,
                raw_response TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_order_created (order_id, created_at),
                INDEX idx_verdict_created (verdict, created_at)
            ) ENGINE=InnoDB
        ");
        try {
            $this->pdo->exec("ALTER TABLE cash_detection_logs ADD COLUMN detected_amount INT NOT NULL DEFAULT 0 AFTER confidence");
        } catch (PDOException $e) {}
    }

// Tambahkan parameter $imagePath di akhir
    public function detectCash(string $orderId, int $amount, string $requestedBy = 'system', string $imagePath = ''): array
    {
        $remoteUrl = rtrim((string) env('CASH_DETECTOR_URL', ''), '/');
        $mode = 'dummy';

        try {
            if ($remoteUrl !== '') {
                $endpoint = env('CASH_DETECTOR_ENDPOINT', '/detect');
                
                // Pastikan file gambar benar-benar ada sebelum dikirim ke AI Python
                if (empty($imagePath) || !file_exists($imagePath)) {
                    throw new Exception('Gambar foto uang tidak ditemukan untuk dideteksi AI.');
                }
                
                // Panggil remote detector dengan 3 parameter yang baru (URL, OrderID, PathGambar)
                $result = $this->callRemoteDetector($remoteUrl . $endpoint, $orderId, $imagePath);
                $mode = 'remote';
            } else {
                $result = $this->runDummyDetector($orderId, $amount);
            }
        } catch (Exception $e) {
            $result = [
                'verdict' => 'error',
                'confidence' => 0.0,
                'detection_ref' => null,
                'notes' => 'Detector error: ' . $e->getMessage(),
                'raw' => json_encode(['error' => $e->getMessage()]),
            ];
        }

        $verdict = $this->normalizeVerdict($result['verdict'] ?? 'uncertain');
        $confidence = max(0, min(1, (float) ($result['confidence'] ?? 0)));
        $detectedAmount = (int) ($result['detected_amount'] ?? 0);
        $detectionRef = $result['detection_ref'] ?? null;
        $notes = $result['notes'] ?? null;
        $raw = $result['raw'] ?? null;

        $stmt = $this->pdo->prepare("
            INSERT INTO cash_detection_logs (
                order_id,
                requested_by,
                detector_mode,
                verdict,
                confidence,
                detected_amount,
                detection_ref,
                notes,
                raw_response
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $orderId,
            $requestedBy,
            $mode,
            $verdict,
            $confidence,
            $detectedAmount,
            $detectionRef,
            $notes,
            $raw,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->getDetectionById($id) ?? [];
    }

    public function getLatestDetection(string $orderId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM cash_detection_logs
            WHERE order_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getLatestDetectionsByOrderIds(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $sql = "
            SELECT d.*
            FROM cash_detection_logs d
            INNER JOIN (
                SELECT order_id, MAX(id) AS max_id
                FROM cash_detection_logs
                WHERE order_id IN ($placeholders)
                GROUP BY order_id
            ) latest ON latest.max_id = d.id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($orderIds));

        $rows = $stmt->fetchAll();
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['order_id']] = $row;
        }
        return $mapped;
    }

    public function assertLatestIsGenuine(string $orderId, int $maxAgeMinutes = 30): array
    {
        $latest = $this->getLatestDetection($orderId);
        if (!$latest) {
            throw new Exception('Deteksi uang belum dilakukan.');
        }

        $ageSeconds = time() - strtotime($latest['created_at']);
        if ($ageSeconds > ($maxAgeMinutes * 60)) {
            throw new Exception('Hasil deteksi sudah kedaluwarsa. Silakan scan ulang.');
        }

        if ($latest['verdict'] === 'counterfeit') {
            throw new Exception('Uang terdeteksi palsu. Transaksi tidak dapat dilunasi.');
        }

        if ($latest['verdict'] !== 'genuine') {
            throw new Exception('Hasil deteksi belum valid untuk approval. Mohon deteksi ulang.');
        }

        return $latest;
    }

    private function getDetectionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cash_detection_logs WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
private function callRemoteDetector(string $url, string $orderId, string $imagePath): array
    {
        $timeout = (int) env('CASH_DETECTOR_TIMEOUT_SECONDS', 10);
        
        // Buat file siap dikirim
        $cfile = new CURLFile($imagePath, mime_content_type($imagePath), basename($imagePath));
        
        $postData = [
            'order_id' => $orderId,
            'file' => $cfile
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        
        $responseBody = (string) curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($statusCode >= 400 || $responseBody === '') {
            throw new Exception('Detector HTTP error/timeout.');
        }

        $decoded = json_decode($responseBody, true);
        // Bisa jadi API bawaan RipaNet atau raw output YOLO array
        $data = $decoded['data'] ?? $decoded;
        
        // Logika untuk menangkap respons YOLO secara langsung
        $yoloLabel = '';
        $yoloConf = 0;
        
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $bestObj = $data[0];
            foreach ($data as $obj) {
                $c = $obj['confidence'] ?? $obj['conf'] ?? 0;
                if ($c > $yoloConf) {
                    $yoloConf = $c;
                    $bestObj = $obj;
                }
            }
            $yoloLabel = $bestObj['name'] ?? $bestObj['class_name'] ?? $bestObj['class'] ?? $bestObj['label'] ?? '';
            $yoloConf = $bestObj['confidence'] ?? $bestObj['conf'] ?? 0;
        } elseif (is_array($data) && (isset($data['name']) || isset($data['label']))) {
            $yoloLabel = $data['name'] ?? $data['label'];
            $yoloConf = $data['confidence'] ?? $data['conf'] ?? 0;
        }
        
        if (!empty($yoloLabel)) {
            // Label example: DPalsu_100k, DAsli_50k, 100k, 50k
            $verdict = 'uncertain';
            if (stripos($yoloLabel, 'palsu') !== false || stripos($yoloLabel, 'fake') !== false) {
                $verdict = 'counterfeit';
            } elseif (stripos($yoloLabel, 'asli') !== false || stripos($yoloLabel, 'real') !== false) {
                $verdict = 'genuine';
            } else {
                $verdict = 'genuine'; // Jika hanya "100k" biasanya uang asli (perlu disesuaikan)
            }
            
            $detectedAmount = 0;
            // Parse amount from "100k", "50k", etc.
            if (preg_match('/(\d+)k/i', $yoloLabel, $matches)) {
                $detectedAmount = (int) $matches[1] * 1000;
            } elseif (preg_match('/(\d+)/', $yoloLabel, $matches)) {
                 $detectedAmount = (int) $matches[1];
                 if ($detectedAmount <= 100) {
                     $detectedAmount *= 1000;
                 }
            }
            
            return [
                'verdict' => $verdict,
                'confidence' => $yoloConf,
                'detected_amount' => $detectedAmount,
                'detection_ref' => null,
                'notes' => 'Parsed from YOLO: ' . $yoloLabel,
                'raw' => $responseBody,
            ];
        }
        
        // RipaNet standard format
        return [
            'verdict' => $data['verdict'] ?? 'uncertain',
            'confidence' => $data['confidence'] ?? 0,
            'detected_amount' => $data['amount'] ?? $data['detected_amount'] ?? 0,
            'detection_ref' => $data['detection_ref'] ?? null,
            'notes' => $data['notes'] ?? 'Remote detector response',
            'raw' => $responseBody,
        ];
    }
    private function runDummyDetector(string $orderId, int $amount): array
    {
        $dummyMode = strtolower((string) env('CASH_DETECTOR_DUMMY_MODE', 'random'));
        $randomRoll = random_int(1, 100);

        $verdict = 'uncertain';
        if ($dummyMode === 'genuine') {
            $verdict = 'genuine';
        } elseif ($dummyMode === 'counterfeit') {
            $verdict = 'counterfeit';
        } elseif ($dummyMode === 'uncertain') {
            $verdict = 'uncertain';
        } else {
            if ($randomRoll <= 70) {
                $verdict = 'genuine';
            } elseif ($randomRoll <= 90) {
                $verdict = 'uncertain';
            } else {
                $verdict = 'counterfeit';
            }
        }

        $confidence = 0.0;
        $detectedAmount = 0;
        if ($verdict === 'genuine') {
            $confidence = random_int(8600, 9900) / 10000;
            $bills = [2000, 5000, 10000, 20000, 50000, 100000];
            $validBills = array_filter($bills, fn($b) => $b >= $amount);
            if (empty($validBills)) {
                $detectedAmount = $amount;
            } else {
                $validBills = array_values($validBills);
                $detectedAmount = $validBills[array_rand($validBills)];
                // 70% chance exact/closest amount
                if (random_int(1, 100) <= 70) {
                    $detectedAmount = $validBills[0];
                }
            }
        } elseif ($verdict === 'counterfeit') {
            $confidence = random_int(7200, 9500) / 10000;
            $detectedAmount = $amount;
        } else {
            $confidence = random_int(4200, 6900) / 10000;
        }

        $ref = 'DUMMY-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $notes = sprintf(
            'Dummy detection for order %s amount %d with mode %s.',
            $orderId,
            $amount,
            $dummyMode
        );

        return [
            'verdict' => $verdict,
            'confidence' => $confidence,
            'detected_amount' => $detectedAmount,
            'detection_ref' => $ref,
            'notes' => $notes,
            'raw' => json_encode([
                'source' => 'dummy',
                'verdict' => $verdict,
                'confidence' => $confidence,
                'detected_amount' => $detectedAmount,
                'order_id' => $orderId,
                'amount' => $amount,
            ]),
        ];
    }

    private function normalizeVerdict(string $verdict): string
    {
        $value = strtolower(trim($verdict));
        if (in_array($value, ['genuine', 'real', 'asli'], true)) {
            return 'genuine';
        }
        if (in_array($value, ['counterfeit', 'fake', 'palsu'], true)) {
            return 'counterfeit';
        }
        if (in_array($value, ['error', 'failed'], true)) {
            return 'error';
        }
        return 'uncertain';
    }
}
