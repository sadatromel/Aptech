<?php
/**
 * Database Migration Script for RMG Compliance & Maternity Benefits
 * Run this script to update database schema
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

    echo "Starting RMG Compliance database migration...\n";

    // Read and execute the SQL file
    $sqlFile = __DIR__ . '/migrations/create_rmg_compliance_tables.sql';
    $sql = file_get_contents($sqlFile);

    if ($sql === false) {
        throw new Exception("Could not read SQL file: $sqlFile");
    }

    // Split SQL into individual statements
    // Be careful with delimiter issues, split by semicolon but ignore inside quotes if any. 
    // In our case, the SQL statements are simple lines split by semicolon.
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing statement...\n";
            $pdo->exec($statement);
        }
    }

    echo "Migration completed successfully!\n";
    echo "Columns updated: employees.worker_grade\n";
    echo "Tables created: maternity_benefits\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and permissions.\n";
}
