<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class GarmentSampleType extends Model
{
    protected string $table = 'garment_sample_types';
    protected string $primaryKey = 'sample_type_id';

    protected array $fillable = [
        'sample_type_code',
        'sample_type_name',
        'description',
        'status',
        'created_at',
        'updated_at'
    ];
}
