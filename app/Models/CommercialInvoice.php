<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class CommercialInvoice extends Model
{
    protected string $table = 'commercial_invoices';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'invoice_number',
        'customer_id',
        'order_id', // Links to ProductionOrder or SalesOrder? Currently ProductionOrder in Controller
        'invoice_date',
        'due_date',
        'total_amount',
        'paid_amount',
        'balance',
        'tax_rate',
        'tax_amount',
        'discount',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];
    
    public function items(int $invoiceId): array
    {
        return $this->db->select('invoice_items', '*', [
            'invoice_id' => $invoiceId,
            'ORDER' => ['id' => 'ASC']
        ]);
    }
    
    public function payments(int $invoiceId): array
    {
         return $this->db->select('invoice_payments', '*', [
            'invoice_id' => $invoiceId,
            'ORDER' => ['payment_date' => 'DESC']
        ]);
    }
}
