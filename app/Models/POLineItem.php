<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class POLineItem extends Model
{
    protected string $table = 'po_line_items';
    protected string $primaryKey = 'line_id';
    protected array $fillable = [
        'po_id',
        'color',
        'inseam',
        'size',
        'quantity_ordered',
        'quantity_produced',
        'unit_price',
        'status',
        'created_at',
        'updated_at',
    ];
}
