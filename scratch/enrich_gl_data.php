<?php
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l) {
        if (strpos(trim($l), '#')===0) continue;
        if (strpos($l, '=')!==false) {
            list($k,$v) = explode('=', $l, 2);
            $_ENV[trim($k)] = trim($v);
        }
    }
}

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbname = $_ENV['DB_DATABASE'] ?? 'aptech_erp';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Enriching Multi-Month General Ledger Data...\n";

    // Fetch accounts map
    $accounts = $pdo->query("SELECT account_code, account_id FROM chart_of_accounts")->fetchAll(PDO::FETCH_KEY_PAIR);

    $cashAcc = $accounts['1010'] ?? array_values($accounts)[0];
    $bankAcc = $accounts['1020'] ?? array_values($accounts)[1];
    $arAcc = $accounts['1100'] ?? array_values($accounts)[2];
    $apAcc = $accounts['2000'] ?? array_values($accounts)[3];
    $capitalAcc = $accounts['3000'] ?? array_values($accounts)[4];
    $revExportAcc = $accounts['4000'] ?? array_values($accounts)[5];
    $costFabricAcc = $accounts['5000'] ?? array_values($accounts)[6];
    $costTrimsAcc = $accounts['5010'] ?? array_values($accounts)[7];
    $costLaborAcc = $accounts['5100'] ?? array_values($accounts)[8];
    $expUtilityAcc = $accounts['6010'] ?? array_values($accounts)[9];
    $expSalariesAcc = $accounts['7000'] ?? array_values($accounts)[10];

    // Additional multi-month balanced double entries
    $entries = [
        // March 2026
        ['2026-03-01', $bankAcc, 500000.00, 0.00, 'Capital Injection by Shareholders', 'capital', 101],
        ['2026-03-01', $capitalAcc, 0.00, 500000.00, 'Capital Injection by Shareholders', 'capital', 101],

        ['2026-03-15', $revExportAcc, 0.00, 320000.00, 'Export Shipment FOB Invoice - Levi Strauss', 'invoice', 102],
        ['2026-03-15', $bankAcc, 320000.00, 0.00, 'Export Shipment FOB Invoice - Levi Strauss', 'invoice', 102],

        ['2026-03-25', $costFabricAcc, 180000.00, 0.00, 'Bulk Denim Fabric Purchase', 'bill', 103],
        ['2026-03-25', $apAcc, 0.00, 180000.00, 'Bulk Denim Fabric Purchase', 'bill', 103],

        // April 2026
        ['2026-04-05', $expUtilityAcc, 35000.00, 0.00, 'Factory Premises Monthly Lease & Utilities', 'payment', 104],
        ['2026-04-05', $bankAcc, 0.00, 35000.00, 'Factory Premises Monthly Lease & Utilities', 'payment', 104],

        ['2026-04-12', $revExportAcc, 0.00, 410000.00, 'Export Order Invoice - Target Stores', 'invoice', 105],
        ['2026-04-12', $bankAcc, 410000.00, 0.00, 'Export Order Invoice - Target Stores', 'invoice', 105],

        ['2026-04-20', $costLaborAcc, 95000.00, 0.00, 'Sewing & Finishing Operators Monthly Wages', 'payment', 106],
        ['2026-04-20', $bankAcc, 0.00, 95000.00, 'Sewing & Finishing Operators Monthly Wages', 'payment', 106],

        // May 2026
        ['2026-05-10', $expSalariesAcc, 45000.00, 0.00, 'Executive & Admin Staff Salary May', 'payment', 107],
        ['2026-05-10', $bankAcc, 0.00, 45000.00, 'Executive & Admin Staff Salary May', 'payment', 107],

        ['2026-05-18', $revExportAcc, 0.00, 520000.00, 'Export Order Shipment - Next Retail', 'invoice', 108],
        ['2026-05-18', $bankAcc, 520000.00, 0.00, 'Export Order Shipment - Next Retail', 'invoice', 108],

        ['2026-05-28', $expUtilityAcc, 28000.00, 0.00, 'Gas & Power Utility Bills May', 'payment', 109],
        ['2026-05-28', $bankAcc, 0.00, 28000.00, 'Gas & Power Utility Bills May', 'payment', 109],

        // June 2026
        ['2026-06-02', $costTrimsAcc, 42000.00, 0.00, 'Zippers, Labels & Buttons Procurement', 'bill', 110],
        ['2026-06-02', $apAcc, 0.00, 42000.00, 'Zippers, Labels & Buttons Procurement', 'bill', 110],

        ['2026-06-15', $revExportAcc, 0.00, 480000.00, 'Knitwear FOB Export Realization - PVH', 'invoice', 111],
        ['2026-06-15', $bankAcc, 480000.00, 0.00, 'Knitwear FOB Export Realization - PVH', 'invoice', 111]
    ];

    $stmt = $pdo->prepare("INSERT INTO general_ledger (transaction_date, account_id, debit_amount, credit_amount, description, reference_type, reference_id, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");

    foreach ($entries as $e) {
        $stmt->execute($e);
    }

    echo "Successfully enriched General Ledger with multi-month transactions.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
