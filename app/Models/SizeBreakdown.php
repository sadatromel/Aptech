<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class SizeBreakdown extends Model
{
    protected string $table = 'size_breakdowns';
    protected string $primaryKey = 'breakdown_id';
    protected array $fillable = [
        'style_id',
        'size_range_id',
        'breakdown_name',
        'color',
        'size_quantities',
        'created_at',
        'updated_at',
    ];
}
