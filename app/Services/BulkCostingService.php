<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

class BulkCostingService
{
    public function __construct(private Database $db)
    {
    }

    public function getStats(): array
    {
        return [
            'total' => (int) ($this->db->count('costing_sheets') ?? 0),
            'active' => (int) ($this->db->count('costing_sheets', ['status' => 'approved']) ?? 0),
            'draft' => (int) ($this->db->count('costing_sheets', ['status' => 'draft']) ?? 0),
        ];
    }

    public function listCostings(array $filters = []): array
    {
        $where = [];

        if (!empty($filters['status'])) {
            $where['cs.status'] = $filters['status'];
        }

        if (!empty($filters['style_id'])) {
            $where['cs.style_id'] = $filters['style_id'];
        }

        if (!empty($filters['search'])) {
            $where['OR'] = [
                'cs.costing_name[~]' => $filters['search'],
                's.style_code[~]' => $filters['search'],
                'b.buyer_name[~]' => $filters['search'],
            ];
        }

        $where['ORDER'] = ['cs.costing_id' => 'DESC'];

        return $this->db->select('costing_sheets cs', [
            '[>]styles s' => ['cs.style_id' => 'style_id'],
            '[>]buyers b' => ['cs.buyer_id' => 'buyer_id'],
            '[>]factories f' => ['cs.factory_id' => 'factory_id'],
        ], [
            'cs.*',
            's.style_code',
            's.style_name',
            'b.buyer_name',
            'f.factory_name',
        ], $where) ?? [];
    }

    public function getDropdownData(): array
    {
        $styles = $this->db->select('styles', [
            'style_id',
            'style_code',
            'style_name',
            'buyer_id'
        ], [
            'status[!]' => 'cancelled',
            'ORDER' => ['style_code' => 'ASC'],
        ]) ?? [];

        $seasons = $this->db->select('styles', 'season', [
            'season[!]' => '',
            'GROUP' => 'season',
            'ORDER' => ['season' => 'DESC'],
        ]) ?? [];

        return [
            'styles' => $styles,
            'seasons' => array_filter($seasons),
            'currencies' => [
                ['value' => 'USD', 'label' => 'USD - US Dollar'],
                ['value' => 'EUR', 'label' => 'EUR - Euro'],
                ['value' => 'GBP', 'label' => 'GBP - British Pound'],
                ['value' => 'BDT', 'label' => 'BDT - Bangladeshi Taka'],
            ],
            'buyers' => $this->db->select('buyers', ['buyer_id', 'buyer_name'], ['ORDER' => 'buyer_name']),
            'factories' => $this->db->select('factories', ['factory_id', 'factory_name'], ['status' => 'active', 'ORDER' => 'factory_name'])
        ];
    }

