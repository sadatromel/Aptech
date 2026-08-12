<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class DashboardService
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Get dashboard statistics for current user
     */
    public function getDashboardStats(int $userId): array
    {
        $stats = [];

        // Get user role and permissions
        $userQuery = "SELECT u.*, r.role_name 
                     FROM users u 
                     LEFT JOIN roles r ON u.role_id = r.role_id 
                     WHERE u.user_id = ?";
        $userResult = $this->db->query($userQuery, [$userId]);
        $user = $userResult->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            return [];
        }

        $stats['user'] = [
            'name' => $user['full_name'] ?? $user['name'] ?? $user['username'] ?? 'Unknown User',
            'email' => $user['email'],
            'role' => $user['role_name'] ?? 'Unknown'
        ];

        // Get module-specific stats based on user role
        $stats['modules'] = $this->getModuleStats($userId, $user['role_name']);

        // Get recent activities
        $stats['recent_activities'] = $this->getRecentActivities($userId);

        // Get system notifications
        $stats['notifications'] = $this->getNotifications($userId);

        return $stats;
    }

    /**
     * Get module-specific statistics
     */
    private function getModuleStats(int $userId, ?string $role): array
    {
        $modules = [];

        // Production stats
        if ($this->hasModuleAccess($userId, 'production')) {
            $modules['production'] = [
                'total_orders' => $this->getCount('production_orders'),
                'pending_orders' => $this->getCount('production_orders', ['status' => 'pending']),
                'in_progress' => $this->getCount('production_orders', ['status' => 'in_progress']),
                'completed' => $this->getCount('production_orders', ['status' => 'completed'])
            ];
        }

        // Merchandising stats
        if ($this->hasModuleAccess($userId, 'merchandising')) {
            $modules['merchandising'] = [
                'total_styles' => $this->getCount('styles'),
                'active_boms' => $this->getCount('bom', ['status' => 'approved']),
                'pending_samples' => $this->getCount('styles', ['status' => 'design']),
                'total_purchase_requests' => $this->getCount('purchase_requests')
            ];
        }

        // HR stats
        if ($this->hasModuleAccess($userId, 'hr')) {
            $modules['hr'] = [
                'total_employees' => $this->getCount('employees', ['status' => 'active']),
                'pending_attendance' => $this->getCount('attendance_records', ['attendance_status' => 'late']),
                'pending_payroll' => $this->getCount('payroll', ['status' => 'Pending']),
                'total_departments' => $this->getCount('departments')
            ];
        }

        // Finance stats
        if ($this->hasModuleAccess($userId, 'finance')) {
            $modules['finance'] = [
                'total_payables' => $this->getCount('purchase_orders', ['status' => 'received']),
                'total_receivables' => $this->getCount('commercial_invoices', ['status' => 'sent']),
                'pending_invoices' => $this->getCount('commercial_invoices', ['status' => 'draft']),
                'total_transactions' => $this->getCount('transactions')
            ];
        }

        // Inventory stats
        if ($this->hasModuleAccess($userId, 'inventory')) {
            $modules['inventory'] = [
                'total_items' => $this->getCount('items'),
                'low_stock_items' => $this->getLowStockItems(),
                'pending_transfers' => $this->safeGetCount('stock_transfer_requests', 'status = "pending"'),
                'total_warehouses' => $this->getCount('warehouses')
            ];
        }

        return $modules;
    }

    /**
     * Get recent user activities
     */
    private function getRecentActivities(int $userId): array
    {
        $query = "SELECT activity_type, description, timestamp as created_at 
                  FROM user_activity_logs 
                  WHERE user_id = ? 
                  ORDER BY timestamp DESC 
                  LIMIT 10";
        
        $result = $this->db->query($query, [$userId]);
        return $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get extended dashboard statistics (RMG ERP Specific)
     */
    public function getExtendedStats(): array
    {
        $stats = [];
        
        // Merchandising Stats
        $stats['styles'] = $this->safeGetCount('styles', "status != 'cancelled'");
        $stats['buyers'] = $this->safeGetCount('buyers', "status = 'active'");
        $stats['bom'] = $this->safeGetCount('bom', "status = 'approved'");
        
        // Production Stats
        $stats['production_orders'] = $this->safeGetCount('production_orders', "status IN ('planned', 'released', 'in_progress')");
        $stats['lines'] = $this->safeGetCount('production_lines', "status = 'active'");
        $stats['wip'] = $this->safeGetCount('wip_transactions', "DATE(transaction_date) = CURDATE()");
        
        // Procurement Stats
        $stats['vendors'] = $this->safeGetCount('vendors', "status = 'active'");
        $stats['purchase_orders'] = $this->safeGetCount('purchase_orders', "status IN ('sent', 'confirmed', 'partial_received')");
        $stats['pending_grn'] = $this->safeGetCount('grn', "status IN ('draft', 'pending_qc')");
        
        // Quality Stats
        $stats['qc_checks'] = $this->safeGetCount('qc_checks', "DATE(inspection_date) = CURDATE()");
        $stats['defects'] = $this->safeGetCount('defects', "DATE(created_at) = CURDATE()");
        
        // HR Stats
        $stats['employees'] = $this->safeGetCount('employees', "status = 'active'");
        $stats['attendance_today'] = $this->safeGetCount('attendance_records', "attendance_date = CURDATE() AND attendance_status = 'present'");

        return $stats;
    }

    /**
     * Helper to safely get count from table
     */
    private function safeGetCount(string $table, string $where = '1=1'): int 
    {
        try {
            $result = $this->db->query("SELECT COUNT(*) as count FROM $table WHERE $where");
            if ($result) {
                $row = $result->fetch(\PDO::FETCH_ASSOC);
                return (int) ($row['count'] ?? 0);
            }
        } catch (\Exception $e) {
            // Table doesn't exist or query failed, return 0
        }
        return 0;
    }

    /**
     * Get Buyer Monthly Orders for Chart
     */
    /**
     * Get Buyer Monthly Orders for Chart
     */
    public function getBuyerMonthlyOrders(?int $factoryId = null): array
    {
        try {
            $params = [];
            $whereClause = "WHERE po.ex_factory_date IS NOT NULL 
                            AND po.ex_factory_date >= '2026-02-01' 
                            AND po.ex_factory_date <= '2027-01-31' 
                            AND po.status != 'cancelled'";
            
            if ($factoryId) {
                $whereClause .= " AND po.factory_id = ?";
                $params[] = $factoryId;
            }

            $query = "
                SELECT 
                    COALESCE(b.buyer_name, 'No Buyer') as buyer_name,
                    DATE_FORMAT(po.ex_factory_date, '%Y-%m') as month_year,
                    DATE_FORMAT(po.ex_factory_date, '%b %y') as month_year_label,
                    SUM(po.quantity_ordered) as total_quantity
                FROM production_orders po
                LEFT JOIN buyers b ON po.buyer_id = b.buyer_id
                $whereClause
                GROUP BY b.buyer_id, DATE_FORMAT(po.ex_factory_date, '%Y-%m')
                ORDER BY month_year ASC
            ";
            $result = $this->db->query($query, $params);
            return $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get active factories
     */
    public function getFactories(): array
    {
        try {
            $query = "SELECT factory_id, factory_name FROM factories WHERE status = 'active' ORDER BY factory_name ASC";
            $result = $this->db->query($query);
            return $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Production Status Limit 5
     */
    public function getProductionStatus(): array
    {
        try {
            $query = "
                SELECT 
                    po.order_number,
                    s.style_name,
                    po.quantity,
                    po.completed_quantity,
                    po.due_date,
                    po.status,
                    b.buyer_name
                FROM production_orders po
                LEFT JOIN styles s ON po.style_id = s.style_id
                LEFT JOIN buyers b ON po.buyer_id = b.buyer_id
                WHERE po.status IN ('planned', 'released', 'in_progress')
                ORDER BY po.due_date ASC
                LIMIT 5
            ";
            $result = $this->db->query($query);
            return $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
           return [];
        }
    }

    /**
     * Get Critical Inventory
     */
    public function getCriticalInventory(): array
    {
        try {
            $query = "
                SELECT 
                    i.item_name,
                    i.item_type,
                    SUM(st.quantity_on_hand) as total_stock,
                    i.min_stock_level
                FROM stock st
                LEFT JOIN items i ON st.item_id = i.item_id
                WHERE i.min_stock_level > 0
                GROUP BY i.item_id
                HAVING total_stock <= i.min_stock_level
                LIMIT 5
            ";
            $result = $this->db->query($query);
            return $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get user notifications
     */
    private function getNotifications(int $userId): array
    {
        // Table does not exist yet
        return [];
        /*
        $query = "SELECT * FROM notifications 
                  WHERE user_id = ? AND status = 'unread' 
                  ORDER BY created_at DESC 
                  LIMIT 5";
        
        $result = $this->db->query($query, [$userId]);
        return $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        */
        return $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Check if user has access to module
     */
    private function hasModuleAccess(int $userId, string $module): bool
    {
        $query = "SELECT COUNT(*) as count 
                  FROM user_permissions 
                  WHERE user_id = ? AND module = ? AND can_view = 1";
        
        $result = $this->db->query($query, [$userId, $module]);
        $row = $result->fetch(\PDO::FETCH_ASSOC);
        
        return ($row['count'] ?? 0) > 0;
    }

    /**
     * Get count from table with optional filters
     */
    private function getCount(string $table, array $filters = []): int
    {
        $query = "SELECT COUNT(*) as count FROM {$table}";
        $params = [];

        if (!empty($filters)) {
            $whereClauses = [];
            foreach ($filters as $column => $value) {
                $whereClauses[] = "{$column} = ?";
                $params[] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $whereClauses);
        }

        $result = $this->db->query($query, $params);
        $row = $result->fetch(\PDO::FETCH_ASSOC);
        
        return (int) ($row['count'] ?? 0);
    }

    /**
     * Get low stock items count
     */
    private function getLowStockItems(): int
    {
        $query = "SELECT COUNT(DISTINCT i.item_id) as count 
                  FROM items i
                  JOIN stock s ON i.item_id = s.item_id
                  WHERE s.quantity_on_hand <= i.reorder_level AND i.status = 'active'";
        
        $result = $this->db->query($query);
        $row = $result->fetch(\PDO::FETCH_ASSOC);
        
        return (int) ($row['count'] ?? 0);
    }
}
