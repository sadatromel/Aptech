<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class SewingLine extends Model
{
    protected string $table = 'production_lines'; // Assuming 'production_lines' is used for sewing lines
    protected string $primaryKey = 'production_line_id';

    protected array $fillable = [
        'line_code',
        'factory_id',
        'line_name',
        'line_type',
        'capacity_per_day',
        'manager_id',
        'status', // active, maintenance, inactive
        'created_at',
        'updated_at'
    ];
}
