<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class Vendor extends Model
{
    protected string $table = 'vendors';
    protected string $primaryKey = 'vendor_id';

    protected array $fillable = [
        'vendor_name',
        'contact_person',
        'email',
        'phone',
        'address',
        'tax_id',
        'category', // e.g., Fabric, Trim, Service
        'payment_terms',
        'status', // active, inactive
        'created_by',
        'created_at',
        'updated_at'
    ];
}
