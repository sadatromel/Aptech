<?php

// Lightweight .env parser to support dynamic environments and database drivers without extra libraries
$envPath = dirname(__DIR__) . '/.env';
$env = [];

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            // Strip quotes if present
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || 
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }
            // Normalize null strings
            if (strtolower($val) === 'null') {
                $val = null;
            }
            $env[$key] = $val;
            $_ENV[$key] = $val;
            putenv("{$key}={$val}");
        }
    }
}

return [
    'driver' => getenv('DB_DRIVER') ?: ($env['DB_DRIVER'] ?? 'mysql'),
    'host' => getenv('DB_HOST') ?: ($env['DB_HOST'] ?? 'localhost'),
    'port' => getenv('DB_PORT') ?: ($env['DB_PORT'] ?? '3306'),
    'database' => getenv('DB_DATABASE') ?: ($env['DB_DATABASE'] ?? 'aptech_erp'),
    'username' => getenv('DB_USERNAME') ?: ($env['DB_USERNAME'] ?? 'root'),
    'password' => (getenv('DB_PASSWORD') !== false && getenv('DB_PASSWORD') !== '') ? getenv('DB_PASSWORD') : ($env['DB_PASSWORD'] ?? ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];

