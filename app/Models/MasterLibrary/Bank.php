<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class Bank extends Model
{
    protected string $table = 'bank_information';
    protected string $primaryKey = 'bank_id';
    
    protected array $fillable = [
        'bank_name',
        'bank_code',
        'account_number',
        'account_title',
        'account_type',
        'branch_name',
        'branch_code',
        'iban',
        'swift_code',
        'address',
        'currency',
        'bank_status',
        'created_by',
        'created_at',
        'updated_at'
    ];
}
