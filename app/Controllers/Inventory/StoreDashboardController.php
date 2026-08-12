<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Models\Item;
use App\Models\Inventory\Stock;

class StoreDashboardController extends BaseController
{
    public function index()
    {
        $this->requireAuth();
        
        // Fetch dashboard statistics using custom SQL queries to ensure exact matches
        $totalItems = $this->db->query("SELECT COUNT(*) FROM items WHERE status = 'active'")->fetchColumn();
        
        $totalValue = $this->db->query("
            SELECT SUM(COALESCE(s.quantity_on_hand, 0) * COALESCE(i.unit_price, 0)) 
            FROM stock s 
            JOIN items i ON s.item_id = i.item_id
        ")->fetchColumn() ?? 0;
        
        $lowStockCount = $this->db->query("
            SELECT COUNT(*) 
            FROM items 
            WHERE status = 'active' AND current_stock <= min_stock_level
        ")->fetchColumn();
        
        // Pending approvals across sub-modules
        $pendingRequisitions = $this->db->query("SELECT COUNT(*) FROM store_requisitions WHERE status = 'pending'")->fetchColumn() ?? 0;
        $pendingAdjustments = $this->db->query("SELECT COUNT(*) FROM stock_adjustments WHERE status = 'pending'")->fetchColumn() ?? 0;
        $pendingTransfers = $this->db->query("SELECT COUNT(*) FROM stock_transfer_requests WHERE status = 'pending'")->fetchColumn() ?? 0;
        $pendingGatePasses = $this->db->query("SELECT COUNT(*) FROM gate_passes WHERE approve_by IS NULL OR approve_by = 0")->fetchColumn() ?? 0;
        
        $totalPendingApprovals = $pendingRequisitions + $pendingAdjustments + $pendingTransfers + $pendingGatePasses;
        
        // Today's transaction count
        $todayTransactions = $this->db->query("
            SELECT COUNT(*) 
            FROM stock_movements 
            WHERE DATE(created_at) = CURRENT_DATE()
        ")->fetchColumn() ?? 0;
        
        // Today's total value received and issued
        $todayReceived = $this->db->query("
            SELECT SUM(total_cost) 
            FROM stock_movements 
            WHERE DATE(created_at) = CURRENT_DATE() AND movement_type IN ('in', 'transfer_in')
        ")->fetchColumn() ?? 0;
        
        $todayIssued = $this->db->query("
            SELECT SUM(total_cost) 
            FROM stock_movements 
            WHERE DATE(created_at) = CURRENT_DATE() AND movement_type IN ('out', 'transfer_out')
        ")->fetchColumn() ?? 0;
        
        // Recent movements log
        $recentMovements = $this->db->query("
            SELECT sm.*, i.item_name, i.item_code, sl.location_name 
            FROM stock_movements sm 
            JOIN items i ON sm.item_id = i.item_id 
            LEFT JOIN store_locations sl ON sm.location_id = sl.location_id
            ORDER BY sm.movement_id DESC 
            LIMIT 10
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        // Stock summary chart data (Top 5 items by stock value)
        $topStockItems = $this->db->query("
            SELECT i.item_name, (SUM(s.quantity_on_hand) * i.unit_price) as stock_val 
            FROM stock s 
            JOIN items i ON s.item_id = i.item_id 
            GROUP BY s.item_id 
            ORDER BY stock_val DESC 
            LIMIT 5
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        // Monthly inventory value trend
        $monthlyTrends = $this->db->query("
            SELECT DATE_FORMAT(created_at, '%b %Y') as month_label, SUM(total_cost) as total_val 
            FROM stock_movements 
            GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
            ORDER BY created_at ASC 
            LIMIT 6
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        // Critical alerts list
        $alertsList = $this->db->query("
            SELECT item_name, item_code, current_stock, min_stock_level, unit_of_measure 
            FROM items 
            WHERE status = 'active' AND current_stock <= min_stock_level 
            LIMIT 5
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('inventory/dashboard', [
            'pageTitle' => 'Store & Inventory Dashboard',
            'activeModule' => 'store_inventory',
            'activeSubModule' => 'dashboard',
            'stats' => [
                'totalItems' => $totalItems,
                'totalValue' => $totalValue,
                'lowStockCount' => $lowStockCount,
                'totalPending' => $totalPendingApprovals,
                'todayTransactions' => $todayTransactions,
                'todayReceived' => $todayReceived,
                'todayIssued' => $todayIssued
            ],
            'recentMovements' => $recentMovements,
            'topStockItems' => $topStockItems,
            'monthlyTrends' => $monthlyTrends,
            'alertsList' => $alertsList
        ]);
    }
}
