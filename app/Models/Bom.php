<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class Bom extends Model
{
    protected string $table = 'boms';
    protected string $primaryKey = 'bom_id';

    protected array $fillable = [
        'style_id',
        'version',
        'status', // draft, active, approved
        'is_default',
        'remarks',
        'created_by',
        'approved_by',
        'created_at',
        'updated_at'
    ];
    
    public function components(int $bomId): array
    {
        return $this->db->select('bom_components', '*', [
            'bom_id' => $bomId,
            'ORDER' => ['type' => 'ASC', 'item_name' => 'ASC']
        ]);
    }
}
