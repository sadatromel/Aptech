<?php

require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Core\Container;
use Core\Database;

$db = Container::get(Database::class);

$columnsToAdd = [
    'costing_name' => "VARCHAR(255) NULL AFTER factory_id",
    'allocation_no' => "VARCHAR(100) NULL AFTER buyer_id",
    'sc_no' => "VARCHAR(100) NULL AFTER allocation_no",
    'job_no' => "VARCHAR(100) NULL AFTER sc_no",
    'local_commission_pct' => "DECIMAL(5,2) DEFAULT 0.00 AFTER costing_date",
    'foreign_commission_pct' => "DECIMAL(5,2) DEFAULT 0.00 AFTER local_commission_pct",
    'early_payment_cost_pct' => "DECIMAL(5,2) DEFAULT 0.00 AFTER foreign_commission_pct",
    'order_value' => "DECIMAL(14,2) DEFAULT 0.00 AFTER early_payment_cost_pct",
    'total_costing_value' => "DECIMAL(14,2) DEFAULT 0.00 AFTER order_value",
    'profit_amount' => "DECIMAL(14,2) DEFAULT 0.00 AFTER total_costing_value"
];

$existing = $db->query("DESCRIBE costing_sheets")->fetchAll(PDO::FETCH_COLUMN);

foreach ($columnsToAdd as $col => $definition) {
    if (!in_array($col, $existing, true)) {
        $db->query("ALTER TABLE costing_sheets ADD COLUMN {$col} {$definition}");
    }
}

echo "Costing sheets table updated successfully.\n";

