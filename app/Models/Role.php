<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class Role extends Model
{
    protected string $table = 'roles';
    protected string $primaryKey = 'role_id';

    protected array $fillable = [
        'role_name',
        'description',
        'created_at',
        'updated_at'
    ];
}
