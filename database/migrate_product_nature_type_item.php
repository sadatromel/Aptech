<?php
/**
 * Database Migration Script for Product Nature, Product Type, and Garments Item
 * Run this script to create tables and add permissions
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

    echo "Starting Product Nature, Type, and Item database tables migration...\n";

    // 1. Create tables and junctions
    $sqlFile = __DIR__ . '/migrations/create_product_nature_type_item_tables.sql';
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
    echo "Tables migration completed successfully!\n";

    // 2. Insert permissions
    echo "Starting Product Nature, Type, and Item permissions migration...\n";
    $permFile = __DIR__ . '/permissions/product_nature_type_item_permissions.sql';
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
