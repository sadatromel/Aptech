<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class ProductNature extends Model
{
    protected string $table = 'product_natures';
    protected string $primaryKey = 'nature_id';

    protected array $fillable = [
        'nature_code',
        'nature_name',
        'description',
        'status',
        'created_at',
        'updated_at'
    ];

    /**
     * Get associated category IDs for a nature
     */
    public function getCategoryIds(int $natureId): array
    {
        $results = $this->db->select('product_nature_category', ['category_id'], ['nature_id' => $natureId]);
        return array_column($results, 'category_id');
    }

    /**
     * Update associated categories
     */
    public function updateCategories(int $natureId, array $categoryIds): void
    {
        $this->db->delete('product_nature_category', ['nature_id' => $natureId]);
        foreach ($categoryIds as $catId) {
            $this->db->insert('product_nature_category', [
                'nature_id' => $natureId,
                'category_id' => (int)$catId
            ]);
        }
    }

    /**
     * Get natures with their comma-separated category names
     */
    public function getWithCategories(): array
    {
        $natures = $this->all();
        foreach ($natures as &$nature) {
            $query = "SELECT c.category_name 
                      FROM product_nature_category pnc
                      JOIN product_categories c ON pnc.category_id = c.category_id
                      WHERE pnc.nature_id = ?";
            $stmt = $this->db->query($query, [$nature['nature_id']]);
            $categories = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $nature['categories'] = implode(', ', $categories);
        }
        return $natures;
    }
}
