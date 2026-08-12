<?php
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4", $config['username'], $config['password']);

try {
    $pdo->exec("ALTER TABLE production_plans ADD COLUMN start_date DATE AFTER planned_date");
    echo "Added start_date column to production_plans.\n";
} catch (\Exception $e) {
    echo "start_date column check: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE production_plans ADD COLUMN end_date DATE AFTER start_date");
    echo "Added end_date column to production_plans.\n";
} catch (\Exception $e) {
    echo "end_date column check: " . $e->getMessage() . "\n";
}