    public function createCosting(array $data, int $userId): int
    {
        $this->db->beginTransaction();
        try {
            $sheetData = [
                'factory_id' => $data['factory_id'],
                'costing_name' => $data['costing_name'] ?? null,
                'style_id' => $data['style_id'],
                'buyer_id' => $data['buyer_id'] ?? null,
                'allocation_no' => $data['allocation_no'] ?? '',
                'sc_no' => $data['sc_no'] ?? '',
                'job_no' => $data['job_no'] ?? '',
                'costing_date' => $data['costing_date'] ?? date('Y-m-d'),
                'local_commission_pct' => $data['local_commission_pct'] ?? 0,
                'foreign_commission_pct' => $data['foreign_commission_pct'] ?? 0,
                'early_payment_cost_pct' => $data['early_payment_cost_pct'] ?? 0,
                'order_value' => $data['order_value'] ?? 0,
                'total_costing_value' => $data['total_costing_value'] ?? 0,
                'profit_amount' => $data['profit_amount'] ?? 0,
                'status' => 'draft',
                'created_by' => $userId
            ];

            $costingId = $this->db->insert('costing_sheets', $sheetData);
            if (!$costingId) { $costingId = (int) $this->db->lastInsertId(); }

            // Save Style Items
            if (!empty($data['styles'])) {
                foreach ($data['styles'] as $style) {
                    if (empty($style['order_no'])) continue;
                    $this->db->insert('costing_styles', [
                        'costing_id' => $costingId,
                        'style_id' => $style['style_id'] ?? $data['style_id'],
                        'order_no' => $style['order_no'],
                        'quantity' => $style['quantity'] ?? 0,
                        'unit_price' => $style['unit_price'] ?? 0,
                        'shipment_date' => $style['shipment_date'] ?: null
                    ]);
                }
            }

            // Save Items (Fabric, Accs, Wash, Embl, Other)
            $itemMap = [
                'fabric' => $data['fabrics'] ?? $data['fabric'] ?? [],
                'accessory' => $data['accessories'] ?? $data['accessorys'] ?? $data['accessory'] ?? [],
                'wash' => $data['wash_costs'] ?? $data['washs'] ?? $data['washes'] ?? $data['wash'] ?? [],
                'embl' => $data['embl_costs'] ?? $data['embls'] ?? $data['embl'] ?? [],
                'other' => $data['other_costs'] ?? $data['others'] ?? $data['other'] ?? []
            ];

            foreach ($itemMap as $type => $itemList) {
                if (!empty($itemList) && is_array($itemList)) {
                    foreach ($itemList as $item) {
                        $itemData = [
                            'costing_id' => $costingId,
                            'item_type' => $type,
                            'item_name' => $item['item_name'] ?? ($item['embl_name'] ?? ($item['wash_type'] ?? null)),
                            'description' => $item['description'] ?? null,
                            'fabric_ref' => $item['fabric_ref'] ?? null,
                            'full_width' => $item['full_width'] ?? null,
                            'cutable_width' => $item['cutable_width'] ?? null,
                            'uom' => $item['uom'] ?? null,
                            'material_source' => $item['material_source'] ?? null,
                            'consumption' => $item['consumption'] ?? 0,
                            'quantity' => $item['quantity'] ?? 0,
                            'unit_price' => $item['unit_price'] ?? 0,
                            'supplier_name' => $item['supplier_name'] ?? null,
                            'remarks' => $item['remarks'] ?? null,
                            'wash_type' => $item['wash_type'] ?? null,
                            'color' => $item['color'] ?? null,
                            'embl_name' => $item['embl_name'] ?? null,
                            'embl_type' => $item['embl_type'] ?? null,
                        ];
                        $this->db->insert('costing_items', $itemData);
                    }
                }
            }
            $this->db->commit();
            return $costingId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateCosting(int $costingId, array $data, int $userId): bool
    {
        $this->db->beginTransaction();
        try {
            $sheetData = [
                'factory_id' => $data['factory_id'],
                'costing_name' => $data['costing_name'] ?? null,
                'style_id' => $data['style_id'],
                'buyer_id' => $data['buyer_id'] ?? null,
                'allocation_no' => $data['allocation_no'] ?? '',
                'sc_no' => $data['sc_no'] ?? '',
                'job_no' => $data['job_no'] ?? '',
                'costing_date' => $data['costing_date'] ?? date('Y-m-d'),
                'local_commission_pct' => $data['local_commission_pct'] ?? 0,
                'foreign_commission_pct' => $data['foreign_commission_pct'] ?? 0,
                'early_payment_cost_pct' => $data['early_payment_cost_pct'] ?? 0,
                'order_value' => $data['order_value'] ?? 0,
                'total_costing_value' => $data['total_costing_value'] ?? 0,
                'profit_amount' => $data['profit_amount'] ?? 0,
            ];

            if (!empty($data['status'])) {
                $sheetData['status'] = $data['status'];
            }

            $this->db->update('costing_sheets', $sheetData, ['costing_id' => $costingId]);

            // Clear old details
            $this->db->delete('costing_styles', ['costing_id' => $costingId]);
            $this->db->delete('costing_items', ['costing_id' => $costingId]);

            // Re-insert Style Items
            if (!empty($data['styles'])) {
                foreach ($data['styles'] as $style) {
                    if (empty($style['order_no'])) continue;
                    $this->db->insert('costing_styles', [
                        'costing_id' => $costingId,
                        'style_id' => $style['style_id'] ?? $data['style_id'],
                        'order_no' => $style['order_no'],
                        'quantity' => $style['quantity'] ?? 0,
                        'unit_price' => $style['unit_price'] ?? 0,
                        'shipment_date' => $style['shipment_date'] ?: null
                    ]);
                }
            }

            // Re-insert Items
            $itemMap = [
                'fabric' => $data['fabrics'] ?? $data['fabric'] ?? [],
                'accessory' => $data['accessories'] ?? $data['accessorys'] ?? $data['accessory'] ?? [],
                'wash' => $data['wash_costs'] ?? $data['washs'] ?? $data['washes'] ?? $data['wash'] ?? [],
                'embl' => $data['embl_costs'] ?? $data['embls'] ?? $data['embl'] ?? [],
                'other' => $data['other_costs'] ?? $data['others'] ?? $data['other'] ?? []
            ];

            foreach ($itemMap as $type => $itemList) {
                if (!empty($itemList) && is_array($itemList)) {
                    foreach ($itemList as $item) {
                        $itemData = [
                            'costing_id' => $costingId,
                            'item_type' => $type,
                            'item_name' => $item['item_name'] ?? ($item['embl_name'] ?? ($item['wash_type'] ?? null)),
                            'description' => $item['description'] ?? null,
                            'fabric_ref' => $item['fabric_ref'] ?? null,
                            'full_width' => $item['full_width'] ?? null,
                            'cutable_width' => $item['cutable_width'] ?? null,
                            'uom' => $item['uom'] ?? null,
                            'material_source' => $item['material_source'] ?? null,
                            'consumption' => $item['consumption'] ?? 0,
                            'quantity' => $item['quantity'] ?? 0,
                            'unit_price' => $item['unit_price'] ?? 0,
                            'supplier_name' => $item['supplier_name'] ?? null,
                            'remarks' => $item['remarks'] ?? null,
                            'wash_type' => $item['wash_type'] ?? null,
                            'color' => $item['color'] ?? null,
                            'embl_name' => $item['embl_name'] ?? null,
                            'embl_type' => $item['embl_type'] ?? null,
                        ];
                        $this->db->insert('costing_items', $itemData);
                    }
                }
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteCosting(int $costingId): bool
    {
        return (bool) $this->db->delete('costing_sheets', ['costing_id' => $costingId]);
    }

    public function getCosting(int $costingId): ?array
    {
        $costing = $this->db->get('costing_sheets cs', [
            '[>]styles s' => ['cs.style_id' => 'style_id'],
            '[>]buyers b' => ['cs.buyer_id' => 'buyer_id'],
            '[>]factories f' => ['cs.factory_id' => 'factory_id'],
        ], [
            'cs.*',
            's.style_code',
            's.style_name',
            'b.buyer_name',
            'f.factory_name'
        ], ['cs.costing_id' => $costingId]);

        if (!$costing) return null;

        $costing['styles'] = $this->db->select('costing_styles cs', [
            '[>]styles s' => ['cs.style_id' => 'style_id']
        ], [
            'cs.*',
            's.style_code',
            's.style_name'
        ], ['cs.costing_id' => $costingId]) ?? [];
        
        $items = $this->db->select('costing_items', '*', ['costing_id' => $costingId]) ?? [];
        $costing['fabrics'] = array_values(array_filter($items, fn($i) => $i['item_type'] === 'fabric'));
        $costing['accessories'] = array_values(array_filter($items, fn($i) => $i['item_type'] === 'accessory'));
        $costing['wash_costs'] = array_values(array_filter($items, fn($i) => $i['item_type'] === 'wash'));
        $costing['embl_costs'] = array_values(array_filter($items, fn($i) => $i['item_type'] === 'embl'));
        $costing['other_costs'] = array_values(array_filter($items, fn($i) => $i['item_type'] === 'other'));

        return $costing;
    }

    public function getCostingDetails(int $costingId): array
    {
        return $this->getCosting($costingId) ?? [];
    }

}
