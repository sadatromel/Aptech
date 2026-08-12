<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class PackingPlan extends Model
{
    protected string $table = 'packing_plans';
    protected string $primaryKey = 'plan_id';

    protected array $fillable = [
        'plan_code',
        'order_id', // Production Order / SO
        'style_id',
        'target_date',
        'total_cartons',
        'quantity_per_carton',
        'status', // planned, in_progress, completed
        'created_by',
        'created_at'
    ];
}
