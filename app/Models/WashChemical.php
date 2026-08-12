<?php

namespace App\Models;

use Core\Model;

class WashChemical extends Model
{
    protected $table = 'wash_chemicals';
    protected $primaryKey = 'chemical_id'; // This might be auto-increment or just part of relation
    protected $fillable = [
        'batch_id',
        'chemical_name',
        'quantity',
        'unit'
    ];
}
