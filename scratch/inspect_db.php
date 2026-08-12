<?php
require_once __DIR__ . '/../core/legacy_helpers.php';
try {
    $conn = get_db_connection();
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "TABLES IN DATABASE:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
