<?php

namespace App\Services;

use Core\Database;

class LinePerformanceService
{
    public function __construct(private Database $db)
    {
    }

    public function listPerformance(array $filters = []): array
    {
        $sql = "
            SELECT lp.*, pl.line_name, s.shift_name,
                   CONCAT(e.first_name, ' ', e.last_name) as supervisor_name,
                   CASE 
                       WHEN lp.efficiency >= 90 THEN 'Excellent'
                       WHEN lp.efficiency >= 75 THEN 'Good'
                       WHEN lp.efficiency >= 60 THEN 'Average'
                       ELSE 'Poor'
                   END as performance_rating,
                   ROUND((lp.actual_output / lp.planned_output) * 100, 1) as achievement_rate,
                   ROUND((lp.rejection_quantity / lp.actual_output) * 100, 2) as rejection_rate
            FROM line_performance lp
            LEFT JOIN production_lines pl ON lp.line_id = pl.production_line_id
            LEFT JOIN shifts s ON lp.shift = s.shift_id
            LEFT JOIN employees e ON lp.supervisor_id = e.employee_id
            WHERE 1=1
        ";

        // Logic handled in query to avoid division by zero if necessary, though SQL handles it or returns null usually. 
        // Legacy handled division logic in PHP or SQL? Legacy SQL: `ROUND((lp.actual_output / lp.planned_output) * 100, 1)`. 
        // If planned_output is 0, this might error or return NULL.
        // I will keep the SQL as is from legacy but add NULL checks if needed later.

        $params = [];
        if (!empty($filters['search'])) {
            $sql .= " AND (pl.line_name LIKE ? OR pl.line_code LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $sql .= " ORDER BY lp.date DESC, lp.shift DESC";

        return $this->db->query($sql, $params)->fetchAll();
    }

    public function getPerformance(int $id)
    {
        $sql = "SELECT lp.*, pl.line_name, s.shift_name,
                       CONCAT(e.first_name, ' ', e.last_name) as supervisor_name
                FROM line_performance lp
                LEFT JOIN production_lines pl ON lp.line_id = pl.production_line_id
                LEFT JOIN shifts s ON lp.shift = s.shift_id
                LEFT JOIN employees e ON lp.supervisor_id = e.employee_id
                WHERE lp.performance_id = ?";
        return $this->db->query($sql, [$id])->fetch();
    }

    public function createPerformance(array $data): int
    {
        $payload = [
            'line_id' => (int)$data['line_id'],
            'date' => $data['date'],
            'shift' => trim($data['shift']),
            'planned_output' => (int)$data['planned_output'],
            'actual_output' => (int)$data['actual_output'],
            'rejection_quantity' => (int)$data['rejection_quantity'],
            'repair_quantity' => (int)$data['repair_quantity'],
            'efficiency' => (float)$data['efficiency'],
            'downtime_minutes' => (int)$data['downtime_minutes'],
            'supervisor_id' => (int)$data['supervisor_id'],
            'notes' => $data['notes'] ?? '',
            'created_by' => $_SESSION['user_id'] ?? 1
        ];

        $this->db->insert('line_performance', $payload);
        return (int)$this->db->lastInsertId();
    }

    public function deletePerformance(int $id): bool
    {
        return (bool)$this->db->delete('line_performance', ['performance_id' => $id]);
    }

    public function getStats(): array
    {
        return [
            'total_records' => (int)$this->db->count('line_performance'),
            'avg_efficiency' => (float)$this->db->query("SELECT AVG(efficiency) FROM line_performance")->fetchColumn() ?: 0,
            // Custom stats not supported by basic avg/sum wrappers easily if complex conditions
            'avg_achievement' => (float)$this->db->query("SELECT AVG((actual_output / planned_output) * 100) FROM line_performance WHERE planned_output > 0")->fetchColumn() ?: 0,
            'avg_rejection' => (float)$this->db->query("SELECT AVG((rejection_quantity / actual_output) * 100) FROM line_performance WHERE actual_output > 0")->fetchColumn() ?: 0
        ];
    }
}
