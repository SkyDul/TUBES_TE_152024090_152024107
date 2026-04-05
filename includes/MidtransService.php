<?php
/**
 * Midtrans Payment Service
 * 
 * Wrapper untuk Midtrans Core API (QRIS)
 */

class MidtransService 
{
    private string $serverKey;
    private string $clientKey;
    private bool $isProduction;
    private string $apiUrl;
    private string $snapUrl;
    
    public function __construct() 
    {
        $this->serverKey = getenv('MIDTRANS_SERVER_KEY') ?: '';
        $this->clientKey = getenv('MIDTRANS_CLIENT_KEY') ?: '';
        $this->isProduction = filter_var(getenv('MIDTRANS_IS_PRODUCTION'), FILTER_VALIDATE_BOOLEAN);
        
        $this->apiUrl = $this->isProduction 
            ? 'https://api.midtrans.com' 
            : 'https://api.sandbox.midtrans.com';
            
        $this->snapUrl = $this->isProduction 
            ? 'https://app.midtrans.com' 
            : 'https://app.sandbox.midtrans.com';
        
        if (empty($this->serverKey)) {
            throw new Exception('MIDTRANS_SERVER_KEY not configured');
        }
    }
    
    /**
     * Create QRIS transaction
     * 
     * @param string $orderId Order ID unik
     * @param int $amount Jumlah dalam Rupiah
     * @param array $itemDetails Detail item
     * @return array Response dari Midtrans
     */
    public function createQRISTransaction(string $orderId, int $amount, array $itemDetails = []): array 
    {
        $payload = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount
            ],
            'qris' => [
                'acquirer' => 'gopay'
            ]
        ];
        
        if (!empty($itemDetails)) {
            $payload['item_details'] = $itemDetails;
        }
        
        return $this->request('/v2/charge', $payload);
    }
    
    /**
     * Create Snap transaction (Provides full payment options: Debit, VA, E-Wallet)
     * 
     * @param string $orderId Order ID unik
     * @param int $amount Jumlah dalam Rupiah
     * @param array $itemDetails Detail item
     * @return array Response dari Midtrans
     */
    public function createSnapTransaction(string $orderId, int $amount, array $itemDetails = []): array 
    {
        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount
            ]
        ];
        
        if (!empty($itemDetails)) {
            $payload['item_details'] = $itemDetails;
        }
        
        return $this->request('/snap/v1/transactions', $payload, 'POST', true);
    }
    
    /**
     * Get transaction status
     */
    public function getStatus(string $orderId): array 
    {
        return $this->request("/v2/{$orderId}/status", null, 'GET');
    }
    
    /**
     * Cancel transaction
     */
    public function cancel(string $orderId): array 
    {
        return $this->request("/v2/{$orderId}/cancel", null, 'POST');
    }
    
    /**
     * Verify callback signature
     * 
     * @param string $orderId
     * @param string $statusCode
     * @param string $grossAmount
     * @param string $signatureKey dari callback
     * @return bool
     */
    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signatureKey): bool 
    {
        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);
        return hash_equals($expectedSignature, $signatureKey);
    }
    
    /**
     * Make HTTP request ke Midtrans API
     */
    private function request(string $endpoint, ?array $data = null, string $method = 'POST', bool $useSnap = false): array 
    {
        $url = ($useSnap ? $this->snapUrl : $this->apiUrl) . $endpoint;
        
        $ch = curl_init($url);
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->serverKey . ':')
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            // Disable SSL verification for development (Laragon issue)
            // TODO: Enable in production!
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        
        if ($method === 'POST' && $data !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            error_log("Midtrans API Error: $error");
            return ['error' => $error, 'http_code' => $httpCode];
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid JSON response', 'raw' => $response];
        }
        
        return $result;
    }
    
    /**
     * Get QR URL from charge response
     */
    public static function extractQRUrl(array $response): ?string 
    {
        if (!isset($response['actions'])) {
            return null;
        }
        
        foreach ($response['actions'] as $action) {
            if ($action['name'] === 'generate-qr-code') {
                return $action['url'];
            }
        }
        
        return null;
    }
    
    /**
     * Get client key untuk frontend
     */
    public function getClientKey(): string 
    {
        return $this->clientKey;
    }
}
