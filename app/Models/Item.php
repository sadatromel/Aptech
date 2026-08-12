<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class Item extends Model
{
    protected string $table = 'items'; // Or 'stock_items' depending on schema, assuming 'items' implies master
    protected string $primaryKey = 'item_id';

    protected array $fillable = [
        'item_code',
        'item_name',
        'item_type',
        'category',
        'unit_of_measure',
        'min_stock_level',
        'current_stock', // Added via Alter
        'unit_price',
        'supplier_id', // Added via Alter
        'location', // Rack/Bin
        'status', // active, inactive
        'created_by',
        'created_at',
        'updated_at'
    ];
}
