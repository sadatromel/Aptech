<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class PurchaseRequestService
{
    public function __construct(private Database $db)
    {
    }

    public function getStats(): array
    {
        $total = (int) ($this->db->count('purchase_requests') ?? 0);
        $pending = (int) ($this->db->count('purchase_requests', ['status' => 'pending']) ?? 0);
        $approved = (int) ($this->db->count('purchase_requests', ['status' => 'approved']) ?? 0);
        
        $totalValue = $this->db->sum('purchase_requests', 'total_price', ['status' => 'approved']) ?? 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'total_value' => (float) $totalValue,
        ];
    }

    public function listPurchaseRequests(array $filters = []): array
    {
        $where = ['ORDER' => ['pr.created_date' => 'DESC']];

        if (!empty($filters['status'])) {
            $where['pr.status'] = $filters['status'];
        }

        if (!empty($filters['style_id'])) {
            $where['pr.style_id'] = (int) $filters['style_id'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where['AND']['OR'] = [
                'pr.pr_number[~]' => $term,
                'pr.item_name[~]' => $term,
                'pr.supplier[~]' => $term,
            ];
        }

        $prs = $this->db->select('purchase_requests pr', [
            '[>]styles st' => ['pr.style_id' => 'style_id'],
        ], [
            'pr.pr_id',
            'pr.pr_number',
            'pr.style_id',
            'pr.item_name',
            'pr.quantity',
            'pr.unit',
            'pr.unit_price',
            'pr.total_price',
            'pr.supplier',
            'pr.required_date',
            'pr.status',
            'pr.notes',
            'pr.created_date',
            'pr.updated_date',
            'st.style_code',
            'st.style_name',
        ], $where) ?? [];

        return $prs;
    }

    public function getDropdownData(): array
    {
        $styles = $this->db->select('styles', [
            'style_id',
            'style_code',
            'style_name',
        ], [
            'status[!]' => 'cancelled',
            'ORDER' => ['style_code' => 'ASC'],
        ]) ?? [];

        $items = $this->db->select('items', [
            'item_id',
            'item_name',
            'category',
        ], [
            'status' => 'active',
            'ORDER' => ['category' => 'ASC', 'item_name' => 'ASC'],
        ]) ?? [];

        $statuses = [
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'approved', 'label' => 'Approved'],
            ['value' => 'rejected', 'label' => 'Rejected'],
            ['value' => 'ordered', 'label' => 'Ordered'],
        ];

        return [
            'styles' => $styles,
            'items' => $items,
            'statuses' => $statuses,
        ];
    }

    public function getPurchaseRequest(int $prId): ?array
    {
        return $this->db->get('purchase_requests pr', [
            '[>]styles st' => ['pr.style_id' => 'style_id'],
        ], [
            'pr.*',
            'st.style_code',
            'st.style_name',
        ], [
            'pr.pr_id' => $prId,
        ]);
    }

    public function createPurchaseRequest(array $data, int $userId): int
    {
        $prNumber = $this->generatePRNumber();
        
        $payload = [
            'pr_number' => $prNumber,
            'style_id' => !empty($data['style_id']) ? (int) $data['style_id'] : null,
            'item_name' => $data['item_name'],
            'quantity' => (float) $data['quantity'],
            'unit' => $data['unit'] ?? '',
            'unit_price' => (float) ($data['unit_price'] ?? 0),
            'total_price' => (float) $data['quantity'] * (float) ($data['unit_price'] ?? 0),
            'supplier' => $data['supplier'] ?? '',
            'required_date' => $data['required_date'],
            'status' => $data['status'] ?? 'pending',
            'notes' => $data['notes'] ?? '',
            'created_date' => date('Y-m-d H:i:s'),
            'updated_date' => date('Y-m-d H:i:s'),
        ];

        return (int) $this->db->insert('purchase_requests', $payload);
    }

    public function updatePurchaseRequest(int $prId, array $data): bool
    {
        $payload = [
            'style_id' => !empty($data['style_id']) ? (int) $data['style_id'] : null,
            'item_name' => $data['item_name'],
            'quantity' => (float) $data['quantity'],
            'unit' => $data['unit'] ?? '',
            'unit_price' => (float) ($data['unit_price'] ?? 0),
            'total_price' => (float) $data['quantity'] * (float) ($data['unit_price'] ?? 0),
            'supplier' => $data['supplier'] ?? '',
            'required_date' => $data['required_date'],
            'status' => $data['status'] ?? 'pending',
            'notes' => $data['notes'] ?? '',
            'updated_date' => date('Y-m-d H:i:s'),
        ];

        return (bool) $this->db->update('purchase_requests', $payload, ['pr_id' => $prId]);
    }

    public function updateStatus(int $prId, string $status): bool
    {
        return (bool) $this->db->update('purchase_requests', [
            'status' => $status,
            'updated_date' => date('Y-m-d H:i:s'),
        ], ['pr_id' => $prId]);
    }

    public function deletePurchaseRequest(int $prId): bool
    {
        return (bool) $this->db->delete('purchase_requests', ['pr_id' => $prId]);
    }

    private function generatePRNumber(): string
    {
        return 'PR-' . date('Y-m') . '-' . str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}
