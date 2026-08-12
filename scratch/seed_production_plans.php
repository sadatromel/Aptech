<?php
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4", $config['username'], $config['password']);

// Fetch active line IDs
$lines = $pdo->query("SELECT production_line_id FROM production_lines LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
$styles = $pdo->query("SELECT style_id FROM styles LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);

if (empty($lines)) {
    echo "No lines found. Inserting default sewing lines...\n";
    $pdo->exec("INSERT INTO production_lines (line_name, line_code, line_type, capacity_per_day, status) VALUES 
        ('Line 01 - Heavy Denim', 'L-01', 'Denim', 1200, 'active'),
        ('Line 02 - Woven Shirts', 'L-02', 'Shirt', 1000, 'active'),
        ('Line 03 - Knitwear', 'L-03', 'Knit', 1500, 'active'),
        ('Line 04 - Jackets', 'L-04', 'Jacket', 800, 'active'),
        ('Line 05 - T-Shirts', 'L-05', 'Basic', 2000, 'active')
    ");
    $lines = $pdo->query("SELECT production_line_id FROM production_lines LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
}

if (empty($styles)) {
    echo "No styles found. Inserting default styles...\n";
    $pdo->exec("INSERT INTO styles (style_code, style_name, status) VALUES 
        ('ST-DENIM-01', 'Slim Fit Denim Jeans', 'active'),
        ('ST-SHIRT-02', 'Casual Woven Shirt', 'active'),
        ('ST-KNIT-03', 'Crew Neck T-Shirt', 'active'),
        ('ST-JACKET-04', 'Puffer Winter Jacket', 'active')
    ");
    $styles = $pdo->query("SELECT style_id FROM styles LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
}

// Check if plans exist
$count = $pdo->query("SELECT COUNT(*) FROM production_plans")->fetchColumn();

if ($count == 0) {
    echo "Seeding initial production plans...\n";
    $stmt = $pdo->prepare("INSERT INTO production_plans (plan_number, line_id, style_id, planned_date, start_date, end_date, quantity, completed_quantity, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

    $l1 = $lines[0] ?? 1;
    $l2 = $lines[1] ?? 2;
    $l3 = $lines[2] ?? 3;
    $s1 = $styles[0] ?? 1;
    $s2 = $styles[1] ?? 2;
    $s3 = $styles[2] ?? 3;

    $stmt->execute(['PLN-20260801-01', $l1, $s1, '2026-08-01', '2026-08-01', '2026-08-07', 5000, 0, 'in_progress']);
    $stmt->execute(['PLN-20260801-02', $l2, $s2, '2026-08-03', '2026-08-03', '2026-08-10', 4200, 0, 'planned']);
    $stmt->execute(['PLN-20260801-03', $l3, $s3, '2026-08-05', '2026-08-05', '2026-08-14', 8000, 0, 'planned']);

    echo "Seeded 3 initial production plans successfully!\n";
} else {
    echo "production_plans already contains $count rows.\n";
}
