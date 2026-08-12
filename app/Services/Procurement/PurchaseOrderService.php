<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use Core\Database;

class PurchaseOrderService
{
    public function __construct(private Database $db)
    {
    }

    public function getStats(): array
    {
        $total = (int) ($this->db->count('purchase_orders') ?? 0);
        $draft = (int) ($this->db->count('purchase_orders', ['status' => 'draft']) ?? 0);
        $submitted = (int) ($this->db->count('purchase_orders', ['status' => 'submitted']) ?? 0);
        $confirmed = (int) ($this->db->count('purchase_orders', ['status' => 'confirmed']) ?? 0);

        $totalValueSql = "SELECT SUM(po_l.line_total) as total 
                          FROM po_lines po_l 
                          LEFT JOIN purchase_orders po ON po_l.po_id = po.po_id 
                          WHERE po.status IN ('confirmed', 'received', 'closed')";
        $result = $this->db->query($totalValueSql);
        $totalValue = $result->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;

        return [
            'total' => $total,
            'draft' => $draft,
            'submitted' => $submitted,
            'confirmed' => $confirmed,
            'total_value' => (float) $totalValue,
        ];
    }

    public function listPurchaseOrders(array $filters = []): array
    {
        $where = ['ORDER' => ['po.po_date' => 'DESC']];

        if (!empty($filters['status'])) {
            $where['po.status'] = $filters['status'];
        }

        if (!empty($filters['vendor_id'])) {
            $where['po.vendor_id'] = (int) $filters['vendor_id'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where['AND']['OR'] = [
                'po.po_number[~]' => $term,
                'v.vendor_name[~]' => $term,
            ];
        }

        $pos = $this->db->select('purchase_orders po', [
            '[>]vendors v' => ['po.vendor_id' => 'vendor_id'],
            '[>]users u' => ['po.created_by' => 'user_id'],
        ], [
            'po.po_id',
            'po.po_number',
            'po.vendor_id',
            'po.po_date',
            'po.required_date',
            'po.payment_terms',
            'po.delivery_terms',
            'po.notes',
            'po.status',
            'po.created_at',
            'po.updated_at',
            'v.vendor_name',
            'v.vendor_code',
            'u.first_name',
            'u.last_name',
        ], $where) ?? [];

        return array_map(function ($po) {
            $po['created_by_name'] = trim(($po['first_name'] ?? '') . ' ' . ($po['last_name'] ?? ''));
            $linesSql = "SELECT COUNT(*) as count, SUM(line_total) as total 
                         FROM po_lines WHERE po_id = ?";
            $stmt = $this->db->query($linesSql, [$po['po_id']]);
            $lines = $stmt->fetch(\PDO::FETCH_ASSOC);
            $po['item_count'] = (int) ($lines['count'] ?? 0);
            $po['total_amount'] = (float) ($lines['total'] ?? 0);
            return $po;
        }, $pos);
    }

    public function getDropdownData(): array
    {
        $vendors = $this->db->select('vendors', [
            'vendor_id',
            'vendor_name',
            'vendor_code',
        ], [
            'status' => 'active',
            'ORDER' => ['vendor_name' => 'ASC'],
        ]) ?? [];

        $items = $this->db->select('items', [
            'item_id',
            'item_name',
            'category',
            'unit_price',
        ], [
            'status' => 'active',
            'ORDER' => ['category' => 'ASC', 'item_name' => 'ASC'],
        ]) ?? [];

        $statuses = [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'submitted', 'label' => 'Submitted'],
            ['value' => 'confirmed', 'label' => 'Confirmed'],
            ['value' => 'partial_received', 'label' => 'Partial Received'],
            ['value' => 'received', 'label' => 'Received'],
            ['value' => 'closed', 'label' => 'Closed'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
        ];

        return [
            'vendors' => $vendors,
            'items' => $items,
            'statuses' => $statuses,
        ];
    }

    public function getPurchaseOrder(int $poId): ?array
    {
        $po = $this->db->get('purchase_orders po', [
            '[>]vendors v' => ['po.vendor_id' => 'vendor_id'],
            '[>]users u' => ['po.created_by' => 'user_id'],
        ], [
            'po.*',
            'v.vendor_name',
            'v.vendor_code',
            'u.first_name',
            'u.last_name',
        ], [
            'po.po_id' => $poId,
        ]);

        if (!$po) {
            return null;
        }

        $po['created_by_name'] = trim(($po['first_name'] ?? '') . ' ' . ($po['last_name'] ?? ''));

        $po['lines'] = $this->db->select('po_lines pl', [
            '[>]items i' => ['pl.item_id' => 'item_id'],
        ], [
            'pl.*',
            'i.item_name',
            'i.category',
        ], [
            'pl.po_id' => $poId,
            'ORDER' => ['pl.po_line_id' => 'ASC'],
        ]) ?? [];

        return $po;
    }

    public function createPurchaseOrder(array $data, int $userId): int
    {
        $this->db->beginTransaction();

        try {
            $poNumber = $this->generatePONumber();
            
            $payload = [
                'po_number' => $poNumber,
                'vendor_id' => (int) $data['vendor_id'],
                'po_date' => $data['po_date'],
                'required_date' => $data['required_date'],
                'payment_terms' => $data['payment_terms'] ?? '',
                'delivery_terms' => $data['delivery_terms'] ?? '',
                'notes' => $data['notes'] ?? '',
                'status' => $data['status'] ?? 'draft',
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $poId = (int) $this->db->insert('purchase_orders', $payload);

            $lines = $data['lines'] ?? [];
            foreach ($lines as $line) {
                if (empty($line['item_id']) || ($line['quantity'] ?? 0) <= 0) {
                    continue;
                }

                $quantity = (float) $line['quantity'];
                $unitPrice = (float) ($line['unit_price'] ?? 0);
                $lineTotal = $quantity * $unitPrice;

                $this->db->insert('po_lines', [
                    'po_id' => $poId,
                    'item_id' => (int) $line['item_id'],
                    'quantity_ordered' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->db->commit();
            return $poId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updatePurchaseOrder(int $poId, array $data): bool
    {
        $this->db->beginTransaction();

        try {
            $payload = [
                'vendor_id' => (int) $data['vendor_id'],
                'po_date' => $data['po_date'],
                'required_date' => $data['required_date'],
                'payment_terms' => $data['payment_terms'] ?? '',
                'delivery_terms' => $data['delivery_terms'] ?? '',
                'notes' => $data['notes'] ?? '',
                'status' => $data['status'] ?? 'draft',
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $this->db->update('purchase_orders', $payload, ['po_id' => $poId]);

            $this->db->delete('po_lines', ['po_id' => $poId]);

            $lines = $data['lines'] ?? [];
            foreach ($lines as $line) {
                if (empty($line['item_id']) || ($line['quantity'] ?? 0) <= 0) {
                    continue;
                }

                $quantity = (float) $line['quantity'];
                $unitPrice = (float) ($line['unit_price'] ?? 0);
                $lineTotal = $quantity * $unitPrice;

                $this->db->insert('po_lines', [
                    'po_id' => $poId,
                    'item_id' => (int) $line['item_id'],
                    'quantity_ordered' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $poId, string $status): bool
    {
        return (bool) $this->db->update('purchase_orders', [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['po_id' => $poId]);
    }

    public function deletePurchaseOrder(int $poId): bool
    {
        $this->db->beginTransaction();
        try {
            $this->db->delete('po_lines', ['po_id' => $poId]);
            $this->db->delete('purchase_orders', ['po_id' => $poId]);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function generatePONumber(): string
    {
        return 'PO-' . date('Y-m') . '-' . str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}
