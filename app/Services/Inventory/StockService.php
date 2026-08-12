<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use Core\Database;

class StockService
{
    public function __construct(private Database $db)
    {
    }

    public function getStats(): array
    {
        $totalItems = (int) ($this->db->count('stock') ?? 0);
        
        $lowStockSql = "SELECT COUNT(*) as count FROM stock WHERE quantity_on_hand <= reorder_level";
        $result = $this->db->query($lowStockSql);
        $lowStock = (int) ($result->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);
        
        $outOfStockSql = "SELECT COUNT(*) as count FROM stock WHERE quantity_on_hand = 0";
        $result = $this->db->query($outOfStockSql);
        $outOfStock = (int) ($result->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);
        
        $totalValueSql = "SELECT SUM(s.quantity_on_hand * i.unit_price) as total 
                          FROM stock s 
                          LEFT JOIN items i ON s.item_id = i.item_id";
        $result = $this->db->query($totalValueSql);
        $totalValue = (float) ($result->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

        return [
            'total_items' => $totalItems,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'total_value' => $totalValue,
        ];
    }

    public function listStock(array $filters = []): array
    {
        $where = ['ORDER' => ['i.item_name' => 'ASC']];

        if (!empty($filters['warehouse_id'])) {
            $where['s.warehouse_id'] = (int) $filters['warehouse_id'];
        }

        if (!empty($filters['category'])) {
            $where['i.category'] = $filters['category'];
        }

        if (!empty($filters['low_stock']) && $filters['low_stock'] === '1') {
            $where['s.quantity_on_hand[<=]'] = 's.reorder_level';
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where['AND']['OR'] = [
                'i.item_name[~]' => $term,
                'i.item_code[~]' => $term,
            ];
        }

        return $this->db->select('stock s', [
            '[>]items i' => ['s.item_id' => 'item_id'],
            '[>]warehouses w' => ['s.warehouse_id' => 'warehouse_id'],
        ], [
            's.stock_id',
            's.warehouse_id',
            's.item_id',
            's.quantity_on_hand',
            's.quantity_reserved',
            's.quantity_available',
            's.reorder_level',
            's.max_level',
            's.last_stock_date',
            's.updated_at',
            'i.item_name',
            'i.item_code',
            'i.category',
            'i.unit_price',
            'i.unit_of_measure',
            'w.warehouse_name',
            'w.warehouse_code',
        ], $where) ?? [];
    }

    public function getStock(int $stockId): ?array
    {
        return $this->db->get('stock s', [
            '[>]items i' => ['s.item_id' => 'item_id'],
            '[>]warehouses w' => ['s.warehouse_id' => 'warehouse_id'],
        ], [
            's.*',
            'i.item_name',
            'i.item_code',
            'i.category',
            'i.unit_price',
            'i.unit_of_measure',
            'w.warehouse_name',
            'w.warehouse_code',
        ], [
            's.stock_id' => $stockId,
        ]);
    }

    public function adjustStock(int $stockId, array $data, int $userId): bool
    {
        $this->db->beginTransaction();

        try {
            $currentStock = $this->db->get('stock', '*', ['stock_id' => $stockId]);
            if (!$currentStock) {
                throw new \RuntimeException('Stock record not found.');
            }

            $newQuantity = (float) $data['quantity_on_hand'];
            $quantityDiff = $newQuantity - (float) $currentStock['quantity_on_hand'];

            $this->db->update('stock', [
                'quantity_on_hand' => $newQuantity,
                'quantity_available' => $newQuantity - (float) $currentStock['quantity_reserved'],
                'reorder_level' => (float) ($data['reorder_level'] ?? $currentStock['reorder_level']),
                'max_level' => (float) ($data['max_level'] ?? $currentStock['max_level']),
                'last_stock_date' => date('Y-m-d'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['stock_id' => $stockId]);

            // Record stock movement
            if ($quantityDiff != 0) {
                $movementType = $quantityDiff > 0 ? 'in' : 'out';
                $this->db->insert('stock_movements', [
                    'warehouse_id' => (int) $currentStock['warehouse_id'],
                    'item_id' => (int) $currentStock['item_id'],
                    'movement_type' => $movementType,
                    'quantity' => abs($quantityDiff),
                    'reference_type' => 'adjustment',
                    'reference_id' => $stockId,
                    'reference_no' => 'ADJ-' . date('Ymd-His'),
                    'balance_before' => (float) $currentStock['quantity_on_hand'],
                    'balance_after' => $newQuantity,
                    'notes' => $data['notes'] ?? 'Stock adjustment',
                    'created_by' => $userId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function createStock(array $data): int
    {
        $quantityOnHand = (float) ($data['quantity_on_hand'] ?? 0);
        $quantityReserved = (float) ($data['quantity_reserved'] ?? 0);

        $payload = [
            'warehouse_id' => (int) $data['warehouse_id'],
            'item_id' => (int) $data['item_id'],
            'quantity_on_hand' => $quantityOnHand,
            'quantity_reserved' => $quantityReserved,
            'quantity_available' => $quantityOnHand - $quantityReserved,
            'reorder_level' => (float) ($data['reorder_level'] ?? 0),
            'max_level' => (float) ($data['max_level'] ?? 0),
            'last_stock_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return (int) $this->db->insert('stock', $payload);
    }

    public function deleteStock(int $stockId): bool
    {
        return (bool) $this->db->delete('stock', ['stock_id' => $stockId]);
    }

    public function getDropdownData(): array
    {
        $warehouses = $this->db->select('warehouses', [
            'warehouse_id',
            'warehouse_name',
            'warehouse_code',
        ], [
            'status' => 'active',
            'ORDER' => ['warehouse_name' => 'ASC'],
        ]) ?? [];

        $items = $this->db->select('items', [
            'item_id',
            'item_name',
            'item_code',
            'category',
            'unit_of_measure',
        ], [
            'status' => 'active',
            'ORDER' => ['item_name' => 'ASC'],
        ]) ?? [];

        $categories = $this->db->select('items', [
            'category',
        ], [
            'GROUP' => 'category',
            'ORDER' => ['category' => 'ASC'],
        ]) ?? [];

        return [
            'warehouses' => $warehouses,
            'items' => $items,
            'categories' => array_column($categories, 'category'),
        ];
    }
}
