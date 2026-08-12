<?php

declare(strict_types=1);

namespace App\Models\Transport;

use Core\Model;

class Vehicle extends Model
{
    protected string $table = 'vehicles';
    protected string $primaryKey = 'vehicle_id';

    protected array $fillable = [
        'registration_number', // License plate
        'vehicle_type', // Bus, Car, Van
        'make',
        'model',
        'total_seats', // Seating capacity
        'assigned_driver',
        // 'driver_phone', // Not in DB
        'status', // active, maintenance, inactive
        'created_at',
        // 'updated_at' // Not in DB
    ];

    /**
     * Get all active vehicles
     * 
     * @return array
     */
    public function getActive(): array
    {
        return $this->where('status', 'active')->get();
    }
}
