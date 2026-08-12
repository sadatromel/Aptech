<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;

class UserPermission extends Model
{
    protected string $table = 'user_permissions';
    protected string $primaryKey = 'id'; // Assuming 'id' exists, otherwise composite key handling needed

    protected array $fillable = [
        'user_id',
        'module',
        'page_name',
        'can_view',
        'can_create',
        'can_edit',
        'can_delete',
        'can_approve'
    ];

    public function getByUser(int $userId): array
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
                'user_id' => $userId
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
    
    public function updateForUser(int $userId, array $permissionsData): bool
    {
        try {
            $this->db->beginTransaction();
            
            // 1. Delete existing permissions for this user
            $this->db->delete($this->table, ['user_id' => $userId]);
            
            // 2. Insert new permissions
            if (!empty($permissionsData)) {
                foreach ($permissionsData as $pageName => $flags) {
                    $permissionModel = new Permission();
                    // Find the module for this page
                    $permDef = $permissionModel->where('permission_name', $pageName)->first();
                    
                    if ($permDef) {
                        $this->create([
                            'user_id' => $userId,
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
