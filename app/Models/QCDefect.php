<?php

namespace App\Models;

use Core\Model;

class QCDefect extends Model
{
    protected string $table = 'qc_defects';
    protected string $primaryKey = 'defect_id';
    
    protected array $fillable = [
        'inspection_id',
        'defect_type',
        'defect_quantity',
        'severity'
    ];
}
