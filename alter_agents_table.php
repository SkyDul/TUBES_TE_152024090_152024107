<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDB();
    $sql = "ALTER TABLE agents ADD COLUMN contract_file VARCHAR(255) NULL AFTER is_agreed;";
    $pdo->exec($sql);
    echo "Column 'contract_file' added successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
