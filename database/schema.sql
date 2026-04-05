-- =====================================================
-- BILLING HOTSPOT SYSTEM - DATABASE SCHEMA
-- Midtrans QRIS + Mikrotik Integration
-- =====================================================

-- Buat database (jika belum ada)
CREATE DATABASE IF NOT EXISTS biling_hotspot 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE biling_hotspot;

-- =====================================================
-- TABEL: paket_voucher
-- Daftar paket voucher yang dijual
-- =====================================================
CREATE TABLE IF NOT EXISTS paket_voucher (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_paket VARCHAR(100) NOT NULL COMMENT 'Nama paket untuk display',
    harga INT NOT NULL COMMENT 'Harga dalam Rupiah',
    mikrotik_profile VARCHAR(100) NOT NULL COMMENT 'Nama profile di Mikrotik (harus PERSIS sama)',
    durasi_hari INT NOT NULL DEFAULT 1 COMMENT 'Durasi dalam hari (untuk generate panjang kode)',
    durasi_display VARCHAR(20) NOT NULL COMMENT 'Durasi untuk display (misal: 1 Hari, 7 Hari)',
    is_active TINYINT(1) DEFAULT 1 COMMENT '1=Aktif, 0=Nonaktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_active (is_active),
    INDEX idx_harga (harga)
) ENGINE=InnoDB;

-- =====================================================
-- TABEL: transaksi
-- Log semua transaksi pembayaran
-- =====================================================
CREATE TABLE IF NOT EXISTS transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL UNIQUE COMMENT 'ID unik transaksi kita (ORDER-xxx)',
    midtrans_transaction_id VARCHAR(100) COMMENT 'Transaction ID dari Midtrans',
    paket_id INT NOT NULL,
    amount INT NOT NULL COMMENT 'Jumlah pembayaran',
    payment_type VARCHAR(50) COMMENT 'Tipe pembayaran (qris, gopay, dll)',
    
    -- Status transaksi
    status ENUM('pending', 'settlement', 'expire', 'cancel', 'deny', 'refund') DEFAULT 'pending',
    
    -- Data voucher (terisi setelah PAID)
    mikrotik_user VARCHAR(20) COMMENT 'Username voucher',
    mikrotik_pass VARCHAR(20) COMMENT 'Password voucher',
    
    -- Data customer (opsional)
    customer_phone VARCHAR(20),
    
    -- QR Code
    qr_url TEXT COMMENT 'URL QR Code dari Midtrans',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expired_at DATETIME COMMENT 'Waktu kedaluwarsa transaksi',
    paid_at DATETIME COMMENT 'Waktu pembayaran berhasil',
    
    -- Audit trail
    raw_notification TEXT COMMENT 'Raw JSON dari Midtrans callback',
    ip_address VARCHAR(45) COMMENT 'IP address pembeli',
    
    -- Foreign key
    FOREIGN KEY (paket_id) REFERENCES paket_voucher(id),
    
    -- Indexes
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_order_status (order_id, status)
) ENGINE=InnoDB;

-- =====================================================
-- DATA SAMPLE: Paket Voucher
-- Sesuaikan dengan profile di Mikrotik Anda
-- =====================================================
INSERT INTO paket_voucher (nama_paket, harga, mikrotik_profile, durasi_hari, durasi_display) VALUES
('Harian 3 Jam', 2000, '3jam', 1, '3 Jam'),
('Harian 5 Ribu', 5000, '1hari', 1, '1 Hari'),
('Mingguan 25 Ribu', 25000, '7hari', 7, '7 Hari'),
('Bulanan 75 Ribu', 75000, '30hari', 30, '30 Hari');

-- =====================================================
-- TABEL: admins
-- Data akses kasir / admin POS
-- =====================================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'kasir') DEFAULT 'kasir',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- User Admin Default: admin / admin123
INSERT INTO admins (username, password_hash, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- =====================================================
-- TABEL: cash_detection_logs
-- Log hasil deteksi uang cash (dummy/remote detector)
-- =====================================================
CREATE TABLE IF NOT EXISTS cash_detection_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL,
    requested_by VARCHAR(50) NOT NULL DEFAULT 'system',
    detector_mode VARCHAR(20) NOT NULL DEFAULT 'dummy',
    verdict ENUM('genuine', 'counterfeit', 'uncertain', 'error') NOT NULL DEFAULT 'uncertain',
    confidence DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
    detection_ref VARCHAR(120),
    notes VARCHAR(255),
    raw_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_created (order_id, created_at),
    INDEX idx_verdict_created (verdict, created_at),
    CONSTRAINT fk_cash_detection_order
        FOREIGN KEY (order_id) REFERENCES transaksi(order_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;
