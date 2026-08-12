<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class Country extends Model
{
    protected string $table = 'countries';
    protected string $primaryKey = 'country_id';
    
    protected array $fillable = [
        'country_code',
        'country_name',
        'currency_code',
        'region',
        'status',
        'created_at',
        'updated_at'
    ];
}
