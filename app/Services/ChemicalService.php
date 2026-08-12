<?php

namespace App\Services;

use Core\Database;

class ChemicalService
{
    public function __construct(private Database $db)
    {
    }

    public function listChemicals(array $filters = []): array
    {
        $sql = "
            SELECT c.*, 
                   CASE 
                       WHEN c.current_stock <= 0 THEN 'Out of Stock'
                       WHEN c.current_stock <= c.min_stock_level THEN 'Low Stock'
                       ELSE 'In Stock'
                   END as stock_status,
                   (c.current_stock * c.unit_cost) as total_value
            FROM chemicals c
            WHERE 1=1
        ";

        $params = [];
        if (!empty($filters['search'])) {
            $sql .= " AND (c.chemical_name LIKE ? OR c.chemical_code LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $sql .= " ORDER BY c.chemical_name";

        return $this->db->query($sql, $params)->fetchAll();
    }

    public function getChemical(int $id): ?array
    {
        return $this->db->get('chemicals', null, '*', ['chemical_id' => $id]);
    }

    public function createChemical(array $data): int
    {
        $payload = [
            'chemical_name' => $data['chemical_name'],
            'chemical_code' => $data['chemical_code'] ?? '',
            'category' => $data['category'],
            'supplier' => $data['supplier'] ?? '',
            'safety_level' => $data['safety_level'],
            'storage_requirements' => $data['storage_requirements'] ?? '',
            'unit_of_measure' => $data['unit_of_measure'],
            'min_stock_level' => (float)($data['min_stock_level'] ?? 0),
            'current_stock' => (float)$data['current_stock'],
            'unit_cost' => (float)$data['unit_cost'],
            'description' => $data['description'] ?? '',
            'created_by' => $_SESSION['user_id'] ?? 1
        ];

        $this->db->insert('chemicals', $payload);
        return (int)$this->db->lastInsertId();
    }

    public function updateStock(int $id, float $newStock, string $type, string $notes): bool
    {
        $this->db->beginTransaction();
        try {
            // Get current stock
            $current = $this->db->query("SELECT current_stock FROM chemicals WHERE chemical_id = ?", [$id])->fetchColumn();
            $currentStock = (float)$current;

            // Update stock
            $this->db->update('chemicals', [
                'current_stock' => $newStock,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['chemical_id' => $id]);

            // Create transaction
            $this->db->insert('chemical_transactions', [
                'chemical_id' => $id,
                'transaction_type' => $type,
                'quantity_change' => $newStock - $currentStock,
                'previous_stock' => $currentStock,
                'new_stock' => $newStock,
                'notes' => $notes,
                'created_by' => $_SESSION['user_id'] ?? 1
            ]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteChemical(int $id): bool
    {
        $this->db->delete('chemical_transactions', ['chemical_id' => $id]);
        return (bool)$this->db->delete('chemicals', ['chemical_id' => $id]);
    }

    public function getRecentTransactions(): array
    {
        return $this->db->query("
            SELECT ct.*, c.chemical_name, c.unit_of_measure,
                   CONCAT(u.first_name, ' ', u.last_name) as created_by_name
            FROM chemical_transactions ct
            LEFT JOIN chemicals c ON ct.chemical_id = c.chemical_id
            LEFT JOIN users u ON ct.created_by = u.user_id
            ORDER BY ct.created_at DESC
            LIMIT 10
        ")->fetchAll();
    }

    public function getStats(): array
    {
        return [
            'total_chemicals' => (int)$this->db->count('chemicals'),
            'low_stock' => (int)$this->db->query("SELECT COUNT(*) FROM chemicals WHERE current_stock <= min_stock_level")->fetchColumn(),
            'out_of_stock' => (int)$this->db->query("SELECT COUNT(*) FROM chemicals WHERE current_stock <= 0")->fetchColumn(),
            'total_value' => (float)$this->db->query("SELECT SUM(current_stock * unit_cost) FROM chemicals")->fetchColumn()
        ];
    }
}
