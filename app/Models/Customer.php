<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class Customer extends Model
{
    protected string $table = 'customers';
    protected string $primaryKey = 'id'; // Assuming 'id' based on previous inspection, though legacy had customer_id

    protected array $fillable = [
        'customer_code',
        'name',
        'contact_person',
        'email',
        'phone',
        'alternate_phone',
        'address',
        'city',
        'country',
        'postal_code',
        'website',
        'industry',
        'status',
        'credit_limit',
        'payment_terms',
        'notes',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];
}
