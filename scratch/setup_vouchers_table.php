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
$port = $_ENV['DB_PORT'] ?? '3306';
$dbname = $_ENV['DB_DATABASE'] ?? 'aptech_erp';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Creating vouchers table...\n";

    $sql = "CREATE TABLE IF NOT EXISTS `vouchers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `voucher_number` VARCHAR(50) NOT NULL UNIQUE,
        `voucher_type` VARCHAR(50) NOT NULL,
        `transaction_date` DATE NOT NULL,
        `debit_account_id` INT NOT NULL,
        `credit_account_id` INT NOT NULL,
        `amount` DECIMAL(15,2) NOT NULL,
        `description` TEXT,
        `status` VARCHAR(30) NOT NULL DEFAULT 'pending_approval',
        `posted_to_gl` TINYINT(1) DEFAULT 0,
        `created_by` INT DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table 'vouchers' ready.\n";

    // Fetch some valid account IDs from chart_of_accounts
    $accounts = $pdo->query("SELECT account_id, account_code FROM chart_of_accounts")->fetchAll(PDO::FETCH_KEY_PAIR);
    $codeMap = array_flip($accounts);

    $cashAcc = $codeMap['1010'] ?? reset($accounts);
    $bankAcc = $codeMap['1020'] ?? reset($accounts);
    $apAcc = $codeMap['2000'] ?? reset($accounts);
    $utilityAcc = $codeMap['6010'] ?? reset($accounts);
    $accruedAcc = $codeMap['2200'] ?? reset($accounts);
    $revenueAcc = $codeMap['4000'] ?? reset($accounts);

    // Check count
    $count = $pdo->query("SELECT COUNT(*) FROM vouchers")->fetchColumn();
    if ($count == 0) {
        $sampleVouchers = [
            ['PV-2026-0102', 'Payment', '2026-07-12', $utilityAcc, $bankAcc, 5400.00, 'Supplier transport bills release', 'pending_release', 0],
            ['JV-2026-0044', 'Journal', '2026-07-12', $utilityAcc, $accruedAcc, 28000.00, 'Factory electricity accrual adjustments', 'pending_approval', 0],
            ['RV-2026-0012', 'Receipt', '2026-07-10', $bankAcc, $revenueAcc, 45000.00, 'Advance payment export order #HM-9912', 'posted', 1],
            ['PV-2026-0098', 'Payment', '2026-07-08', $apAcc, $bankAcc, 12400.00, 'Textile supplier settlement', 'posted', 1]
        ];

        $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_number, voucher_type, transaction_date, debit_account_id, credit_account_id, amount, description, status, posted_to_gl, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");

        foreach ($sampleVouchers as $sv) {
            $stmt->execute($sv);
        }
        echo "Inserted initial sample vouchers into database.\n";
    }

    echo "Vouchers database setup completed successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
