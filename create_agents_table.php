<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDB();
    $sql = "
    CREATE TABLE IF NOT EXISTS agents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_lengkap VARCHAR(150) NOT NULL,
        no_wa VARCHAR(20) NOT NULL,
        alamat TEXT NOT NULL,
        nik_ktp VARCHAR(50) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        is_agreed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
    ";
    $pdo->exec($sql);
    echo "Table 'agents' created successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
