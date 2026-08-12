<?php

namespace App\Services;

use App\Models\Bundle;
use Core\Database;

class BundleService
{
    public function __construct(private Database $db)
    {
    }

    public function listBundles(array $filters = []): array
    {
        $sql = "
            SELECT bg.*, m.marker_number, s.style_code, s.style_name,
                   cr.cutting_room_name, CONCAT(u.first_name, ' ', u.last_name) as created_by_name
            FROM bundle_generation bg
            LEFT JOIN markers m ON bg.marker_id = m.marker_id
            LEFT JOIN styles s ON bg.style_id = s.style_id
            LEFT JOIN cutting_rooms cr ON bg.cutting_room_id = cr.cutting_room_id
            LEFT JOIN users u ON bg.created_by = u.user_id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($filters['search'])) {
            $sql .= " AND (bg.bundle_number LIKE ? OR s.style_code LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $sql .= " ORDER BY bg.generated_date DESC";

        return $this->db->query($sql, $params)->fetchAll();
    }

    public function createBundle(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $payload = [
                'bundle_number' => $data['bundle_number'],
                'marker_id' => (int)$data['marker_id'],
                'style_id' => (int)$data['style_id'],
                'size_name' => $data['size_name'],
                'quantity' => (int)$data['bundle_quantity'],
                'bundle_type' => $data['bundle_type'],
                'generated_date' => $data['generated_date'],
                'cutting_room_id' => (int)$data['cutting_room_id'],
                'notes' => $data['notes'] ?? '',
                'piece_count' => (int)$data['bundle_quantity'], // Initial piece count
                'cut_pieces' => (int)$data['bundle_quantity'], // All start as cut
                'created_by' => $_SESSION['user_id'] ?? 1,
                'status' => 'generated'
            ];

            // Manual insert to ensure ID generic logic matches legacy
            $this->db->insert('bundle_generation', $payload);
            $bundleId = (int)$this->db->lastInsertId();

            // Generate bundle pieces
            $quantity = (int)$data['bundle_quantity'];
            for ($i = 1; $i <= $quantity; $i++) {
                $pieceNumber = $data['bundle_number'] . '-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
                $this->db->insert('bundle_pieces', [
                    'bundle_id' => $bundleId,
                    'piece_number' => $pieceNumber,
                    'piece_status' => 'cut'
                ]);
            }

            $this->db->commit();
            return (int)$bundleId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteBundle(int $id): bool
    {
        $this->db->delete('bundle_pieces', ['bundle_id' => $id]);
        return (bool)$this->db->delete('bundle_generation', ['bundle_id' => $id]);
    }

    public function getStats(): array
    {
        return [
            'total_bundles' => (int)$this->db->count('bundle_generation'),
            'active_bundles' => (int)$this->db->count('bundle_generation', ['status' => ['generated', 'issued']]),
            'completed_bundles' => (int)$this->db->count('bundle_generation', ['status' => 'completed']),
            'total_pieces' => (int)$this->db->sum('bundle_generation', 'quantity')
        ];
    }
}
