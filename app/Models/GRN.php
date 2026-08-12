<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class GRN extends Model
{
    protected string $table = 'grns'; // Goods Received Notes
    protected string $primaryKey = 'grn_id';

    protected array $fillable = [
        'grn_number',
        'po_id',
        'vendor_id', // Denormalized for query ease
        'received_date',
        'challan_number', // Vendor's delivery challan
        'received_by', // User ID
        'status', // received, verified
        'remarks',
        'created_at'
    ];
    
    public function items(int $grnId): array
    {
        return $this->db->select('grn_items', '*', ['grn_id' => $grnId]);
    }
}
