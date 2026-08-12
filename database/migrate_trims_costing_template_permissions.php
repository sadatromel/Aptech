<?php
/**
 * Database Migration Script for Trims Costing Template Permissions
 * Run this script to add the necessary permissions
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

    echo "Starting Trims Costing Template permissions migration...\n";

    // Read and execute the SQL file
    $sqlFile = __DIR__ . '/permissions/trims_costing_template_permissions.sql';
    $sql = file_get_contents($sqlFile);

    if ($sql === false) {
        throw new Exception("Could not read SQL file: $sqlFile");
    }

    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            $pdo->exec($statement);
        }
    }

    echo "Permissions migration completed successfully!\n";
    echo "Permissions added for Trims Costing Template module.\n";

} catch (Exception $e) {
    echo "Permissions migration failed: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and permissions.\n";
}
