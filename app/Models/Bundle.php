<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class Bundle extends Model
{
    protected string $table = 'bundles'; // Legacy likely had 'cutting_bundles' or similar, assuming 'bundles'
    protected string $primaryKey = 'bundle_id';

    protected array $fillable = [
        'bundle_code', // Unique barcode/identifier
        'plan_id',
        'start_serial',
        'end_serial',
        'quantity',
        'size',
        'color',
        'status', // created, in_sewing, completed
        'created_at'
    ];
}
