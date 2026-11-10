<?php
/**
 * Standalone Database Migration Script
 * Automatically verifies connection and imports backups/init.sql if tables are absent.
 */

$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: 3306;
$database = getenv('DB_DATABASE') ?: 'aptech_erp';
$username = getenv('DB_USERNAME') ?: 'root';
$password = (getenv('DB_PASSWORD') !== false && getenv('DB_PASSWORD') !== '') ? getenv('DB_PASSWORD') : 'aptech_secret_pass_2026';
$sqlFile = dirname(__DIR__) . '/backups/init.sql';

echo "[Migration] Target: $username@$host:$port/$database\n";

$maxRetries = 30;
$retry = 0;
$pdo = null;

while ($retry < $maxRetries) {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        break;
    } catch (PDOException $e) {
        $retry++;
        echo "[Migration] Waiting for MySQL server... ($retry/$maxRetries): " . $e->getMessage() . "\n";
        sleep(2);
    }
}

if (!$pdo) {
    echo "[Migration] ERROR: Could not connect to MySQL server after $maxRetries attempts.\n";
    exit(1);
}

// Ensure database exists
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$database`");

// Check if tables already exist
$check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
if ($check) {
    echo "[Migration] Database is already initialized ('users' table exists). Skipping.\n";
    exit(0);
}

if (!file_exists($sqlFile)) {
    echo "[Migration] ERROR: Backup file not found at $sqlFile\n";
    exit(1);
}

echo "[Migration] Initializing database from $sqlFile (" . round(filesize($sqlFile) / 1024, 1) . " KB)...\n";

$sql = file_get_contents($sqlFile);
if (str_starts_with($sql, "\xFF\xFE")) {
    $sql = mb_convert_encoding(substr($sql, 2), 'UTF-8', 'UTF-16LE');
} elseif (str_starts_with($sql, "\xEF\xBB\xBF")) {
    $sql = substr($sql, 3);
}

$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
$pdo->setAttribute(PDO::MYSQL_ATTR_MULTI_STATEMENTS, true);
$pdo->exec($sql);

$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "[Migration] SUCCESS! Successfully migrated " . count($tables) . " tables into $database.\n";
