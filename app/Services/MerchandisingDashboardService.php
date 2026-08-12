<?php

declare(strict_types=1);

namespace App\Services;

use Core\Container;
use PDO;

class MerchandisingDashboardService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Container::get(PDO::class);
    }

    public function getDashboardMetrics(): array
    {
        // 1. Style stats
        $totalStyles = 0;
        $activeStyles = 0;
        try {
            $totalStyles = (int) $this->pdo->query("SELECT COUNT(*) FROM styles")->fetchColumn();
            $activeStyles = (int) $this->pdo->query("SELECT COUNT(*) FROM styles WHERE status IN ('draft', 'design', 'active', 'approved_for_production')")->fetchColumn();
        } catch (\Throwable $e) {
            $totalStyles = 0;
            $activeStyles = 0;
        }

        // 2. Production Orders stats
        $totalOrders = 0;
        $orderFobSum = 0.0;
        $orderQtySum = 0;
        try {
            $totalOrders = (int) $this->pdo->query("SELECT COUNT(*) FROM production_orders")->fetchColumn();
            $orderFobSum = (float) $this->pdo->query("SELECT COALESCE(SUM(COALESCE(quantity_ordered, quantity, 0) * COALESCE(unit_price, 0)), 0) FROM production_orders")->fetchColumn();
            $orderQtySum = (int) $this->pdo->query("SELECT COALESCE(SUM(COALESCE(quantity_ordered, quantity, 0)), 0) FROM production_orders")->fetchColumn();
        } catch (\Throwable $e) {
            $totalOrders = 0;
            $orderFobSum = 0.0;
            $orderQtySum = 0;
        }

        // 3. Sampling stats
        $totalSamples = 0;
        $approvedSamples = 0;
        try {
            $totalSamples = (int) $this->pdo->query("SELECT COUNT(*) FROM sampling")->fetchColumn();
            $approvedSamples = (int) $this->pdo->query("SELECT COUNT(*) FROM sampling WHERE status = 'approved' OR approval_status = 'approved'")->fetchColumn();
        } catch (\Throwable $e) {
            try {
                $totalSamples = (int) $this->pdo->query("SELECT COUNT(*) FROM sample_requisitions")->fetchColumn();
                $approvedSamples = (int) $this->pdo->query("SELECT COUNT(*) FROM sample_requisitions WHERE status = 'approved'")->fetchColumn();
            } catch (\Throwable $ex) {
                $totalSamples = 0;
                $approvedSamples = 0;
            }
        }
        $sampleApprovalRate = $totalSamples > 0 ? round(($approvedSamples / $totalSamples) * 100, 1) : 100;

        // 4. Pending Purchase Requests
        $pendingPrs = 0;
        try {
            $pendingPrs = (int) $this->pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status IN ('draft', 'pending', 'submitted')")->fetchColumn();
        } catch (\Throwable $e) {
            $pendingPrs = 0;
        }

        // 5. T&A Delay Alerts
        $tnaAlerts = 0;
        try {
            $tnaAlerts = (int) $this->pdo->query("
                SELECT COUNT(*) 
                FROM order_tna 
                WHERE status = 'delayed' OR (target_date < CURDATE() AND status NOT IN ('completed'))
            ")->fetchColumn();
        } catch (\Throwable $e) {
            $tnaAlerts = 0;
        }

        // 6. Recent Styles
        $recentStyles = [];
        try {
            $recentStyles = $this->pdo->query("
                SELECT s.style_id, s.style_code, s.style_name, s.season, s.price, s.status, s.created_at, b.buyer_name
                FROM styles s
                LEFT JOIN buyers b ON s.buyer_id = b.buyer_id
                ORDER BY s.style_id DESC
                LIMIT 5
            ")->fetchAll();
        } catch (\Throwable $e) {
            $recentStyles = [];
        }

        // 7. Recent Production Orders
        $recentOrders = [];
        try {
            $recentOrders = $this->pdo->query("
                SELECT po.po_id as id, 
                       COALESCE(NULLIF(po.po_number, ''), po.order_number, CONCAT('PO-', po.po_id)) as order_number, 
                       po.order_date, 
                       COALESCE(po.ex_factory_date, po.required_date, po.due_date) as delivery_date, 
                       COALESCE(po.quantity_ordered, po.quantity, 0) as quantity, 
                       (COALESCE(po.quantity_ordered, po.quantity, 0) * COALESCE(po.unit_price, 0)) as total_value, 
                       po.status, 
                       s.style_code, 
                       b.buyer_name
                FROM production_orders po
                LEFT JOIN styles s ON po.style_id = s.style_id
                LEFT JOIN buyers b ON po.buyer_id = b.buyer_id
                ORDER BY po.po_id DESC
                LIMIT 5
            ")->fetchAll();
        } catch (\Throwable $e) {
            $recentOrders = [];
        }

        // 8. T&A Urgent Warning Items
        $tnaWarnings = [];
        try {
            $tnaWarnings = $this->pdo->query("
                SELECT t.*, 
                       COALESCE(NULLIF(po.po_number, ''), po.order_number, CONCAT('PO-', po.po_id)) as order_number, 
                       s.style_code
                FROM order_tna t
                LEFT JOIN production_orders po ON t.po_id = po.po_id
                LEFT JOIN styles s ON t.style_id = s.style_id
                WHERE t.status = 'delayed' OR (t.target_date < CURDATE() AND t.status NOT IN ('completed'))
                ORDER BY t.target_date ASC
                LIMIT 5
            ")->fetchAll();
        } catch (\Throwable $e) {
            $tnaWarnings = [];
        }

        return [
            'total_styles'           => $totalStyles,
            'active_styles'          => $activeStyles,
            'total_orders'           => $totalOrders,
            'order_fob_sum'          => $orderFobSum,
            'order_qty_sum'          => $orderQtySum,
            'total_samples'          => $totalSamples,
            'approved_samples'       => $approvedSamples,
            'sample_approval_rate'   => $sampleApprovalRate,
            'pending_prs'            => $pendingPrs,
            'tna_alerts'             => $tnaAlerts,
            'recent_styles'          => $recentStyles,
            'recent_orders'          => $recentOrders,
            'tna_warnings'           => $tnaWarnings,
        ];
    }
}
