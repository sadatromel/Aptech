<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class TrimsCostingTemplate extends Model
{
    protected string $table = 'trims_costing_templates';
    protected string $primaryKey = 'template_id';
    
    protected array $fillable = [
        'template_name',
        'related_buyers',
        'status',
        'created_at',
        'updated_at'
    ];

    public function getWithBuyers(): array
    {
        return $this->db->select($this->table, [
            "[>]buyers" => ["{$this->table}.related_buyers" => "buyers.buyer_id"]
        ], [
            "{$this->table}.template_id",
            "{$this->table}.template_name",
            "{$this->table}.related_buyers",
            "{$this->table}.status",
            "{$this->table}.created_at",
            "{$this->table}.updated_at",
            "buyers.buyer_name"
        ]);
    }

    public function getActiveTemplates(): array
    {
        return $this->db->select($this->table, '*', [
            'status' => 'Active',
            'ORDER' => ['template_name' => 'ASC']
        ]);
    }

    public function getTemplatesByBuyer(int $buyerId): array
    {
        return $this->db->select($this->table, '*', [
            'related_buyers' => $buyerId,
            'status' => 'Active',
            'ORDER' => ['template_name' => 'ASC']
        ]);
    }
}
