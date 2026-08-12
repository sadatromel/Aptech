<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class GarmentsItem extends Model
{
    protected string $table = 'garments_items';
    protected string $primaryKey = 'item_id';

    protected array $fillable = [
        'item_code',
        'item_name',
        'description',
        'status',
        'created_at',
        'updated_at'
    ];

    /**
     * Get associated type IDs for a garments item
     */
    public function getTypeIds(int $itemId): array
    {
        $results = $this->db->select('product_type_garments_item', ['type_id'], ['item_id' => $itemId]);
        return array_column($results, 'type_id');
    }

    /**
     * Update associated product types
     */
    public function updateTypes(int $itemId, array $typeIds): void
    {
        $this->db->delete('product_type_garments_item', ['item_id' => $itemId]);
        foreach ($typeIds as $typeId) {
            $this->db->insert('product_type_garments_item', [
                'type_id' => (int)$typeId,
                'item_id' => $itemId
            ]);
        }
    }

    /**
     * Get garments items with their comma-separated product type names
     */
    public function getWithTypes(): array
    {
        $items = $this->all();
        foreach ($items as &$item) {
            $query = "SELECT t.type_name 
                      FROM product_type_garments_item ptgi
                      JOIN product_types t ON ptgi.type_id = t.type_id
                      WHERE ptgi.item_id = ?";
            $stmt = $this->db->query($query, [$item['item_id']]);
            $types = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $item['types'] = implode(', ', $types);
        }
        return $items;
    }
}
