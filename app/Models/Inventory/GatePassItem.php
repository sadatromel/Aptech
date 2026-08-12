<?php

namespace App\Models\Inventory;

use Core\Model;

class GatePassItem extends Model
{
    protected string $table = 'gate_pass_items';

    protected array $fillable = [
        'gate_pass_id',
        'buyer_id',
        'style_id',
        'allocation_no',
        'item_category_id',
        'description',
        'color',
        'size',
        'unit',
        'quantity',
        'weight'
    ];
}
