<?php
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['database']}", $config['username'], $config['password']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Running HR Attendance Operations Database Migrations...\n";

$sql = file_get_contents(__DIR__ . '/migrations/create_hr_attendance_operations_tables.sql');
$pdo->exec($sql);
echo "✔ Tables created / verified successfully.\n";

$permSql = file_get_contents(__DIR__ . '/permissions/hr_attendance_operations_permissions.sql');
$pdo->exec($permSql);
echo "✔ Permissions registered successfully.\n";

echo "🎉 Migration Complete!\n";
