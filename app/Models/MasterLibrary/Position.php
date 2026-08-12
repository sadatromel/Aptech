<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;
use PDO;

class Position extends Model
{
    protected string $table = 'positions';
    protected string $primaryKey = 'position_id';
    protected array $fillable = [
        'position_name',
        'status'
    ];

    /**
     * Get all positions with employee counts
     */
    public function getAllWithEmployeeCount(): array
    {
        $sql = "
            SELECT p.*, 
                   (SELECT COUNT(*) FROM employees WHERE position_id = p.position_id) as employee_count
            FROM {$this->table} p
            ORDER BY p.position_name ASC
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find position by name
     */
    public function findByName(string $name): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE position_name = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get employee count for a position
     */
    public function getEmployeeCount(int $positionId): int
    {
        $sql = "SELECT COUNT(*) as count FROM employees WHERE position_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$positionId]);
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
