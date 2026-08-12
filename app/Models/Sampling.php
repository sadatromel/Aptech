<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class Sampling extends Model
{
    protected string $table = 'sampling';
    protected string $primaryKey = 'sample_id';
    protected array $fillable = [
        'style_id',
        'sample_type',
        'sample_size',
        'quantity',
        'request_date',
        'due_date',
        'status',
        'approval_status',
        'notes',
        'feedback',
        'created_date',
        'updated_date',
    ];
}
