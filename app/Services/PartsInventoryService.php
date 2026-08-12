<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PartsInventory;
use Core\Database;

class PartsInventoryService
{
    public function __construct(private Database $db, private PartsInventory $model)
    {
    }

    public function getStats(): array
    {
        $totalParts = $this->db->query("SELECT COUNT(*) FROM parts_inventory")->fetchColumn();
        
        $lowStockStmt = $this->db->query("
            SELECT COUNT(*) as count 
            FROM parts_inventory 
            WHERE quantity_on_hand <= min_stock_level
        ");
        $lowStock = (int) $lowStockStmt->fetchColumn();

        $totalValueStmt = $this->db->query("
            SELECT SUM(quantity_on_hand * unit_price) as total 
            FROM parts_inventory
        ");
        $totalValue = (float) ($totalValueStmt->fetchColumn() ?: 0);

        return [
            'total' => $totalParts,
            'low_stock' => $lowStock,
            'total_value' => round($totalValue, 2),
        ];
    }

    public function getParts(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(part_name LIKE ? OR part_number LIKE ? OR supplier LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT * 
            FROM parts_inventory 
            $whereClause 
            ORDER BY part_name ASC
        ";

        return $this->db->query($sql, $params)->fetchAll();
    }

    public function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->model->create($data);
    }

    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->model->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->model->delete($id);
    }

    public function findById(int $id): ?array
    {
        return $this->model->find($id);
    }
}
