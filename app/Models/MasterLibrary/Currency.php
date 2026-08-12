<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class Currency extends Model
{
    protected string $table = 'currencies';
    protected string $primaryKey = 'currency_id';
    
    protected array $fillable = [
        'currency_code',
        'currency_name',
        'symbol',
        'exchange_rate',
        'is_base_currency',
        'status',
        'created_at',
        'updated_at'
    ];
}
