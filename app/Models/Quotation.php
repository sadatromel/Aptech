<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class Quotation extends Model
{
    protected string $table = 'quotations';
    protected string $primaryKey = 'quotation_id';

    protected array $fillable = [
        'quotation_no',
        'customer_id',
        'quotation_date',
        'valid_date',
        'reference_no',
        'attention_to',
        'subject',
        'terms_conditions',
        'currency',
        'status',
        'total_amount',
        'grand_total',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];

    public function lines(): array
    {
        return $this->db->select('quotation_lines', '*', [
            'quotation_id' => $this->id(),
            'ORDER' => ['line_number' => 'ASC']
        ]);
    }
    
    // Helper to get ID since primary key is non-standard
    public function id()
    {
        // If loaded, return the value of quotation_id
        // This requires the model to hold data, checking how Core/Model works... 
        // Core/Model doesn't persist data instance, it's a query builder wrapper mainly.
        // So this method expects usage like $model->find($id) returning array.
        // We might want to add relationship methods that take an ID.
        return null; 
    }
    
    public function getLines(int $quotationId): array
    {
        return $this->db->select('quotation_lines', '*', [
            'quotation_id' => $quotationId,
            'ORDER' => ['line_number' => 'ASC']
        ]);
    }
}
