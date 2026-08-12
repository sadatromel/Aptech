<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class AdminService
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Get all users
     */
    public function getAllUsers(): array
    {
        $query = "SELECT u.user_id, u.username, u.first_name, u.last_name, u.email, u.role_id, r.role_name 
                  FROM users u 
                  LEFT JOIN roles r ON u.role_id = r.role_id 
                  ORDER BY u.username";
        
        $result = $this->db->query($query);
        return $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get permission matrix for user
     */
    public function getPermissionMatrix(int $userId): array
    {
        $query = "SELECT p.module, p.action, p.description
                  FROM user_permissions up
                  JOIN permissions p ON up.permission_id = p.permission_id
                  WHERE up.user_id = ?
                  ORDER BY p.module, p.action";
        
        $result = $this->db->query($query, [$userId]);
        $permissions = $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Organize by module
        $matrix = [];
        foreach ($permissions as $permission) {
            $module = $permission['module'];
            if (!isset($matrix[$module])) {
                $matrix[$module] = [];
            }
            $matrix[$module][$permission['action']] = true; // Simple boolean since permission_type doesn't exist
        }

        return $matrix;
    }

    /**
     * Update user permissions
     */
    public function updateUserPermissions(int $userId, array $permissions): bool
    {
        try {
            // Start transaction
            $this->db->beginTransaction();

            // Remove existing permissions
            $deleteQuery = "DELETE FROM user_permissions WHERE user_id = ?";
            $this->db->query($deleteQuery, [$userId]);

            // Get module mapping for permissions
            $mapQuery = "SELECT permission_name, module FROM permissions";
            $mapResult = $this->db->query($mapQuery);
            $mapping = [];
            foreach ($mapResult->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $mapping[$row['permission_name']] = $row['module'];
            }

            // Prepare insert statement
            $insertQuery = "INSERT INTO user_permissions 
                           (user_id, module, page_name, can_view, can_create, can_edit, can_delete, can_approve) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($insertQuery);

            // Add new permissions
            foreach ($permissions as $permName => $caps) {
                $module = $mapping[$permName] ?? 'other';
                
                $canView = isset($caps['can_view']) ? 1 : 0;
                $canCreate = isset($caps['can_create']) ? 1 : 0;
                $canEdit = isset($caps['can_edit']) ? 1 : 0;
                $canDelete = isset($caps['can_delete']) ? 1 : 0;
                $canApprove = isset($caps['can_approve']) ? 1 : 0; // Check if can_approve exists in form or DB
                
                // Only insert if at least one permission is granted
                if ($canView || $canCreate || $canEdit || $canDelete || $canApprove) {
                    $stmt->execute([
                        $userId, 
                        $module, 
                        $permName, 
                        $canView, 
                        $canCreate, 
                        $canEdit, 
                        $canDelete, 
                        $canApprove
                    ]);
                }
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Failed to update user permissions: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all available permissions
     */
    public function getAllPermissions(): array
    {
        $query = "SELECT permission_id, module, permission_name, description FROM permissions ORDER BY module, permission_name";
        $result = $this->db->query($query);
        return $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get detailed user permissions for matrix
     */
    public function getDetailedUserPermissions(int $userId): array
    {
        $query = "SELECT module, page_name as permission_name, can_view, can_create, can_edit, can_delete, can_approve
                  FROM user_permissions
                  WHERE user_id = ?";
        $result = $this->db->query($query, [$userId]);
        $rows = $result->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        
        $permissions = [];
        foreach ($rows as $row) {
            $permissions[$row['permission_name']] = $row;
        }
        
        return $permissions;
    }


    /**
     * Get module list for admin
     */
    public function getModuleList(): array
    {
        return [
            'Merchandising' => 'Merchandising',
            'Production' => 'Production',
            'HR' => 'Human Resources',
            'Finance' => 'Finance',
            'Store Inventory' => 'Store Inventory',
            'Procurement' => 'Procurement',
            'Transport' => 'Transport',
            'Cutting' => 'Cutting',
            'Sewing' => 'Sewing',
            'Washing' => 'Washing',
            'Packing' => 'Packing',
            'Visitor' => 'Visitor Management',
            'Admin' => 'Administration'
        ];
    }

    /**
     * Get system statistics
     */
    public function getSystemStats(): array
    {
        $stats = [];

        // User statistics
        $stats['users'] = [
            'total' => $this->getCount('users'),
            'active' => $this->getCount('users', ['status' => 'active']),
            'admin' => $this->getCount('users', ['role_id' => 1])
        ];

        // Module statistics
        $stats['modules'] = [
            'production_orders' => $this->getCount('production_orders'),
            'styles' => $this->getCount('styles'),
            'boms' => $this->getCount('bill_of_materials'),
            'employees' => $this->getCount('employees'),
            'inventory_items' => $this->getCount('inventory_items')
        ];

        // System health
        $stats['system'] = [
            'database_size' => $this->getDatabaseSize(),
            'last_backup' => $this->getLastBackupDate(),
            'php_version' => PHP_VERSION,
            'mysql_version' => $this->getMySQLVersion()
        ];

        return $stats;
    }

    /**
     * Get count from table with optional filters
     */
    private function getCount(string $table, array $filters = []): int
    {
        try {
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
        } catch (\Exception $e) {
            // Table might not exist or other DB error
            return 0;
        }
    }

    /**
     * Get database size
     */
    private function getDatabaseSize(): string
    {
        try {
            $query = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size 
                      FROM information_schema.tables 
                      WHERE table_schema = DATABASE()";
            $result = $this->db->query($query);
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            
            return ($row['size'] ?? 0) . ' MB';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get last backup date
     */
    private function getLastBackupDate(): string
    {
        try {
            $query = "SELECT created_at FROM backups ORDER BY created_at DESC LIMIT 1";
            $result = $this->db->query($query);
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            
            return $row['created_at'] ?? 'No backups found';
        } catch (\Exception $e) {
            return 'No backups found';
        }
    }

    /**
     * Get MySQL version
     */
    private function getMySQLVersion(): string
    {
        try {
            $query = "SELECT VERSION() as version";
            $result = $this->db->query($query);
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            
            return $row['version'] ?? 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }
}
