<?php

declare(strict_types=1);

namespace App\Services;

use Core\Container;
use PDO;

class CommercialDashboardService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Container::get(PDO::class);
    }

    public function getDashboardMetrics(): array
    {
        // 1. Invoices stats
        $invoicedRevenue = 0.0;
        $receivableBalance = 0.0;
        $totalInvoices = 0;
        try {
            $invoicedRevenue = (float) $this->pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM commercial_invoices")->fetchColumn();
            $receivableBalance = (float) $this->pdo->query("SELECT COALESCE(SUM(COALESCE(balance, (total_amount - COALESCE(paid_amount, 0)))), 0) FROM commercial_invoices WHERE status != 'paid'")->fetchColumn();
            $totalInvoices = (int) $this->pdo->query("SELECT COUNT(*) FROM commercial_invoices")->fetchColumn();
        } catch (\Throwable $e) {
            $invoicedRevenue = 0.0;
            $receivableBalance = 0.0;
            $totalInvoices = 0;
        }

        // 2. Sales Contracts stats
        $activeContractsSum = 0.0;
        $totalContracts = 0;
        try {
            $activeContractsSum = (float) $this->pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM sales_contracts WHERE status = 'active'")->fetchColumn();
            $totalContracts = (int) $this->pdo->query("SELECT COUNT(*) FROM sales_contracts")->fetchColumn();
        } catch (\Throwable $e) {
            $activeContractsSum = 0.0;
            $totalContracts = 0;
        }

        // 3. Letters of Credit stats
        $activeLcsSum = 0.0;
        $utilizedLcsSum = 0.0;
        $totalLcs = 0;
        try {
            $activeLcsSum = (float) $this->pdo->query("SELECT COALESCE(SUM(lc_amount), 0) FROM letters_of_credit WHERE status IN ('active', 'amendment')")->fetchColumn();
            $utilizedLcsSum = (float) $this->pdo->query("SELECT COALESCE(SUM(utilized_amount), 0) FROM letters_of_credit WHERE status IN ('active', 'amendment')")->fetchColumn();
            $totalLcs = (int) $this->pdo->query("SELECT COUNT(*) FROM letters_of_credit")->fetchColumn();
        } catch (\Throwable $e) {
            $activeLcsSum = 0.0;
            $utilizedLcsSum = 0.0;
            $totalLcs = 0;
        }

        // 4. Quotations Conversion stats
        $totalQuotations = 0;
        $acceptedQuotations = 0;
        try {
            $totalQuotations = (int) $this->pdo->query("SELECT COUNT(*) FROM quotations")->fetchColumn();
            $acceptedQuotations = (int) $this->pdo->query("SELECT COUNT(*) FROM quotations WHERE status IN ('accepted', 'converted')")->fetchColumn();
        } catch (\Throwable $e) {
            $totalQuotations = 0;
            $acceptedQuotations = 0;
        }
        $quotationConversionRate = $totalQuotations > 0 ? round(($acceptedQuotations / $totalQuotations) * 100, 1) : 100;

        // 5. Export Documents Count
        $totalExportDocs = 0;
        try {
            $totalExportDocs = (int) $this->pdo->query("SELECT COUNT(*) FROM export_documents")->fetchColumn();
        } catch (\Throwable $e) {
            $totalExportDocs = 0;
        }

        // 6. Recent Invoices
        $recentInvoices = [];
        try {
            $recentInvoices = $this->pdo->query("
                SELECT i.*, c.customer_name as buyer_name
                FROM commercial_invoices i
                LEFT JOIN customers c ON i.customer_id = c.customer_id
                ORDER BY i.id DESC
                LIMIT 5
            ")->fetchAll();
        } catch (\Throwable $e) {
            $recentInvoices = [];
        }

        // 7. Recent Sales Contracts
        $recentContracts = [];
        try {
            $recentContracts = $this->pdo->query("
                SELECT sc.*, b.buyer_name
                FROM sales_contracts sc
                LEFT JOIN buyers b ON sc.customer_id = b.buyer_id
                ORDER BY sc.so_id DESC
                LIMIT 5
            ")->fetchAll();
        } catch (\Throwable $e) {
            $recentContracts = [];
        }

        return [
            'invoiced_revenue'          => $invoicedRevenue,
            'receivable_balance'        => $receivableBalance,
            'total_invoices'            => $totalInvoices,
            'active_contracts_sum'      => $activeContractsSum,
            'total_contracts'           => $totalContracts,
            'active_lcs_sum'            => $activeLcsSum,
            'utilized_lcs_sum'          => $utilizedLcsSum,
            'total_lcs'                 => $totalLcs,
            'total_quotations'          => $totalQuotations,
            'accepted_quotations'       => $acceptedQuotations,
            'quotation_conversion_rate' => $quotationConversionRate,
            'total_export_docs'         => $totalExportDocs,
            'recent_invoices'           => $recentInvoices,
            'recent_contracts'          => $recentContracts,
        ];
    }
}
