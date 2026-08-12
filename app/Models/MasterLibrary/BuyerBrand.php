<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class BuyerBrand extends Model
{
    protected string $table = 'buyer_brands';
    protected string $primaryKey = 'brand_id';
    
    protected array $fillable = [
        'buyer_id',
        'brand_name',
        'description',
        'status',
        'created_at',
        'updated_at'
    ];

    public function getWithBuyer(): array
    {
        // Join with buyers table to get buyer name
        return $this->db->select($this->table, [
            "[>]buyers" => ["{$this->table}.buyer_id" => "buyers.buyer_id"]
        ], [
            "{$this->table}.brand_id",
            "{$this->table}.buyer_id",
            "{$this->table}.brand_name",
            "{$this->table}.description",
            "{$this->table}.status",
            "buyers.buyer_name"
        ]);
    }
}
