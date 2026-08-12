<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/paths.php';

$db = Core\Container::get(Core\Database::class);

try {
    $db->query('ALTER TABLE costing_styles ADD COLUMN style_id INT AFTER costing_id');
    echo "Successfully added style_id to costing_styles\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
