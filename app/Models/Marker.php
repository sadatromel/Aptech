<?php

namespace App\Models;

use Core\Model;

class Marker extends Model
{
    protected string $table = 'markers';
    protected string $primaryKey = 'marker_id';
    
    protected array $fillable = [
        'marker_number',
        'plan_id',
        'style_id',
        'marker_type',
        'total_pieces',
        'marker_length',
        'marker_width',
        'fabric_consumption',
        'efficiency',
        'created_date',
        'notes',
        'status',
        'created_by'
    ];
}
