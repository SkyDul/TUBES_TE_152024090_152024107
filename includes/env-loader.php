<?php
/**
 * Environment Variables Loader
 * 
 * Load .env file dari lokasi aman diluar public folder
 */

function loadEnv(string $path = null): void 
{
    // Default path berdasarkan environment
    if ($path === null) {
        // Windows (Laragon)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $path = 'E:/laragon/private/.env';
        } else {
            // Linux (aaPanel)
            $path = '/www/private/.env';
        }
    }
    
    if (!file_exists($path)) {
        // Fallback ke .env.example untuk development
        $fallback = dirname(__DIR__) . '/.env.example';
        if (file_exists($fallback)) {
            $path = $fallback;
        } else {
            throw new Exception("Environment file not found: $path");
        }
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip empty lines and comments
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Parse key=value
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set environment variable
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * Get environment variable with default value
 */
function env(string $key, $default = null) 
{
    $value = $_ENV[$key] ?? getenv($key);
    
    if ($value === false || $value === null) {
        return $default;
    }
    
    // Convert string booleans
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'null':
        case '(null)':
            return null;
    }
    
    return $value;
}
