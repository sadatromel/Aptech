<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use Core\Model;

class POLine extends Model
{
    protected string $table = 'po_lines';
    protected string $primaryKey = 'po_line_id';
    protected array $fillable = [
        'po_id',
        'item_id',
        'quantity_ordered',
        'quantity_received',
        'unit_price',
        'line_total',
        'status',
        'created_at',
        'updated_at',
    ];
}
