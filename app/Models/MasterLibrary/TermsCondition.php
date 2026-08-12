<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class TermsCondition extends Model
{
    protected string $table = 'commercial_terms_conditions';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'term_code',
        'term_name',
        'term_type',
        'applies_to',
        'description',
        'effective_from',
        'effective_to',
        'is_mandatory',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ];

    /**
     * Allowed term types (mirrors DB ENUM).
     */
    public static function termTypes(): array
    {
        return ['Payment', 'Shipment', 'Delivery', 'Quality', 'Warranty', 'Return', 'General', 'Other'];
    }

    /**
     * Allowed "applies to" values.
     */
    public static function appliesTo(): array
    {
        return ['Buyer', 'Supplier', 'Both'];
    }
}
