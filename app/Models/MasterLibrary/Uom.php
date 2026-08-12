<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class Uom extends Model
{
    protected string $table = 'uoms';
    protected string $primaryKey = 'uom_id';

    protected array $fillable = [
        'uom_code',
        'uom_name',
        'description',
        'status',
        'created_at',
        'updated_at'
    ];
}
