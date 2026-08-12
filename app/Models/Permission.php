<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;

class Permission extends Model
{
    protected string $table = 'permissions';
    protected string $primaryKey = 'permission_id';

    protected array $fillable = [
        'module',
        'permission_name',
        'permission_code',
        'description'
    ];

    public function getAllGroupedByModule(): array
    {
        $permissions = $this->all();
        $grouped = [];
        
        foreach ($permissions as $permission) {
            $module = $permission['module'];
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $permission;
        }
        
        return $grouped;
    }
}
