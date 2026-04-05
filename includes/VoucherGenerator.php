<?php
/**
 * Voucher Code Generator
 * 
 * Generate kode voucher dengan panjang berdasarkan durasi:
 * - Harian (1 hari): 6 karakter
 * - Mingguan (7 hari): 7 karakter
 * - Bulanan (30 hari): 8 karakter
 */

class VoucherGenerator 
{
    // Karakter yang digunakan (campuran besar kecil, tanpa karakter ambigu: 0,O,I,1,l)
    private const CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    
    /**
     * Generate kode voucher berdasarkan durasi hari
     * 
     * @param int $durasiHari Durasi dalam hari
     * @return array ['user' => string, 'pass' => string, 'length' => int]
     */
    public static function generate(int $durasiHari): array 
    {
        // Tentukan panjang berdasarkan durasi
        if ($durasiHari <= 1) {
            $length = 6;      // Harian: 6 karakter
        } elseif ($durasiHari <= 7) {
            $length = 7;      // Mingguan: 7 karakter  
        } else {
            $length = 8;      // Bulanan: 8 karakter
        }
        
        $code = self::generateCode($length);
        
        return [
            'user' => $code,
            'pass' => $code,  // User = Pass untuk kemudahan
            'length' => $length
        ];
    }
    
    /**
     * Generate random code dengan panjang tertentu
     */
    private static function generateCode(int $length): string 
    {
        $code = '';
        $charsLength = strlen(self::CHARS);
        
        for ($i = 0; $i < $length; $i++) {
            $code .= self::CHARS[random_int(0, $charsLength - 1)];
        }
        
        return $code;
    }
    
    /**
     * Cek apakah kode sudah ada di database
     */
    public static function isUnique(PDO $pdo, string $code): bool 
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE mikrotik_user = ?");
        $stmt->execute([$code]);
        return $stmt->fetchColumn() == 0;
    }
    
    /**
     * Generate kode unik (auto-retry jika sudah ada)
     */
    public static function generateUnique(PDO $pdo, int $durasiHari, int $maxRetry = 10): array 
    {
        for ($i = 0; $i < $maxRetry; $i++) {
            $voucher = self::generate($durasiHari);
            
            if (self::isUnique($pdo, $voucher['user'])) {
                return $voucher;
            }
        }
        
        // Jika tetap tidak unik, tambah random suffix
        $voucher = self::generate($durasiHari);
        $voucher['user'] .= random_int(10, 99);
        $voucher['pass'] = $voucher['user'];
        
        return $voucher;
    }
}
