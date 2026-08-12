<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class CuttingPlan extends Model
{
    protected string $table = 'cutting_plans';
    protected string $primaryKey = 'plan_id';

    protected array $fillable = [
        'plan_number',
        'style_id',
        'order_id', // production_order_id link
        'planned_date',
        'total_quantity',
        'table_no',
        'status', // domestic, export, completed
        'created_by',
        'created_at',
        'updated_at'
    ];
}
