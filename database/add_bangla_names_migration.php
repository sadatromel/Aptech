<?php
/**
 * Migration Script to add Bangla Name columns to districts and sub_districts tables
 */

$config = require __DIR__ . '/../config/database.php';

try {
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Adding district_name_bn to districts table...\n";
    try {
        $pdo->exec("ALTER TABLE `districts` ADD COLUMN `district_name_bn` VARCHAR(150) NULL AFTER `district_name`");
        echo "Added district_name_bn column successfully!\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            echo "Column district_name_bn already exists.\n";
        } else {
            throw $e;
        }
    }

    echo "Adding sub_district_name_bn to sub_districts table...\n";
    try {
        $pdo->exec("ALTER TABLE `sub_districts` ADD COLUMN `sub_district_name_bn` VARCHAR(150) NULL AFTER `sub_district_name`");
        echo "Added sub_district_name_bn column successfully!\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            echo "Column sub_district_name_bn already exists.\n";
        } else {
            throw $e;
        }
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
