<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class Carton extends Model
{
    protected string $table = 'cartons';
    protected string $primaryKey = 'carton_id';

    protected array $fillable = [
        'carton_number', // C001, C002
        'manual_barcode',
        'plan_id',
        'style_id',
        'size_breakdown', // JSON or string: S=2, M=4
        'total_quantity',
        'weight',
        'dimension',
        'status', // packed, qc_passed, shipped
        'created_by',
        'created_at'
    ];
}
