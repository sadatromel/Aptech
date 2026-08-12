<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class ProductionPlan extends Model
{
    protected string $table = 'production_plans';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'plan_number',
        'line_id',
        'style_id',
        'customer_id',
        'planned_date',
        'quantity',
        'completed_quantity',
        'status',
        'remarks',
        'created_by',
        'created_at',
        'updated_at'
    ];
}
