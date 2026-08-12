<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class SalesContract extends Model
{
    protected string $table = 'sales_contracts';
    protected string $primaryKey = 'so_id';

    protected array $fillable = [
        'contract_no',
        'customer_id',
        'contract_date',
        'valid_until',
        'shipping_terms',
        'payment_terms',
        'currency',
        'status',
        'total_amount',
        'notes',
        'created_by',
        'created_at',
        'updated_at'
    ];
}
