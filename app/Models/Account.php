<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

class Account extends Model
{
    protected string $table = 'accounts';
    protected string $primaryKey = 'account_id';

    protected array $fillable = [
        'account_code', // e.g., 1001
        'account_name', // e.g., Cash, Bank, Sales
        'type', // Asset, Liability, Equity, Revenue, Expense
        'is_group', // Boolean
        'parent_id', // Parent Group ID
        'opening_balance',
        'status', // active, inactive
        'created_by',
        'created_at',
        'updated_at'
    ];

    /**
     * Get parent group
     */
    public function parent()
    {
        if (!$this->parent_id) return null;
        return (new Account())->find($this->parent_id);
    }

    /**
     * Get child accounts (sub-groups or ledgers)
     */
    public function children()
    {
        return $this->where('parent_id', $this->account_id)->get();
    }
}
