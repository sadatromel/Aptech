<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;
use App\Models\Permission;

class RolePermission extends Model
{
    protected string $table = 'role_permissions';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'role_id',
        'module',
        'page_name',
        'can_view',
        'can_create',
        'can_edit',
        'can_delete',
        'can_approve'
    ];

    /**
     * Get permission matrix for a role
     */
    public function getByRole(int $roleId): array
    {
        try {
            $results = $this->db->select($this->table, [
                'module',
                'page_name',
                'can_view',
                'can_create',
                'can_edit',
                'can_delete',
                'can_approve'
            ], [
                'role_id' => $roleId
            ]);
        } catch (\Exception $e) {
            return [];
        }

        // Key by page_name for easy lookup
        $permissions = [];
        if ($results) {
            foreach ($results as $row) {
                $permissions[$row['page_name']] = $row;
            }
        }

        return $permissions;
    }
    
    /**
     * Bulk update permissions for a role
     */
    public function updateForRole(int $roleId, array $permissionsData): bool
    {
        try {
            $this->db->beginTransaction();
            
            // 1. Delete existing permissions for this role
            $this->db->delete($this->table, ['role_id' => $roleId]);
            
            // 2. Insert new permissions
            if (!empty($permissionsData)) {
                foreach ($permissionsData as $pageName => $flags) {
                    $permissionModel = new Permission();
                    // Find the module for this page
                    $permDef = $permissionModel->where('permission_name', $pageName)->first();
                    
                    if ($permDef) {
                        $this->create([
                            'role_id' => $roleId,
                            'module' => $permDef['module'],
                            'page_name' => $pageName,
                            'can_view' => isset($flags['can_view']) ? 1 : 0,
                            'can_create' => isset($flags['can_create']) ? 1 : 0,
                            'can_edit' => isset($flags['can_edit']) ? 1 : 0,
                            'can_delete' => isset($flags['can_delete']) ? 1 : 0,
                            'can_approve' => isset($flags['can_approve']) ? 1 : 0
                        ]);
                    } else {
                        // Log when permission is not found
                        error_log("Permission not found in database: " . $pageName);
                    }
                }
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
