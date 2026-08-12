<?php

declare(strict_types=1);

namespace App\Models\Visitor;

use Core\Model;

class Visitor extends Model
{
    protected string $table = 'visitors';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name',
        'phone',
        'email',
        'category',
        'company_name',
        'purpose',
        'meet_person',
        'department',
        'id_type',
        'id_number',
        'card_number',
        'photo_path',
        'check_in_time',
        'check_out_time',
        'status',
        'notes',
        'created_at',
        'updated_at'
    ];
}
