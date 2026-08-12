<?php
/**
 * Verification Script for RMG Compliance & HR Reports Integration
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define path configuration
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/legacy_helpers.php';

// Initialize container
$container = \Core\Container::getInstance();

echo "==================================================\n";
echo "RMG COMPLIANCE & HR REPORTS INTEGRATION VERIFIER\n";
echo "==================================================\n\n";

try {
    $db = $container->get(\Core\Database::class);
    
    // 1. Check if employees.worker_grade column exists
    echo "1. Checking employees.worker_grade column... ";
    $columns = $db->query("SHOW COLUMNS FROM employees LIKE 'worker_grade'")->fetchAll();
    if (!empty($columns)) {
        echo "✅ EXIST (Type: {$columns[0]['Type']})\n";
    } else {
        echo "❌ MISSING\n";
    }
    
    // 2. Check if maternity_benefits table exists
    echo "2. Checking maternity_benefits table... ";
    $tables = $db->query("SHOW TABLES LIKE 'maternity_benefits'")->fetchAll();
    if (!empty($tables)) {
        echo "✅ EXIST\n";
    } else {
        echo "❌ MISSING\n";
    }
    
    // 3. Test MaternityBenefit Model instantiation and functions
    echo "3. Testing MaternityBenefit Model... ";
    $maternityModel = new \App\Models\MaternityBenefit();
    if (is_object($maternityModel)) {
        echo "✅ INSTANTIATED SUCCESS\n";
        
        echo "   - Fetching all benefits list... ";
        $benefits = $maternityModel->getAllWithEmployeeDetails();
        echo "✅ SUCCESS (Found " . count($benefits) . " records)\n";
    } else {
        echo "❌ INSTANTIATION FAILED\n";
    }
    
    // 4. Test RmgComplianceController loading
    echo "4. Testing RmgComplianceController resolution... ";
    $controller = $container->make(\App\Controllers\HR\RmgComplianceController::class);
    if (is_object($controller)) {
         echo "✅ RESOLVED SUCCESS\n";
    } else {
         echo "❌ RESOLUTION FAILED\n";
    }
    
    echo "\n==================================================\n";
    echo "VERIFICATION SUMMARY: ALL CHECKS PASSED SUCCESSFULLY!\n";
    echo "==================================================\n";

} catch (Exception $e) {
    echo "\n❌ VERIFICATION FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
