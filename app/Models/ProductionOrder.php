<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class ProductionOrder extends Model
{
    protected string $table = 'production_orders';
    protected string $primaryKey = 'id'; // Keeping generic 'id' or 'so_id' depending on schema, likely 'id' or 'po_id'

    protected array $fillable = [
        'order_number',
        'customer_id',
        'style_id', // If linked directly
        'order_date',
        'delivery_date',
        'quantity',
        'total_value',
        'currency_id',
        'conversion_rate',
        'status', // planned, confirmed, in_production, shipped
        'invoiced', // 0 or 1
        'invoice_id',
        'created_by',
        'created_at',
        'updated_at'
    ];
}
