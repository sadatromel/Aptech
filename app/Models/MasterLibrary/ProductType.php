<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class ProductType extends Model
{
    protected string $table = 'product_types';
    protected string $primaryKey = 'type_id';

    protected array $fillable = [
        'type_code',
        'type_name',
        'description',
        'status',
        'created_at',
        'updated_at'
    ];

    /**
     * Get associated category IDs for a type
     */
    public function getCategoryIds(int $typeId): array
    {
        $results = $this->db->select('product_category_type', ['category_id'], ['type_id' => $typeId]);
        return array_column($results, 'category_id');
    }

    /**
     * Update associated categories
     */
    public function updateCategories(int $typeId, array $categoryIds): void
    {
        $this->db->delete('product_category_type', ['type_id' => $typeId]);
        foreach ($categoryIds as $catId) {
            $this->db->insert('product_category_type', [
                'category_id' => (int)$catId,
                'type_id' => $typeId
            ]);
        }
    }

    /**
     * Get types with their comma-separated category names
     */
    public function getWithCategories(): array
    {
        $types = $this->all();
        foreach ($types as &$type) {
            $query = "SELECT c.category_name 
                      FROM product_category_type pct
                      JOIN product_categories c ON pct.category_id = c.category_id
                      WHERE pct.type_id = ?";
            $stmt = $this->db->query($query, [$type['type_id']]);
            $categories = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $type['categories'] = implode(', ', $categories);
        }
        return $types;
    }
}
