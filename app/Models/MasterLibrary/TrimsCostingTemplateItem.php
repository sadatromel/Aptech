<?php

declare(strict_types=1);

namespace App\Models\MasterLibrary;

use Core\Model;

class TrimsCostingTemplateItem extends Model
{
    protected string $table = 'trims_costing_template_items';
    protected string $primaryKey = 'item_id';
    
    protected array $fillable = [
        'template_id',
        'user_code',
        'trims_group',
        'item_description',
        'consumption_uom',
        'brand_supplier_ref',
        'consumption_per_garment',
        'excess_percentage',
        'total_consumption',
        'purchase_rate',
        'amount',
        'approval_required',
        'supplier',
        'created_at',
        'updated_at'
    ];

    public function getItemsByTemplate(int $templateId): array
    {
        return $this->db->select($this->table, '*', [
            'template_id' => $templateId,
            'ORDER' => ['item_id' => 'ASC']
        ]);
    }

    public function deleteByTemplate(int $templateId): bool
    {
        return $this->db->delete($this->table, ['template_id' => $templateId]);
    }

    public function calculateAmount(float $purchaseRate, float $totalConsumption): float
    {
        return $purchaseRate * $totalConsumption;
    }

    public function calculateTotalConsumption(float $consumptionPerGarment, float $excessPercentage): float
    {
        return $consumptionPerGarment * (1 + ($excessPercentage / 100));
    }
}
