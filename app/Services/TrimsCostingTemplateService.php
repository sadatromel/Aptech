<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MasterLibrary\TrimsCostingTemplate;
use App\Models\MasterLibrary\TrimsCostingTemplateItem;
use App\Models\Buyer;
use App\Models\Vendor;
use Core\Database;

class TrimsCostingTemplateService
{
    private TrimsCostingTemplate $templateModel;
    private TrimsCostingTemplateItem $itemModel;
    private Buyer $buyerModel;
    private Vendor $vendorModel;
    private Database $db;

    public function __construct(
        TrimsCostingTemplate $templateModel,
        TrimsCostingTemplateItem $itemModel,
        Buyer $buyerModel,
        Vendor $vendorModel,
        Database $db
    ) {
        $this->templateModel = $templateModel;
        $this->itemModel = $itemModel;
        $this->buyerModel = $buyerModel;
        $this->vendorModel = $vendorModel;
        $this->db = $db;
    }

    public function getBuyers(): array
    {
        return $this->buyerModel->all();
    }

    public function getSuppliers(): array
    {
        return $this->vendorModel->all();
    }

    public function getTrimsGroups(): array
    {
        return [
            'Buttons' => 'Buttons',
            'Zippers' => 'Zippers',
            'Labels' => 'Labels',
            'Threads' => 'Threads',
            'Elastic' => 'Elastic',
            'Interlining' => 'Interlining',
            'Tapes' => 'Tapes',
            'Patches' => 'Patches',
            'Rivets' => 'Rivets',
            'Buckles' => 'Buckles',
            'Eyelets' => 'Eyelets',
            'Velcro' => 'Velcro',
            'Drawstrings' => 'Drawstrings',
            'Other' => 'Other'
        ];
    }

    public function getUOMOptions(): array
    {
        return [
            'Pcs' => 'Pcs',
            'Meters' => 'Meters',
            'Yards' => 'Yards',
            'Kgs' => 'Kgs',
            'Grams' => 'Grams',
            'Sets' => 'Sets',
            'Pairs' => 'Pairs'
        ];
    }

    public function getTemplates(): array
    {
        return $this->templateModel->getWithBuyers();
    }

    public function getTemplateById(int $id): ?array
    {
        $template = $this->templateModel->find($id);
        if (!$template) {
            return null;
        }

        $template['items'] = $this->itemModel->getItemsByTemplate($id);
        return $template;
    }

    public function getTemplatesByBuyer(int $buyerId): array
    {
        return $this->templateModel->getTemplatesByBuyer($buyerId);
    }

    public function createTemplate(array $data): int
    {
        try {
            $this->db->beginTransaction();

            $templateData = [
                'template_name' => $data['template_name'],
                'related_buyers' => $data['related_buyers'],
                'status' => $data['status'] ?? 'Active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $templateId = $this->templateModel->create($templateData);

            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $item['template_id'] = $templateId;
                    
                    // Sanitize numeric/nullable fields
                    if (isset($item['supplier']) && $item['supplier'] === '') {
                        $item['supplier'] = null;
                    }
                    if (isset($item['brand_supplier_ref']) && $item['brand_supplier_ref'] === '') {
                        $item['brand_supplier_ref'] = null;
                    }
                    
                    $item['created_at'] = date('Y-m-d H:i:s');
                    $item['updated_at'] = date('Y-m-d H:i:s');
                    
                    $this->itemModel->create($item);
                }
            }

            $this->db->commit();
            return $templateId;

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function updateTemplate(int $id, array $data): bool
    {
        try {
            $this->db->beginTransaction();

            $templateData = [
                'template_name' => $data['template_name'],
                'related_buyers' => $data['related_buyers'],
                'status' => $data['status'] ?? 'Active',
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->templateModel->update($id, $templateData);

            if (isset($data['items']) && is_array($data['items'])) {
                $this->itemModel->deleteByTemplate($id);
                
                foreach ($data['items'] as $item) {
                    $item['template_id'] = $id;
                    
                    // Sanitize numeric/nullable fields
                    if (isset($item['supplier']) && $item['supplier'] === '') {
                        $item['supplier'] = null;
                    }
                    if (isset($item['brand_supplier_ref']) && $item['brand_supplier_ref'] === '') {
                        $item['brand_supplier_ref'] = null;
                    }
                    
                    $item['created_at'] = date('Y-m-d H:i:s');
                    $item['updated_at'] = date('Y-m-d H:i:s');
                    
                    $this->itemModel->create($item);
                }
            }

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function deleteTemplate(int $id): bool
    {
        try {
            $this->db->beginTransaction();

            $this->itemModel->deleteByTemplate($id);
            $this->templateModel->delete($id);

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function copyTemplate(int $sourceTemplateId, string $newTemplateName, int $newBuyerId): int
    {
        try {
            $this->db->beginTransaction();

            $sourceTemplate = $this->templateModel->find($sourceTemplateId);
            if (!$sourceTemplate) {
                throw new \Exception('Source template not found');
            }

            $newTemplateData = [
                'template_name' => $newTemplateName,
                'related_buyers' => $newBuyerId,
                'status' => 'Active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $newTemplateId = $this->templateModel->create($newTemplateData);

            $sourceItems = $this->itemModel->getItemsByTemplate($sourceTemplateId);
            foreach ($sourceItems as $item) {
                unset($item['item_id']);
                $item['template_id'] = $newTemplateId;
                $item['created_at'] = date('Y-m-d H:i:s');
                $item['updated_at'] = date('Y-m-d H:i:s');
                
                $this->itemModel->create($item);
            }

            $this->db->commit();
            return $newTemplateId;

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function getStats(): array
    {
        $totalTemplates = count($this->templateModel->all());
        $activeTemplates = count($this->templateModel->getActiveTemplates());
        $totalItems = $this->db->sum('trims_costing_template_items', 'item_id');
        
        return [
            'total_templates' => $totalTemplates,
            'active_templates' => $activeTemplates,
            'total_items' => $totalItems
        ];
    }
}
