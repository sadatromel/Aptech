<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class SeatAllotment extends Model
{
    protected string $table = 'seat_allotments';
    protected string $primaryKey = 'allotment_id';

    protected array $fillable = [
        'employee_id',
        'vehicle_id',
        'route_id',
        'seat_number',
        'valid_from',
        'valid_to',
        'status',
        'created_by',
        'created_at',
        'updated_at'
    ];
}
