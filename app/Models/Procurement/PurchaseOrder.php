<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use Core\Model;

class PurchaseOrder extends Model
{
    protected string $table = 'purchase_orders';
    protected string $primaryKey = 'po_id';
    protected array $fillable = [
        'po_number',
        'vendor_id',
        'po_date',
        'required_date',
        'payment_terms',
        'delivery_terms',
        'notes',
        'status',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];
}
