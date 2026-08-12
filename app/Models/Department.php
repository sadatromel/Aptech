<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;

class Department extends Model
{
    protected string $table = 'departments';
    protected string $primaryKey = 'department_id';
    protected array $fillable = [
        'department_code',
        'department_name',
        'description',
        'status',
        'created_at',
        'updated_at'
    ];

    /**
     * Get all departments with employee counts and factory names
     */
    public function getAllWithEmployeeCount(): array
    {
        $sql = "
            SELECT d.*, 
                   (SELECT COUNT(*) FROM employees WHERE department_id = d.department_id) as employee_count,
                   (SELECT GROUP_CONCAT(f.factory_name SEPARATOR ', ') 
                    FROM department_factories df 
                    JOIN factories f ON df.factory_id = f.factory_id 
                    WHERE df.department_id = d.department_id) as factory_names
            FROM {$this->table} d
            ORDER BY d.department_name
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get linked factories for a department
     */
    public function getFactories(int $departmentId): array
    {
        $sql = "
            SELECT f.* 
            FROM factories f
            JOIN department_factories df ON f.factory_id = df.factory_id
            WHERE df.department_id = ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$departmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Save linked factories for a department
     */
    public function saveFactories(int $departmentId, array $factoryIds): void
    {
        $sqlDelete = "DELETE FROM department_factories WHERE department_id = ?";
        $stmtDelete = $this->db->prepare($sqlDelete);
        $stmtDelete->execute([$departmentId]);

        if (empty($factoryIds)) {
            return;
        }

        $sqlInsert = "INSERT INTO department_factories (department_id, factory_id) VALUES (?, ?)";
        $stmtInsert = $this->db->prepare($sqlInsert);
        foreach ($factoryIds as $factoryId) {
            $fid = (int)$factoryId;
            if ($fid > 0) {
                $stmtInsert->execute([$departmentId, $fid]);
            }
        }
    }

    /**
     * Find department by code
     */
    public function findByCode(string $code): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE department_code = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get employee count for a department
     */
    public function getEmployeeCount(int $departmentId): int
    {
        $sql = "SELECT COUNT(*) as count FROM employees WHERE department_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$departmentId]);
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }

    /**
     * Get total statistics
     */
    public function getStats(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive
            FROM {$this->table}
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
