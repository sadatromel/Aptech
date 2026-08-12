<?php

declare(strict_types=1);

namespace App\Services;

use Core\Container;
use PDO;

class ProductionDashboardService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Container::get(PDO::class);
    }

    public function getMetrics(): array
    {
        // 1. Production Orders Stats
        $activeOrders = 0;
        $totalTargetQty = 0;
        try {
            $activeOrders = (int) $this->pdo->query("SELECT COUNT(*) FROM production_orders WHERE status NOT IN ('completed', 'cancelled')")->fetchColumn();
            $totalTargetQty = (int) $this->pdo->query("SELECT COALESCE(SUM(COALESCE(quantity_ordered, quantity, 0)), 0) FROM production_orders WHERE status NOT IN ('cancelled')")->fetchColumn();
        } catch (\Throwable $e) {
            $activeOrders = 0;
            $totalTargetQty = 0;
        }

        // 2. Cutting Stats
        $totalCutQty = 0;
        $activeCuttingPlans = 0;
        try {
            $totalCutQty = (int) $this->pdo->query("SELECT COALESCE(SUM(total_quantity), 0) FROM cutting_plans")->fetchColumn();
            $activeCuttingPlans = (int) $this->pdo->query("SELECT COUNT(*) FROM cutting_plans WHERE status != 'completed'")->fetchColumn();
        } catch (\Throwable $e) {
            $totalCutQty = 0;
            $activeCuttingPlans = 0;
        }

        // 3. Sewing Line Stats
        $activeLines = 0;
        $completedSewnQty = 0;
        try {
            $activeLines = (int) $this->pdo->query("SELECT COUNT(*) FROM production_lines WHERE status = 'active'")->fetchColumn();
            $completedSewnQty = (int) $this->pdo->query("SELECT COALESCE(SUM(completed_quantity), 0) FROM production_plans")->fetchColumn();
        } catch (\Throwable $e) {
            $activeLines = 0;
            $completedSewnQty = 0;
        }

        // 4. Finishing Stats
        $finishedQty = 0;
        try {
            $finishedQty = (int) $this->pdo->query("SELECT COALESCE(SUM(qty_received), 0) FROM finishing_receive")->fetchColumn();
        } catch (\Throwable $e) {
            $finishedQty = 0;
        }

        // 5. Washing Plant Batches
        $washBatchesCount = 0;
        try {
            $washBatchesCount = (int) $this->pdo->query("SELECT COUNT(*) FROM wash_batches")->fetchColumn();
        } catch (\Throwable $e) {
            $washBatchesCount = 0;
        }

        // 6. Packing Stats
        $packedQty = 0;
        try {
            $packedQty = (int) $this->pdo->query("SELECT COALESCE(SUM(actual_quantity), 0) FROM packing_plans")->fetchColumn();
        } catch (\Throwable $e) {
            $packedQty = 0;
        }

        return [
            'active_orders'       => $activeOrders,
            'total_target_qty'    => $totalTargetQty,
            'total_cut_qty'       => $totalCutQty,
            'active_cutting_plans'=> $activeCuttingPlans,
            'active_lines'        => $activeLines,
            'completed_sewn_qty'  => $completedSewnQty,
            'finished_qty'        => $finishedQty,
            'wash_batches_count'  => $washBatchesCount,
            'packed_qty'          => $packedQty,
        ];
    }
}
