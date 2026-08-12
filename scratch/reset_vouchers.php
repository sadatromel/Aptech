<?php
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l) {
        if (strpos(trim($l), '#')===0) continue;
        if (strpos($l, '=')!==false) {
            list($k,$v) = explode('=', $l, 2);
            $_ENV[trim($k)] = trim($v);
        }
    }
}

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbname = $_ENV['DB_DATABASE'] ?? 'aptech_erp';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

$pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass);
$pdo->exec("UPDATE vouchers SET status = 'pending_release', posted_to_gl = 0 WHERE id = 1");
$pdo->exec("UPDATE vouchers SET status = 'pending_approval', posted_to_gl = 0 WHERE id = 2");
echo "Reset pending vouchers state in DB.\n";
