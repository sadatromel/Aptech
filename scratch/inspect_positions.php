<?php
require_once __DIR__ . '/../core/legacy_helpers.php';
try {
    $conn = get_db_connection();
    
    $tables = ['positions'];
    foreach ($tables as $table) {
        echo "\nCOLUMNS FOR TABLE '$table':\n";
        $stmt = $conn->query("DESCRIBE `$table`");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo "  {$col['Field']} - {$col['Type']} - Null: {$col['Null']} - Key: {$col['Key']}\n";
        }
        
        $stmt = $conn->query("SELECT * FROM `$table` LIMIT 5");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        print_r($rows);
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
