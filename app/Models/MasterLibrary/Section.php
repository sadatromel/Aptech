<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;
use PDO;

class Section extends Model
{
    protected string $table = 'sections';
    protected string $primaryKey = 'section_id';
    protected array $fillable = [
        'section_name',
        'status',
        'department_id'
    ];

    /**
     * Get all sections with employee counts and parent department names
     */
    public function getAllWithEmployeeCount(): array
    {
        $sql = "
            SELECT s.*, d.department_name,
                   (SELECT COUNT(*) FROM employees WHERE section_id = s.section_id) as employee_count
            FROM {$this->table} s
            LEFT JOIN departments d ON s.department_id = d.department_id
            ORDER BY s.section_name ASC
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get sections count for a department
     */
    public function getSectionsCountForDepartment(int $departmentId): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE department_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$departmentId]);
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }

    /**
     * Find section by name
     */
    public function findByName(string $name): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE section_name = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get employee count for a section
     */
    public function getEmployeeCount(int $sectionId): int
    {
        $sql = "SELECT COUNT(*) as count FROM employees WHERE section_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sectionId]);
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
