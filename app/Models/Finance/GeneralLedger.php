<?php

declare(strict_types=1);

namespace App\Models\Finance;

use Core\Model;

class GeneralLedger extends Model
{
    protected string $table = 'general_ledger';
    protected string $primaryKey = 'ledger_id';
    protected array $fillable = [
        'transaction_date',
        'account_id',
        'debit_amount',
        'credit_amount',
        'reference_type',
        'reference_id',
        'description',
        'cost_center_id',
        'created_by',
        'created_at',
        'updated_at',
    ];
}
