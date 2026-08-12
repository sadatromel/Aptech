<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class SizeRange extends Model
{
    protected string $table = 'size_ranges';
    protected string $primaryKey = 'size_range_id';
    protected array $fillable = [
        'range_name',
        'description',
        'sizes',
        'created_at',
        'updated_at',
    ];
}
