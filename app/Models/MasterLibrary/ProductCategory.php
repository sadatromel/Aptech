<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class ProductCategory extends Model
{
    protected string $table = 'product_categories';
    protected string $primaryKey = 'category_id';
    
    protected array $fillable = [
        'category_code',
        'category_name',
        'description',
        'status',
        'created_at',
        'updated_at'
    ];

    /**
     * Get associated nature IDs for a category
     */
    public function getNatureIds(int $categoryId): array
    {
        $results = $this->db->select('product_nature_category', ['nature_id'], ['category_id' => $categoryId]);
        return array_column($results, 'nature_id');
    }

    /**
     * Update associated natures
     */
    public function updateNatures(int $categoryId, array $natureIds): void
    {
        $this->db->delete('product_nature_category', ['category_id' => $categoryId]);
        foreach ($natureIds as $natureId) {
            $this->db->insert('product_nature_category', [
                'category_id' => $categoryId,
                'nature_id' => (int)$natureId
            ]);
        }
    }

    /**
     * Get categories with their comma-separated nature names
     */
    public function getWithNatures(): array
    {
        $categories = $this->all();
        foreach ($categories as &$category) {
            $query = "SELECT n.nature_name 
                      FROM product_nature_category pnc
                      JOIN product_natures n ON pnc.nature_id = n.nature_id
                      WHERE pnc.category_id = ?";
            $stmt = $this->db->query($query, [$category['category_id']]);
            $natures = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $category['natures'] = implode(', ', $natures);
        }
        return $categories;
    }
}
