<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Core\Model;

class Stock extends Model
{
    protected string $table = 'stock';
    protected string $primaryKey = 'stock_id';
    protected array $fillable = [
        'warehouse_id',
        'item_id',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_available',
        'reorder_level',
        'max_level',
        'last_stock_date',
        'created_at',
        'updated_at',
    ];
}
