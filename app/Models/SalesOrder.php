<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class SalesOrder extends Model
{
    protected string $table = 'sales_orders';
    protected string $primaryKey = 'so_id';

    protected array $fillable = [
        'so_no',
        'quotation_id',
        'customer_id',
        'order_date',
        'delivery_date',
        'reference_no',
        'attention_to',
        'shipping_address',
        'billing_address',
        'terms_conditions',
        'currency',
        'status',
        'total_amount',
        'grand_total',
        'total_production_orders',
        'contract_type', // single or multiple
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];
    
    public function getLines(int $soId): array
    {
        return $this->db->select('sales_order_lines', '*', [
            'so_id' => $soId,
            'ORDER' => ['line_number' => 'ASC']
        ]);
    }
}
