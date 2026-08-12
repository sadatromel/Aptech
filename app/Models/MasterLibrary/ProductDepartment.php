<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class ProductDepartment extends Model
{
    protected string $table = 'product_departments';
    protected string $primaryKey = 'department_id';
    
    protected array $fillable = [
        'department_code',
        'department_name',
        'description',
        'status',
        'created_at',
        'updated_at'
    ];
}
