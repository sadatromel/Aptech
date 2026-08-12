<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class Transaction extends Model
{
    protected string $table = 'transactions'; // or ledger_entries
    protected string $primaryKey = 'transaction_id';

    protected array $fillable = [
        'transaction_date',
        'voucher_type',
        'voucher_number',
        'account_id',
        'debit',
        'credit',
        'reference', // Invoice #, PO #
        'description',
        'narration',
        'type', // Journal, Payment, Receipt
        'created_by',
        'created_at'
    ];
}
