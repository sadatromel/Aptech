<?php
require_once __DIR__ . '/../core/legacy_helpers.php';
try {
    $conn = get_db_connection();
    echo "DEPARTMENTS:\n";
    print_r($conn->query("SELECT * FROM departments LIMIT 5")->fetchAll(PDO::FETCH_ASSOC));
    echo "SECTIONS:\n";
    print_r($conn->query("SELECT * FROM sections LIMIT 5")->fetchAll(PDO::FETCH_ASSOC));
    echo "POSITIONS:\n";
    print_r($conn->query("SELECT * FROM positions LIMIT 5")->fetchAll(PDO::FETCH_ASSOC));
    echo "FACTORIES:\n";
    print_r($conn->query("SELECT * FROM factories LIMIT 5")->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
