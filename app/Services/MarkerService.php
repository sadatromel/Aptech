<?php

namespace App\Services;

use Core\Database;
use PDO;

class MarkerService
{
    public function __construct(private Database $db)
    {
    }

    public function listMarkers(array $filters = []): array
    {
        $sql = "
            SELECT m.*, cp.plan_number, s.style_code, s.style_name,
                   CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                   COUNT(ms.marker_size_id) as size_count
            FROM markers m
            LEFT JOIN cutting_plans cp ON m.plan_id = cp.plan_id
            LEFT JOIN styles s ON m.style_id = s.style_id
            LEFT JOIN users u ON m.created_by = u.user_id
            LEFT JOIN marker_sizes ms ON m.marker_id = ms.marker_id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($filters['search'])) {
            $sql .= " AND (m.marker_number LIKE ? OR s.style_code LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $sql .= " GROUP BY m.marker_id ORDER BY m.created_date DESC";

        return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createMarker(array $data): int
    {
        $payload = [
            'marker_number' => $data['marker_number'],
            'plan_id' => (int)$data['plan_id'],
            'style_id' => (int)$data['style_id'],
            'marker_type' => $data['marker_type'],
            'total_pieces' => (int)$data['total_pieces'],
            'marker_length' => (float)$data['marker_length'],
            'marker_width' => (float)$data['marker_width'],
            'fabric_consumption' => (float)$data['fabric_consumption'],
            'efficiency' => (float)$data['efficiency'],
            'created_date' => $data['created_date'],
            'notes' => $data['notes'] ?? '',
            'created_by' => $_SESSION['user_id'] ?? 1,
            'status' => 'active'
        ];

        $this->db->insert('markers', $payload);
        $markerId = (int)$this->db->lastInsertId();

        if (!empty($data['size_combinations'])) {
            foreach ($data['size_combinations'] as $combination) {
                $this->db->insert('marker_sizes', [
                    'marker_id' => $markerId,
                    'size_name' => $combination['size_name'],
                    'quantity' => (int)$combination['quantity'],
                    'position' => $combination['position']
                ]);
            }
        }
        
        return $markerId;
    }

    public function deleteMarker(int $id): bool
    {
        $this->db->delete('marker_sizes', ['marker_id' => $id]);
        return (bool)$this->db->delete('markers', ['marker_id' => $id]);
    }

    public function getStats(): array
    {
        return [
            'total_markers' => (int)$this->db->count('markers'),
            'approved_markers' => (int)$this->db->count('markers', ['status' => 'approved']),
            'avg_efficiency' => (float)$this->db->query("SELECT AVG(efficiency) FROM markers WHERE efficiency > 0")->fetchColumn() ?: 0,
            'total_pieces' => (int)$this->db->sum('markers', 'total_pieces')
        ];
    }
}
