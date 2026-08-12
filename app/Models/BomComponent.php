<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class BomComponent extends Model
{
    protected string $table = 'bom_components';
    protected string $primaryKey = 'component_id';

    protected array $fillable = [
        'bom_id',
        'type', // fabric, trim, packaging, other
        'item_name',
        'item_code',
        'item_description',
        'supplier_id',
        'position', // body, sleeves, etc.
        'consumption',
        'wastage_percent',
        'unit', // kg, m, yard, pc
        'unit_price',
        'currency',
        'total_cost',
        'remarks'
    ];
}
