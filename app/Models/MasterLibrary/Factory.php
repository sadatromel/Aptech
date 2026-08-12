<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class Factory extends Model
{
    protected string $table = 'factories';
    protected string $primaryKey = 'factory_id';
    
    protected array $fillable = [
        'factory_code',
        'factory_name',
        'address',
        'city',
        'country',
        'contact_person',
        'phone',
        'email',
        'timezone',
        'currency',
        'status',
        'created_at',
        'updated_at'
    ];
}
