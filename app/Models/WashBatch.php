<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class WashBatch extends Model
{
    protected string $table = 'wash_batches';
    protected string $primaryKey = 'batch_id';

    protected array $fillable = [
        'batch_no',
        'style_id',
        'quantity',
        'recipe_id', // Chemical recipe
        'machine_no',
        'start_time',
        'end_time',
        'status', // scheduled, washing, drying, completed
        'remarks',
        'created_by',
        'created_at'
    ];
}
