<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class PartsInventory extends Model
{
    protected string $table = 'parts_inventory';
    protected string $primaryKey = 'part_id';
    protected array $fillable = [
        'part_name',
        'part_number',
        'description',
        'quantity_on_hand',
        'min_stock_level',
        'unit_price',
        'supplier',
        'location',
        'status',
        'created_at',
        'updated_at',
    ];
}
