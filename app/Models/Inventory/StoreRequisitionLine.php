<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Core\Model;

class StoreRequisitionLine extends Model
{
    protected string $table = 'store_requisition_lines';
    protected string $primaryKey = 'line_id';

    protected array $fillable = [
        'requisition_id',
        'item_id',
        'quantity_requested',
        'quantity_approved',
        'quantity_issued',
        'unit_cost',
        // 'total_cost' is a generated column in MySQL, usually shouldn't insert it.
        'remarks',
        'created_at'
    ];
}
