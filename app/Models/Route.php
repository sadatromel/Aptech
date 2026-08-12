<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class Route extends Model
{
    protected string $table = 'routes'; // or 'vehicle_routes'
    protected string $primaryKey = 'route_id';

    protected array $fillable = [
        'route_name',
        'start_location',
        'end_location',
        'distance',
        'duration',
        'start_time',
        'end_time', // Estimated
        'vehicle_id', // Assigned vehicle
        'driver_id', // If driver is a user/employee
        'status', // active, inactive
        'created_by',
        'created_at',
        'updated_at'
    ];
}
