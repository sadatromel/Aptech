<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class PurchaseRequest extends Model
{
    protected string $table = 'purchase_requests';
    protected string $primaryKey = 'pr_id';
    protected array $fillable = [
        'pr_number',
        'style_id',
        'item_name',
        'quantity',
        'unit',
        'unit_price',
        'total_price',
        'supplier',
        'required_date',
        'status',
        'notes',
        'created_date',
        'updated_date',
    ];
}
