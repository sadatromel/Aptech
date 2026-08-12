<?php

namespace App\Models;

use Core\Model;

class Chemical extends Model
{
    protected $table = 'chemicals';
    protected $primaryKey = 'chemical_id';
    protected $fillable = [
        'chemical_name',
        'chemical_code',
        'category',
        'supplier',
        'safety_level',
        'storage_requirements',
        'unit_of_measure',
        'min_stock_level',
        'current_stock',
        'unit_cost',
        'description',
        'created_by',
        'updated_at'
    ];
}
