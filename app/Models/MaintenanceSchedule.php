<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;

class MaintenanceSchedule extends Model
{
    protected string $table = 'maintenance_schedule';
    protected string $primaryKey = 'maintenance_id';
    protected array $fillable = [
        'vehicle_id',
        'maintenance_type',
        'description',
        'scheduled_date',
        'cost',
        'status',
        'notes',
        'created_by',
        'created_at',
        'updated_at'
    ];

    /**
     * Get all maintenance records with vehicle information
     */
    public function getAllWithVehicles(): array
    {
        $sql = "
            SELECT 
                ms.*, 
                v.registration_number, 
                CONCAT(v.make, ' ', v.model) as vehicle_name 
            FROM {$this->table} ms 
            JOIN vehicles v ON ms.vehicle_id = v.vehicle_id 
            ORDER BY ms.scheduled_date DESC
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Search maintenance records with filters
     */
    public function searchWithFilters(string $search = '', string $status = '', string $dateFrom = '', string $dateTo = ''): array
    {
        $sql = "
            SELECT 
                ms.*, 
                v.registration_number, 
                CONCAT(v.make, ' ', v.model) as vehicle_name 
            FROM {$this->table} ms 
            JOIN vehicles v ON ms.vehicle_id = v.vehicle_id 
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (ms.maintenance_type LIKE ? OR ms.description LIKE ? OR v.registration_number LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        if (!empty($status)) {
            $sql .= " AND ms.status = ?";
            $params[] = $status;
        }
        
        if (!empty($dateFrom)) {
            $sql .= " AND ms.scheduled_date >= ?";
            $params[] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $sql .= " AND ms.scheduled_date <= ?";
            $params[] = $dateTo;
        }
        
        $sql .= " ORDER BY ms.scheduled_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update maintenance status
     */
    public function updateStatus(int $maintenanceId, string $newStatus): bool
    {
        $sql = "UPDATE {$this->table} SET status = ?, updated_at = NOW() WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$newStatus, $maintenanceId]);
    }

    /**
     * Get maintenance record with vehicle details
     */
    public function findWithVehicle(int $id): ?array
    {
        $sql = "
            SELECT 
                ms.*, 
                v.registration_number, 
                CONCAT(v.make, ' ', v.model) as vehicle_name,
                v.make,
                v.model
            FROM {$this->table} ms 
            JOIN vehicles v ON ms.vehicle_id = v.vehicle_id 
            WHERE ms.{$this->primaryKey} = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get upcoming maintenance (scheduled status)
     */
    public function getUpcoming(int $limit = 10): array
    {
        $sql = "
            SELECT 
                ms.*, 
                v.registration_number, 
                CONCAT(v.make, ' ', v.model) as vehicle_name 
            FROM {$this->table} ms 
            JOIN vehicles v ON ms.vehicle_id = v.vehicle_id 
            WHERE ms.status = 'scheduled' 
            ORDER BY ms.scheduled_date ASC 
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get overdue maintenance
     */
    public function getOverdue(): array
    {
        $sql = "
            SELECT 
                ms.*, 
                v.registration_number, 
                CONCAT(v.make, ' ', v.model) as vehicle_name 
            FROM {$this->table} ms 
            JOIN vehicles v ON ms.vehicle_id = v.vehicle_id 
            WHERE ms.status IN ('scheduled', 'in_progress') 
            AND ms.scheduled_date < CURDATE()
            ORDER BY ms.scheduled_date ASC
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get maintenance statistics
     */
    public function getStats(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue,
                SUM(cost) as total_cost,
                AVG(cost) as avg_cost
            FROM {$this->table}
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
