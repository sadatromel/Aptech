<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class InventoryTransaction extends Model
{
    protected string $table = 'inventory_transactions';
    protected string $primaryKey = 'transaction_id';

    protected array $fillable = [
        'transaction_type', // in, out, adjustment, transfer
        'item_id',
        'quantity',
        'unit_price', // for proper valuation
        'total_value',
        'reference_type', // PO, SO, Requisition
        'reference_id',
        'remarks',
        'transaction_date',
        'created_by',
        'created_at'
    ];
}
