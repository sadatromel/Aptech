<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class QuotationLine extends Model
{
    protected string $table = 'quotation_lines';
    protected string $primaryKey = 'line_id'; // Assuming line_id

    protected array $fillable = [
        'quotation_id',
        'line_number',
        'style_no',
        'description',
        'quantity',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'delivery_date',
        'remarks'
    ];
}
