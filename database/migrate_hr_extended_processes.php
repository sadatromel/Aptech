<?php
/**
 * Migration Script for HR Extended Processes Tables and Permissions
 */

$config = require __DIR__ . '/../config/database.php';

try {
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Starting HR Extended Processes table migration...\n";
    $sqlFile = __DIR__ . '/migrations/create_hr_extended_processes_tables.sql';
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Could not read SQL file: $sqlFile");
    }

    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    echo "HR Extended Processes tables migrated successfully!\n";

    echo "Migrating HR Extended Processes permissions...\n";
    $permFile = __DIR__ . '/permissions/hr_extended_processes_permissions.sql';
    $permSql = file_get_contents($permFile);
    if ($permSql === false) {
        throw new Exception("Could not read SQL file: $permFile");
    }

    $permStatements = array_filter(array_map('trim', explode(';', $permSql)));
    foreach ($permStatements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    echo "Permissions migrated successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
