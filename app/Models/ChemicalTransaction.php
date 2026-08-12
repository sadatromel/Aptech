<?php

namespace App\Models;

use Core\Model;

class ChemicalTransaction extends Model
{
    protected $table = 'chemical_transactions';
    protected $primaryKey = 'transaction_id';
    protected $fillable = [
        'chemical_id',
        'transaction_type',
        'quantity_change',
        'previous_stock',
        'new_stock',
        'notes',
        'created_by'
    ];
}
