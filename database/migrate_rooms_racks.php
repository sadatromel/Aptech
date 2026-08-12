<?php
/**
 * Database Migration Script for Store Rooms and Store Racks
 */

$config = require __DIR__ . '/../config/database.php';

try {
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Starting Room and Rack database migration...\n";

    // 1. Alter production_floors to add rack_id if it doesn't exist
    $result = $pdo->query("SHOW COLUMNS FROM production_floors LIKE 'rack_id'");
    if ($result->rowCount() === 0) {
        echo "Altering production_floors to add rack_id...\n";
        $pdo->exec("ALTER TABLE production_floors ADD COLUMN rack_id INT NULL AFTER factory_id");
        $pdo->exec("ALTER TABLE production_floors ADD INDEX idx_floor_rack_id (rack_id)");
        echo "Successfully altered production_floors.\n";
    } else {
        echo "rack_id column already exists in production_floors.\n";
    }

    // 2. Create store_rooms table
    echo "Creating store_rooms table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `store_rooms` (
          `room_id` int NOT NULL AUTO_INCREMENT,
          `room_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
          `room_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
          `floor_id` int NOT NULL,
          `description` text COLLATE utf8mb4_unicode_ci,
          `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`room_id`),
          UNIQUE KEY `unique_room` (`floor_id`,`room_code`),
          KEY `idx_floor_id` (`floor_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Successfully created store_rooms table.\n";

    // 3. Create store_racks table
    echo "Creating store_racks table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `store_racks` (
          `rack_id` int NOT NULL AUTO_INCREMENT,
          `rack_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
          `rack_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
          `room_id` int NOT NULL,
          `description` text COLLATE utf8mb4_unicode_ci,
          `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`rack_id`),
          UNIQUE KEY `unique_rack_room` (`room_id`,`rack_code`),
          KEY `idx_room_id` (`room_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Successfully created store_racks table.\n";

    echo "All migrations completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
