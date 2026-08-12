<?php

namespace App\Services;

use Core\Database;

class CartonService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function listCartons(string $limit = '20', string $search = ''): array
    {
        $query = "
            SELECT c.*, pp.plan_number, po.po_number as order_number, s.style_code, s.style_name,
                   CONCAT(e.first_name, ' ', e.last_name) as packed_by_name
            FROM cartons c
            LEFT JOIN packing_plans pp ON c.packing_plan_id = pp.plan_id
            LEFT JOIN production_orders po ON c.order_id = po.po_id
            LEFT JOIN styles s ON c.style_id = s.style_id
            LEFT JOIN employees e ON c.packed_by = e.employee_id
            LEFT JOIN packing_sizes ps ON c.size_name = ps.size_name AND pp.plan_id = ps.plan_id 
        ";

        $params = [];
        if ($search) {
            $query .= " WHERE c.carton_number LIKE ? OR po.po_number LIKE ?";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $query .= " ORDER BY c.packing_date DESC, c.carton_number LIMIT $limit";

        return $this->db->query($query, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCarton(int $id): ?array
    {
        return $this->db->query("
            SELECT c.*, pp.plan_number, po.po_number as order_number, s.style_code, s.style_name, e.first_name, e.last_name
            FROM cartons c
            LEFT JOIN packing_plans pp ON c.packing_plan_id = pp.plan_id
            LEFT JOIN production_orders po ON c.order_id = po.po_id
            LEFT JOIN styles s ON c.style_id = s.style_id
            LEFT JOIN employees e ON c.packed_by = e.employee_id
            WHERE c.carton_id = ?
        ", [$id])->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function createCarton(array $data, int $userId): int
    {
        if (empty($data['cbm'])) {
            $data['cbm'] = ($data['length'] * $data['width'] * $data['height']) / 1000000;
        }

        $this->db->query(
            "INSERT INTO cartons (carton_number, packing_plan_id, order_id, style_id, size_name, quantity, net_weight, gross_weight, length, width, height, cbm, packing_date, packed_by, notes, created_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['carton_number'],
                $data['packing_plan_id'] ?? null,
                $data['order_id'],
                $data['style_id'],
                $data['size_name'],
                $data['quantity'],
                $data['net_weight'],
                $data['gross_weight'],
                $data['length'],
                $data['width'],
                $data['height'],
                $data['cbm'],
                $data['packing_date'],
                $data['packed_by'] ?? null,
                $data['notes'] ?? '',
                $userId
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    public function deleteCarton(int $id): bool
    {
        return (bool)$this->db->query("DELETE FROM cartons WHERE carton_id = ?", [$id]);
    }

    public function getStats(): array
    {
        return [
            'total_cartons' => $this->db->query("SELECT COUNT(*) FROM cartons")->fetchColumn(),
            'total_quantity' => $this->db->query("SELECT SUM(quantity) FROM cartons")->fetchColumn() ?: 0,
            'total_cbm' => $this->db->query("SELECT SUM(cbm) FROM cartons")->fetchColumn() ?: 0,
            'total_weight' => $this->db->query("SELECT SUM(gross_weight) FROM cartons")->fetchColumn() ?: 0
        ];
    }
}
