<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class SamplingService
{
    private const STATUS_PENDING = ['requested', 'in_progress'];

    public function __construct(private Database $db)
    {
    }

    public function getStats(): array
    {
        $total = (int) ($this->db->count('sampling') ?? 0);
        $pending = (int) ($this->db->count('sampling', ['status' => self::STATUS_PENDING]) ?? 0);
        $approved = (int) ($this->db->count('sampling', ['status' => 'approved']) ?? 0);
        $rejected = (int) ($this->db->count('sampling', ['status' => 'rejected']) ?? 0);

        return [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
        ];
    }

    public function getSamples(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = 's.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['style_id'])) {
            $conditions[] = 's.style_id = ?';
            $params[] = (int) $filters['style_id'];
        }

        if (!empty($filters['sample_type'])) {
            $conditions[] = 's.sample_type = ?';
            $params[] = $filters['sample_type'];
        }

        if (!empty($filters['from_date'])) {
            $conditions[] = 's.request_date >= ?';
            $params[] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $conditions[] = 's.request_date <= ?';
            $params[] = $filters['to_date'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(st.style_code LIKE ? OR st.style_name LIKE ? OR s.notes LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT 
                s.sample_id,
                s.requisition_no,
                s.style_id,
                s.sample_type,
                s.sample_size,
                s.size_breakdown,
                s.quantity,
                s.request_date,
                s.due_date,
                s.shell_fabric_available,
                s.pocketing_available,
                s.lining_interlining_available,
                s.sewing_accessories_available,
                s.finishing_accessories_available,
                s.status,
                s.notes,
                s.created_date,
                s.updated_date,
                st.style_code,
                st.style_name
            FROM sampling s
            LEFT JOIN styles st ON s.style_id = st.style_id
            {$where}
            ORDER BY s.request_date DESC, s.created_date DESC
        ";

        $stmt = $this->db->query($sql, $params);
        $samples = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return array_map(function (array $sample) {
            $sample['is_overdue'] = $this->isOverdue($sample['due_date'] ?? null, $sample['status']);
            $sample['days_left'] = $this->daysUntil($sample['due_date'] ?? null);
            $sample['breakdown'] = json_decode($sample['size_breakdown'] ?? '[]', true);
            return $sample;
        }, $samples);
    }

    public function getDropdownData(): array
    {
        $buyers = $this->db->select('buyers', [
            'buyer_id',
            'buyer_name',
        ], [
            'status' => 'active',
            'ORDER' => ['buyer_name' => 'ASC'],
        ]);

        $styles = $this->db->select('styles', [
            'style_id',
            'style_code',
            'style_name',
            'buyer_id',
        ], [
            'status[!]' => 'cancelled',
            'ORDER' => ['style_code' => 'ASC'],
        ]);

        $sampleTypes = [
            ['value' => 'proto', 'label' => 'Proto Sample'],
            ['value' => 'fit', 'label' => 'Fit Sample'],
            ['value' => 'photo', 'label' => 'Photo Sample'],
            ['value' => 'production', 'label' => 'Production Sample'],
            ['value' => 'size_set', 'label' => 'Size Set'],
        ];

        $statuses = [
            ['value' => 'requested', 'label' => 'Requested'],
            ['value' => 'in_progress', 'label' => 'In Progress'],
            ['value' => 'completed', 'label' => 'Completed'],
            ['value' => 'approved', 'label' => 'Approved'],
            ['value' => 'rejected', 'label' => 'Rejected'],
        ];

        return [
            'buyers' => $buyers,
            'styles' => $styles,
            'sample_types' => $sampleTypes,
            'statuses' => $statuses,
        ];
    }

    public function getSample(int $sampleId): ?array
    {
        $sample = $this->db->get('sampling s', [
            '[>]styles st' => ['s.style_id' => 'style_id'],
        ], [
            's.*',
            'st.style_code',
            'st.style_name',
        ], [
            's.sample_id' => $sampleId,
        ]);

        if (!$sample) {
            return null;
        }

        $sample['is_overdue'] = $this->isOverdue($sample['due_date'], $sample['status']);
        $sample['days_left'] = $this->daysUntil($sample['due_date']);
        $sample['breakdown'] = json_decode($sample['size_breakdown'] ?? '[]', true);

        return $sample;
    }

    public function createSample(array $data, int $userId): int
    {
        $breakdown = $data['size_breakdown'] ?? [];
        $totalQty = 0;
        $sizeLabels = [];

        foreach ($breakdown as $item) {
            if (!empty($item['size'])) {
                $totalQty += (int)($item['qty'] ?? 0);
                $sizeLabels[] = $item['size'];
            }
        }

        $payload = [
            'requisition_no' => $this->generateRequisitionNo(),
            'style_id' => (int) $data['style_id'],
            'sample_type' => $data['sample_type'],
            'sample_size' => implode(', ', $sizeLabels),
            'size_breakdown' => json_encode($breakdown),
            'quantity' => $totalQty > 0 ? $totalQty : (int) ($data['quantity'] ?? 1),
            'request_date' => $data['request_date'],
            'due_date' => $data['due_date'],
            'shell_fabric_available' => isset($data['shell_fabric_available']) && $data['shell_fabric_available'] ? 1 : 0,
            'pocketing_available' => isset($data['pocketing_available']) && $data['pocketing_available'] ? 1 : 0,
            'lining_interlining_available' => isset($data['lining_interlining_available']) && $data['lining_interlining_available'] ? 1 : 0,
            'sewing_accessories_available' => isset($data['sewing_accessories_available']) && $data['sewing_accessories_available'] ? 1 : 0,
            'finishing_accessories_available' => isset($data['finishing_accessories_available']) && $data['finishing_accessories_available'] ? 1 : 0,
            'status' => $data['status'] ?? 'requested',
            'notes' => $data['notes'] ?? '',
            'created_date' => date('Y-m-d H:i:s'),
            'updated_date' => date('Y-m-d H:i:s'),
        ];

        return $this->db->insert('sampling', $payload);
    }

    public function updateSample(int $sampleId, array $data): bool
    {
        $breakdown = $data['size_breakdown'] ?? [];
        $totalQty = 0;
        $sizeLabels = [];

        foreach ($breakdown as $item) {
            if (!empty($item['size'])) {
                $totalQty += (int)($item['qty'] ?? 0);
                $sizeLabels[] = $item['size'];
            }
        }

        $payload = [
            'style_id' => (int) $data['style_id'],
            'sample_type' => $data['sample_type'],
            'sample_size' => implode(', ', $sizeLabels),
            'size_breakdown' => json_encode($breakdown),
            'quantity' => $totalQty > 0 ? $totalQty : (int) ($data['quantity'] ?? 1),
            'request_date' => $data['request_date'],
            'due_date' => $data['due_date'],
            'shell_fabric_available' => isset($data['shell_fabric_available']) && $data['shell_fabric_available'] ? 1 : 0,
            'pocketing_available' => isset($data['pocketing_available']) && $data['pocketing_available'] ? 1 : 0,
            'lining_interlining_available' => isset($data['lining_interlining_available']) && $data['lining_interlining_available'] ? 1 : 0,
            'sewing_accessories_available' => isset($data['sewing_accessories_available']) && $data['sewing_accessories_available'] ? 1 : 0,
            'finishing_accessories_available' => isset($data['finishing_accessories_available']) && $data['finishing_accessories_available'] ? 1 : 0,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? '',
            'updated_date' => date('Y-m-d H:i:s'),
        ];

        return (bool) $this->db->update('sampling', $payload, ['sample_id' => $sampleId]);
    }

    public function updateStatus(int $sampleId, string $status, ?string $notes = null): bool
    {
        $payload = [
            'status' => $status,
            'updated_date' => date('Y-m-d H:i:s'),
        ];

        if ($notes !== null) {
            $payload['notes'] = $notes;
        }

        return (bool) $this->db->update('sampling', $payload, ['sample_id' => $sampleId]);
    }

    public function deleteSample(int $sampleId): bool
    {
        return (bool) $this->db->delete('sampling', ['sample_id' => $sampleId]);
    }

    private function isOverdue(?string $dueDate, string $status): bool
    {
        if (empty($dueDate)) {
            return false;
        }

        if (in_array($status, ['completed', 'approved', 'rejected'], true)) {
            return false;
        }

        return strtotime($dueDate) < strtotime(date('Y-m-d'));
    }

    private function daysUntil(?string $dueDate): ?int
    {
        if (empty($dueDate)) {
            return null;
        }

        $diff = (strtotime($dueDate) - strtotime(date('Y-m-d'))) / 86400;
        return (int) round($diff);
    }

    private function generateRequisitionNo(): string
    {
        $date = date('Ymd');
        $prefix = "SREQ-{$date}-";
        
        $sql = "SELECT MAX(requisition_no) as max_req FROM sampling WHERE requisition_no LIKE ?";
        $stmt = $this->db->query($sql, [$prefix . '%']);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $next = 1;
        if ($result && $result['max_req']) {
            $parts = explode('-', $result['max_req']);
            $next = (int) end($parts) + 1;
        }
        
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
