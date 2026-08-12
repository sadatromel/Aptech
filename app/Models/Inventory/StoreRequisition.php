<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Core\Model;

class StoreRequisition extends Model
{
    protected string $table = 'store_requisitions';
    protected string $primaryKey = 'requisition_id';

    protected array $fillable = [
        'requisition_no',
        'location_id',
        'department_id',
        'requisition_date',
        'required_date',
        'reference_no',
        'purpose',
        'status',
        'remarks',
        'requested_by',
        'approved_by',
        'approved_date',
        'created_at',
        'updated_at'
    ];
}
