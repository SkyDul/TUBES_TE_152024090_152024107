<?php
/**
 * Database Connection
 * 
 * PDO connection dengan error handling
 */

require_once __DIR__ . '/app.php';

function getDB(): PDO 
{
    static $pdo = null;
    
    if ($pdo === null) {
        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', '3306');
        $name = env('DB_NAME', 'biling_hotspot');
        $user = env('DB_USER', 'root');
        $pass = env('DB_PASS', 'tarikolot');
        
        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            if (env('APP_DEBUG', false)) {
                throw $e;
            }
            error_log("Database connection failed: " . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    
    return $pdo;
}
