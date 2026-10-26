<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class UserService
{
    public function __construct(private Database $db)
    {
    }

    public function getStats(bool $includeSystemAdmin = true): array
    {
        if (!$includeSystemAdmin) {
            $total = (int) ($this->db->count('users', [
                'role_id[!]' => [1, 2]
            ]) ?? 0);
            $active = (int) ($this->db->count('users', [
                'status' => 'active',
                'role_id[!]' => [1, 2]
            ]) ?? 0);
            $inactive = (int) ($this->db->count('users', [
                'status' => 'inactive',
                'role_id[!]' => [1, 2]
            ]) ?? 0);
            $admin = 0;

            return [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'admin' => $admin,
            ];
        }

        // Get stats
        $total = (int) ($this->db->count('users') ?? 0);
        $active = (int) ($this->db->count('users', ['status' => 'active']) ?? 0);
        $inactive = (int) ($this->db->count('users', ['status' => 'inactive']) ?? 0);
        
        // Count admins (role_id 1)
        $admin = (int) ($this->db->count('users', ['role_id' => 1]) ?? 0);
        
        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'admin' => $admin,
        ];
    }

    public function listUsers(array $filters = [], bool $includeSystemAdmin = true): array
    {
        $selectColumns = [
            'users.user_id',
            'users.first_name',
            'users.last_name',
            'users.email',
            'users.status',
            'users.last_login',
            'users.created_at',
            'users.role_id',
            'roles.role_name'
        ];
        
        $where = ['ORDER' => ['users.created_at' => 'DESC']];

        if (!$includeSystemAdmin) {
            $where['AND']['users.role_id[!]'] = [1, 2];
            $where['AND']['roles.role_name[!]'] = ['System Administrator', 'Supper Admin', 'Super Admin'];
        }
        
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where['AND']['OR'] = [
                'users.first_name[~]' => $term,
                'users.last_name[~]' => $term,
                'users.email[~]' => $term,
                'roles.role_name[~]' => $term,
            ];
        }
        
        if (!empty($filters['role_id'])) {
            $where['AND']['users.role_id'] = (int) $filters['role_id'];
        }
        
        if (!empty($filters['status'])) {
            $where['AND']['users.status'] = $filters['status'];
        }
        
        $users = $this->db->select('users', [
            '[>]roles' => ['users.role_id' => 'roles.role_id'],
        ], $selectColumns, $where) ?? [];
        
        return $users;
    }
    
    private function getDemoUsers(): array
    {
        return [
            [
                'user_id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com',
                'status' => 'active',
                'last_login' => date('Y-m-d H:i:s'),
                'created_at' => '2024-01-15 10:00:00',
                'role_id' => 1,
                'role_name' => 'Admin',
            ],
            [
                'user_id' => 2,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.smith@example.com',
                'status' => 'active',
                'last_login' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'created_at' => '2024-01-16 14:30:00',
                'role_id' => 2,
                'role_name' => 'Manager',
            ],
            [
                'user_id' => 3,
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'email' => 'bob.johnson@example.com',
                'status' => 'inactive',
                'last_login' => null,
                'created_at' => '2024-01-17 09:15:00',
                'role_id' => 3,
                'role_name' => 'User',
            ],
        ];
    }

    public function getRoles(bool $includePrivileged = true): array
    {
        $where = ['ORDER' => ['role_name' => 'ASC']];
        if (!$includePrivileged) {
            $where['AND'] = [
                'role_id[!]' => [1, 2],
                'role_name[!]' => ['System Administrator', 'Supper Admin', 'Super Admin'],
            ];
        }

        return $this->db->select('roles', [
            'role_id',
            'role_name',
        ], $where) ?? [];
    }

    public function createUser(array $data, int $createdBy): int
    {
        // Check if email exists
        $existing = $this->db->get('users', 'user_id', ['email' => $data['email']]);
        if ($existing) {
            throw new \Exception('Email already exists');
        }

        // Generate username from email
        $username = explode('@', $data['email'])[0];
        
        // Ensure username is unique
        $checkUser = $this->db->count('users', ['username' => $username]);
        if ($checkUser > 0) {
            $username .= rand(100, 999);
        }

        $payload = [
            'username' => $username,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role_id' => (int) $data['role_id'],
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return (int) $this->db->insert('users', $payload);
    }

    public function getUser(int $userId): ?array
    {
        return $this->db->get('users', [
            '[>]roles' => ['users.role_id' => 'roles.role_id'],
        ], [
            'users.user_id',
            'users.first_name',
            'users.last_name',
            'users.email',
            'users.status',
            'users.role_id',
            'users.last_login',
            'users.created_at',
            'users.updated_at',
            'roles.role_name',
        ], [
            'users.user_id' => $userId,
        ]) ?: null;
    }

    public function updateUser(int $userId, array $data, int $updatedBy): bool
    {
        // Check if email exists for another user
        if (!empty($data['email'])) {
            $existing = $this->db->get('users', 'user_id', [
                'email' => $data['email'],
                'user_id[!]' => $userId,
            ]);
            if ($existing) {
                throw new \Exception('Email already exists');
            }
        }

        $payload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'role_id' => (int) $data['role_id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return (bool) $this->db->update('users', $payload, ['user_id' => $userId]);
    }

    public function deleteUser(int $userId): bool
    {
        // Delete related records first
        $this->db->delete('user_activity_logs', ['user_id' => $userId]);
        
        // Then delete the user
        return (bool) $this->db->delete('users', ['user_id' => $userId]);
    }

    public function resetPassword(int $userId, string $password): bool
    {
        return (bool) $this->db->update('users', [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['user_id' => $userId]);
    }

    public function updateStatus(int $userId, string $status): bool
    {
        return (bool) $this->db->update('users', [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['user_id' => $userId]);
    }

    // Access Application Methods
    public function getAccessApplications(string $status = 'pending'): array
    {
        try {
            // Check if table exists
            $tables = $this->db->query("SHOW TABLES LIKE 'access_applications'");
            if (empty($tables)) {
                return [];
            }

            return $this->db->select('access_applications', '*', [
                'status' => $status,
                'ORDER' => ['created_at' => 'DESC']
            ]) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getAccessApplication(int $id): ?array
    {
        try {
            return $this->db->get('access_applications', '*', ['id' => $id]) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function updateApplicationStatus(int $id, string $status): bool
    {
        try {
            return (bool) $this->db->update('access_applications', [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $id]);
        } catch (\Exception $e) {
            return false;
        }
    }
}
