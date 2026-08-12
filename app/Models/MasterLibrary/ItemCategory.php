<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class ItemCategory extends Model
{
    protected string $table = 'item_categories';
    protected string $primaryKey = 'category_id';
    
    protected array $fillable = [
        'category_code',
        'category_name',
        'parent_category_id',
        'description',
        'status',
        'created_at',
        'updated_at'
    ];

    public function getWithParent(int $id): ?array
    {
         // Self-join logic if needed, usually simple select is enough.
         // Let's assume standard retrieval for now.
         return $this->find($id);
    }
}
