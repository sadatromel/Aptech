<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class PurchaseOrder extends Model
{
    protected string $table = 'purchase_orders';
    protected string $primaryKey = 'po_id';

    protected array $fillable = [
        'po_number',
        'vendor_id',
        'order_date',
        'delivery_date',
        'total_amount',
        'currency',
        'status', // draft, sent, partial, completed, cancelled
        'terms_conditions',
        'created_by',
        'created_at',
        'updated_at'
    ];
    
    public function items(int $poId): array
    {
        return $this->db->select('purchase_order_items', '*', [
            'po_id' => $poId
        ]);
    }
}
