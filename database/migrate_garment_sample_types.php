<?php
/**
 * Database Migration Script for Garment Sample Types
 * Run this script to create table and add permissions
 */

// Include the database configuration
$config = require __DIR__ . '/../config/database.php';

try {
    // Create PDO connection
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Starting Garments Sample Types database table migration...\n";

    // 1. Create table and insert default types
    $sqlFile = __DIR__ . '/migrations/create_garment_sample_types_table.sql';
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Could not read SQL file: $sqlFile");
    }

    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing table statement...\n";
            $pdo->exec($statement);
        }
    }
    echo "Table migration completed successfully!\n";

    // 2. Insert permissions
    echo "Starting Garments Sample Types permissions migration...\n";
    $permFile = __DIR__ . '/permissions/garment_sample_types_permissions.sql';
    $permSql = file_get_contents($permFile);
    if ($permSql === false) {
        throw new Exception("Could not read SQL file: $permFile");
    }

    $permStatements = array_filter(array_map('trim', explode(';', $permSql)));
    foreach ($permStatements as $statement) {
        if (!empty($statement)) {
            echo "Executing permission statement...\n";
            $pdo->exec($statement);
        }
    }
    echo "Permissions migration completed successfully!\n\n";
    echo "All migrations completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and permissions.\n";
}
