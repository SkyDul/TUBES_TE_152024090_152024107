<?php
/**
 * Application Configuration
 * 
 * Load environment dan setup konfigurasi dasar
 */

// Load environment variables
require_once __DIR__ . '/../includes/env-loader.php';
loadEnv();

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting
if (env('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Return config array
return [
    'app' => [
        'env' => env('APP_ENV', 'production'),
        'debug' => env('APP_DEBUG', false),
        'url' => env('APP_URL', 'http://localhost'),
    ],
    
    'midtrans' => [
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'api_url' => env('MIDTRANS_IS_PRODUCTION', false) 
            ? 'https://api.midtrans.com' 
            : 'https://api.sandbox.midtrans.com',
    ],
    
    'mikrotik' => [
        'host' => env('MIKROTIK_HOST', '192.168.88.1'),
        'port' => (int) env('MIKROTIK_PORT', 8728),
        'user' => env('MIKROTIK_USER'),
        'pass' => env('MIKROTIK_PASS'),
    ],
    
    'transaction' => [
        'expire_minutes' => (int) env('TRANSACTION_EXPIRE_MINUTES', 15),
    ],

    'cash_detector' => [
        'url' => env('CASH_DETECTOR_URL', ''),
        'endpoint' => env('CASH_DETECTOR_ENDPOINT', '/detect'),
        'timeout_seconds' => (int) env('CASH_DETECTOR_TIMEOUT_SECONDS', 5),
        'remote_simulate' => env('CASH_DETECTOR_REMOTE_SIMULATE', true),
        'dummy_mode' => env('CASH_DETECTOR_DUMMY_MODE', 'random'),
        'max_age_minutes' => (int) env('CASH_DETECTOR_MAX_AGE_MINUTES', 30),
    ],
];
