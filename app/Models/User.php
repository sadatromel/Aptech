<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'user_id';
    
    protected array $fillable = [
        'username',
        'password',
        'email',
        'first_name',
        'last_name',
        'role_id',
        'employee_id',
        'status',
        'created_at',
        'updated_at',
        'last_login'
    ];

    public function getWithRole(int $id): ?array
    {
        return $this->db->get($this->table, [
            '[>]roles' => ['users.role_id' => 'roles.role_id']
        ], [
            'users.user_id',
            'users.username',
            'users.email',
            'users.first_name',
            'users.last_name',
            'users.status',
            'users.role_id',
            'roles.role_name'
        ], [
            'users.user_id' => $id
        ]);
    }

    public function getAllWithRoles(): array
    {
        return $this->db->select($this->table, [
            '[>]roles' => ['users.role_id' => 'roles.role_id']
        ], [
            'users.user_id',
            'users.username',
            'users.email',
            'users.first_name',
            'users.last_name',
            'users.status',
            'users.role_id',
            'roles.role_name'
        ], [
            'ORDER' => ['users.first_name', 'users.last_name']
        ]);
    }
}
