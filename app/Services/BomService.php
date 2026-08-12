<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class BomService
{
    public function __construct(private Database $db)
    {
    }

    public function listBoms(array $filters = []): array
    {
        $where = [];

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where['AND']['OR'] = [
                'boms.bom_code[~]' => $term,
                's.style_code[~]' => $term,
                's.style_name[~]' => $term,
            ];
        }

        if (!empty($filters['style_id'])) {
            $where['AND']['boms.style_id'] = $filters['style_id'];
        }

        if (!empty($filters['status'])) {
            $where['AND']['boms.status'] = $filters['status'];
        }

        $where['ORDER'] = ['boms.updated_at' => 'DESC'];

        return $this->db->select('boms', [
            '[>]styles s' => 'style_id'
        ], [
            'boms.bom_id',
            'boms.style_id',
            'boms.bom_code',
            'boms.version',
            'boms.status',
            'boms.remarks',
            'boms.created_at',
            'boms.updated_at',
            's.style_code',
            's.style_name'
        ], $where) ?? [];
    }

    public function getBom(int $bomId): ?array
    {
        $bom = $this->db->get('boms', [
            '[>]styles s' => 'style_id'
        ], [
            'boms.bom_id',
            'boms.style_id',
            'boms.version',
            'boms.status',
            'boms.remarks',
            'boms.created_at',
            'boms.updated_at',
            's.style_code',
            's.style_name'
        ], [
            'boms.bom_id' => $bomId
        ]);

        return $bom ?: null;
    }

    public function createBom(array $data): int
    {
        $styleId = (int) $data['style_id'];
        $version = $data['version'] ?? '1.0';
        
        // Fetch Style Code
        $styleCode = $this->db->get('styles', 'style_code', ['style_id' => $styleId]);
        $bomCode = sprintf("BOM-%s-v%s", $styleCode ?: 'UNK', $version);

        $payload = [
            'style_id' => $styleId,
            'bom_code' => $bomCode,
            'version' => $version,
            'status' => $data['status'] ?? 'draft',
            'remarks' => $data['remarks'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('boms', $payload);
        return (int) $this->db->lastInsertId();
    }

    public function updateBom(int $bomId, array $data): bool
    {
        $payload = [
            'status' => $data['status'],
            'remarks' => $data['remarks'] ?? '',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        
        // Allow updating version? Usually yes for draft.
        if (isset($data['version'])) {
            $payload['version'] = $data['version'];
        }

        return (bool) $this->db->update('boms', $payload, ['bom_id' => $bomId]);
    }

    public function deleteBom(int $bomId): bool
    {
        $this->db->delete('bom_components', ['bom_id' => $bomId]);
        return (bool) $this->db->delete('boms', ['bom_id' => $bomId]);
    }

    public function getStats(): array
    {
        $totalComponents = (int) ($this->db->count('bom_components') ?? 0);
        $activeBoms = (int) ($this->db->count('boms', ['status' => 'approved']) ?? 0); // Corrected table name

        $stylesStmt = $this->db->query("SELECT COUNT(DISTINCT style_id) AS total FROM boms WHERE status != 'archived'"); // Corrected table name
        $stylesWithBom = (int) ($stylesStmt?->fetchColumn() ?: 0);

        return [
            'total_components' => $totalComponents,
            'active_boms' => $activeBoms,
            'styles_with_bom' => $stylesWithBom,
        ];
    }

    public function getComponents(array $filters = []): array
    {
        $sql = "SELECT 
                bc.component_id,
                bc.bom_id,
                bc.material_name,
                bc.material_code,
                bc.quantity,
                bc.unit,
                bc.unit_cost,
                bc.total_cost,
                bc.supplier,
                bc.created_at,
                bc.updated_at,
                b.version AS bom_version,
                b.status AS bom_status,
                s.style_code,
                s.style_name
            FROM bom_components bc
            LEFT JOIN boms b ON bc.bom_id = b.bom_id
            LEFT JOIN styles s ON b.style_id = s.style_id
            WHERE 1=1";

        $params = [];

        if (!empty($filters['bom_id'])) {
            $sql .= " AND bc.bom_id = ?";
            $params[] = $filters['bom_id'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $sql .= " AND (bc.material_name LIKE ? OR bc.material_code LIKE ? OR bc.supplier LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        
        $sql .= " ORDER BY bc.updated_at DESC";

        return $this->db->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getDropdownData(): array
    {
        $styles = $this->db->select('styles', [
            'style_id',
            'style_code',
            'style_name',
            'status',
        ], [
            'status[!]' => 'cancelled',
            'ORDER' => ['style_code' => 'ASC'],
        ]);

        $boms = $this->db->select('boms', [ 
            '[>]styles s' => 'style_id',
        ], [
            'boms.bom_id',
            'boms.bom_code',
            'boms.version',
            'boms.status',
            'boms.created_at',
            's.style_code',
            's.style_name',
        ], [
            'ORDER' => ['boms.created_at' => 'DESC'],
        ]);

        $statuses = [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'approved', 'label' => 'Approved'],
            ['value' => 'archived', 'label' => 'Archived'],
        ];

        return [
            'styles' => $styles,
            'boms' => $boms,
            'statuses' => $statuses,
        ];
    }

    // ... createComponent, updateComponent, deleteComponent remain similar but I will ensure table names match ...
    // Note: I see createComponent uses 'bom_components', correct.
    
    public function createComponent(array $data): int
    {
        $bomId = (int) $data['bom_id'];
        $totalCost = $this->computeTotalCost($data['quantity'], $data['unit_cost']);

        return (int) $this->db->insert('bom_components', [
            'bom_id' => $bomId,
            'material_name' => $data['material_name'],
            'material_code' => $data['material_code'] ?? null,
            'quantity' => (float) $data['quantity'],
            'unit' => $data['unit'] ?? '',
            'unit_cost' => (float) $data['unit_cost'],
            'total_cost' => $totalCost,
            'supplier' => $data['supplier'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'), 
        ]);
    }

    public function updateComponent(int $componentId, array $data): bool
    {
        $totalCost = $this->computeTotalCost($data['quantity'], $data['unit_cost']);

        $updated = $this->db->update('bom_components', [
            'bom_id' => (int) $data['bom_id'],
            'material_name' => $data['material_name'],
            'material_code' => $data['material_code'] ?? null,
            'quantity' => (float) $data['quantity'],
            'unit' => $data['unit'] ?? '',
            'unit_cost' => (float) $data['unit_cost'],
            'total_cost' => $totalCost,
            'supplier' => $data['supplier'] ?? '',
            'updated_at' => date('Y-m-d H:i:s'),
        ], [
            'component_id' => $componentId,
        ]);

        return (bool) $updated;
    }

    public function deleteComponent(int $componentId): bool
    {
        return (bool) $this->db->delete('bom_components', ['component_id' => $componentId]);
    }
    
    // ... keep getComponent ...

    public function getComponent(int $componentId): ?array
    {
        $component = $this->db->get('bom_components bc', [
            '[>]boms b' => ['bc.bom_id' => 'bom_id'],
            '[>]styles s' => ['b.style_id' => 'style_id'],
        ], [
            'bc.component_id',
            'bc.bom_id',
            'bc.material_name',
            'bc.material_code',
            'bc.quantity',
            'bc.unit',
            'bc.unit_cost',
            'bc.total_cost',
            'bc.supplier',
            'bc.created_at',
            'bc.updated_at',
            'b.version(bom_version)',
            'b.status(bom_status)',
            's.style_code',
            's.style_name',
        ], [
            'bc.component_id' => $componentId,
        ]);

        return $component ?: null;
    }

    // ... drop manual getDropdownData if not needed or update it ...
    // I'll keep a simple styles getter for the create form
    public function getStyles(): array 
    {
         return $this->db->select('styles', [
            'style_id',
            'style_code',
            'style_name',
        ], [
            'status[!]' => 'cancelled',
            'ORDER' => ['style_code' => 'ASC'],
        ]) ?? [];
    }

    private function computeTotalCost(mixed $quantity, mixed $unitCost): float
    {
        $qty = (float) $quantity;
        $cost = (float) $unitCost;

        return round($qty * $cost, 2);
    }
}
