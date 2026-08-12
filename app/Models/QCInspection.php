<?php

namespace App\Models;

use Core\Model;

class QCInspection extends Model
{
    protected string $table = 'qc_inspections';
    protected string $primaryKey = 'inspection_id';
    
    protected array $fillable = [
        'inspection_number',
        'order_id',
        'style_id',
        'line_id',
        'inspection_date',
        'inspection_type',
        'inspector_id',
        'total_inspected',
        'passed_quantity',
        'failed_quantity',
        'rework_quantity',
        'remarks',
        'created_by'
    ];
}
