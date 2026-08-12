<?php
/**
 * Database Migration Script for Extended HRMS module
 * Run this script to create table
 */

$config = require __DIR__ . '/../config/database.php';

try {
    // Create PDO connection
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Starting Extended HRMS database table migration...\n";

    $sqlFile = __DIR__ . '/migrations/create_hr_extended_table.sql';
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Could not read SQL file: $sqlFile");
    }

    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing statement...\n";
            $pdo->exec($statement);
        }
    }
    echo "Extended HRMS database table migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
