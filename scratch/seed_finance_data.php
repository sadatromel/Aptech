<?php
require_once __DIR__ . '/../vendor/autoload.php';

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbname = $_ENV['DB_DATABASE'] ?? 'aptech_erp';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Seeding Finance Data...\n";

    // 1. Seed Bank Accounts if empty
    $bankCount = $pdo->query("SELECT COUNT(*) FROM bank_information")->fetchColumn();
    if ($bankCount == 0) {
        $banks = [
            ['Standard Chartered Bank', '1020304050601', 'Gulshan-2 Branch, Dhaka', 'SCBLBDDX'],
            ['Islami Bank Bangladesh Ltd', '2050123456789', 'Motijheel Corporate Branch', 'IBBLBDDH'],
            ['Eastern Bank PLC', '1011040892301', 'Uttara Branch, Dhaka', 'EBLNDDH'],
            ['HSBC Bangladesh', '001098234001', 'Management Office, Gulshan', 'HSBCBDDH']
        ];
        $stmt = $pdo->prepare("INSERT INTO bank_information (bank_name, account_number, branch_name, swift_code, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'Active', NOW(), NOW())");
        foreach ($banks as $b) {
            $stmt->execute($b);
        }
        echo "Seeded 4 Bank Accounts.\n";
    }

    // 2. Seed Cost Centers if empty
    $ccCount = $pdo->query("SELECT COUNT(*) FROM cost_centers")->fetchColumn();
    if ($ccCount == 0) {
        $costCenters = [
            ['CC-SEW', 'Sewing Production Floor 1', 'active'],
            ['CC-CUT', 'Cutting Section HQ', 'active'],
            ['CC-ADM', 'Head Office Administration', 'active'],
            ['CC-WSH', 'Washing Plant Facility', 'active']
        ];
        $stmt = $pdo->prepare("INSERT INTO cost_centers (cost_center_code, cost_center_name, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        foreach ($costCenters as $cc) {
            $stmt->execute($cc);
        }
        echo "Seeded Cost Centers.\n";
    }

    // 3. Get Account IDs for double entries
    $accounts = $pdo->query("SELECT account_id, account_code FROM chart_of_accounts")->fetchAll(PDO::FETCH_KEY_PAIR);
    // Reverse map code -> id
    $codeMap = array_flip($accounts);

    // 4. Seed General Ledger entries if empty
    $glCount = $pdo->query("SELECT COUNT(*) FROM general_ledger")->fetchColumn();
    if ($glCount == 0) {
        $sampleTransactions = [
            // Date, Debit Code, Credit Code, Amount, Desc, RefType, RefId, CC
            ['2026-07-01', '1010', '4000', 150000.00, 'Export Order Advance Payment - H&M', 'receipt', 1001, 'CC-ADM'],
            ['2026-07-03', '5000', '2000', 45000.00, 'Fabric Purchase Invoice - Apex Textiles', 'invoice', 1002, 'CC-CUT'],
            ['2026-07-05', '5100', '1020', 32000.00, 'Disbursed Sewing Operators Weekly Wage', 'payment', 1003, 'CC-SEW'],
            ['2026-07-08', '6010', '1020', 12500.00, 'Factory Electricity Bill Payment', 'payment', 1004, 'CC-WSH'],
            ['2026-07-10', '1020', '1100', 85000.00, 'Export Invoice Realization - Zara', 'receipt', 1005, 'CC-ADM'],
            ['2026-07-12', '7000', '1010', 18000.00, 'Staff Salary Partial Disbursement', 'payment', 1006, 'CC-ADM'],
            ['2026-07-15', '5010', '2000', 14200.00, 'Trims & Buttons Booking Invoice', 'invoice', 1007, 'CC-SEW'],
            ['2026-07-20', '1010', '4100', 22000.00, 'Scrap & Waste Fabric Sales Receipt', 'receipt', 1008, 'CC-CUT']
        ];

        $stmt = $pdo->prepare("INSERT INTO general_ledger (transaction_date, account_id, debit_amount, credit_amount, reference_type, reference_id, description, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");

        foreach ($sampleTransactions as $t) {
            list($date, $drCode, $crCode, $amount, $desc, $refType, $refId, $ccCode) = $t;
            
            $drAccId = $codeMap[$drCode] ?? 1;
            $crAccId = $codeMap[$crCode] ?? 2;

            // Debit line
            $stmt->execute([$date, $drAccId, $amount, 0.00, $refType, $refId, $desc]);
            // Credit line
            $stmt->execute([$date, $crAccId, 0.00, $amount, $refType, $refId, $desc]);
        }
        echo "Seeded General Ledger Transactions.\n";
    }

    // 5. Seed Accounts Receivable if empty
    $arCount = $pdo->query("SELECT COUNT(*) FROM accounts_receivable")->fetchColumn();
    if ($arCount == 0) {
        $buyerId = $pdo->query("SELECT buyer_id FROM buyers LIMIT 1")->fetchColumn() ?: 1;
        $arData = [
            [$buyerId, 'INV-2026-001', '2026-07-01', '2026-07-31', 125000.00, 50000.00, 'partial'],
            [$buyerId, 'INV-2026-002', '2026-07-10', '2026-08-10', 84000.00, 0.00, 'pending'],
            [$buyerId, 'INV-2026-003', '2026-06-15', '2026-07-15', 62000.00, 62000.00, 'paid']
        ];
        $stmt = $pdo->prepare("INSERT INTO accounts_receivable (buyer_id, invoice_number, invoice_date, due_date, amount, total_amount, paid_amount, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        foreach ($arData as $ar) {
            $stmt->execute([$ar[0], $ar[1], $ar[2], $ar[3], $ar[4], $ar[4], $ar[5], $ar[6]]);
        }
        echo "Seeded Accounts Receivable.\n";
    }

    // 6. Seed Accounts Payable if empty
    $apCount = $pdo->query("SELECT COUNT(*) FROM accounts_payable")->fetchColumn();
    if ($apCount == 0) {
        $vendorId = $pdo->query("SELECT vendor_id FROM vendors LIMIT 1")->fetchColumn() ?: 1;
        $apData = [
            [$vendorId, 'BIL-YRN-9812', '2026-07-02', '2026-08-02', 64000.00, 0.00, 'pending'],
            [$vendorId, 'BIL-ACC-1102', '2026-06-20', '2026-07-20', 18500.00, 18500.00, 'paid'],
            [$vendorId, 'BIL-FAB-3341', '2026-07-12', '2026-08-12', 42000.00, 20000.00, 'partial']
        ];
        $stmt = $pdo->prepare("INSERT INTO accounts_payable (vendor_id, bill_number, bill_date, due_date, total_amount, paid_amount, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        foreach ($apData as $ap) {
            $stmt->execute([$ap[0], $ap[1], $ap[2], $ap[3], $ap[4], $ap[5], $ap[6]]);
        }
        echo "Seeded Accounts Payable.\n";
    }

    echo "Finance Seeding Completed Successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
