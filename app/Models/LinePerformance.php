<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class LinePerformance extends Model
{
    protected string $table = 'line_performance';
    protected string $primaryKey = 'performance_id';

    protected array $fillable = [
        'line_id',
        'date',
        'hour', // 1-24 or 'Shift A' etc
        'style_id',
        'produced_quantity',
        'target_quantity',
        'efficiency',
        'defects',
        'remarks',
        'created_by',
        'created_at'
    ];
}
