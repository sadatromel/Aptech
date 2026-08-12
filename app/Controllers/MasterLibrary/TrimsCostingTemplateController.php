<?php

declare(strict_types=1);

namespace App\Controllers\MasterLibrary;

use App\Controllers\BaseController;
use App\Services\TrimsCostingTemplateService;
use Core\Container;

class TrimsCostingTemplateController extends BaseController
{
    private TrimsCostingTemplateService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = Container::get(TrimsCostingTemplateService::class);
    }

    public function index(): void
    {
        $this->requirePermission('view_trims_costing_template');
        
        $data = [
            'buyers' => $this->service->getBuyers(),
            'suppliers' => $this->service->getSuppliers(),
            'trimsGroups' => $this->service->getTrimsGroups(),
            'uomOptions' => $this->service->getUOMOptions(),
            'templates' => $this->service->getTemplates(),
            'stats' => $this->service->getStats()
        ];

        $this->view('master_library/trims_costing_template/index', $data);
    }

    public function create(): void
    {
        $this->requirePermission('create_trims_costing_template');
        
        $data = [
            'buyers' => $this->service->getBuyers(),
            'suppliers' => $this->service->getSuppliers(),
            'trimsGroups' => $this->service->getTrimsGroups(),
            'uomOptions' => $this->service->getUOMOptions()
        ];

        $this->view('master_library/trims_costing_template/create', $data);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requirePermission('create_trims_costing_template');
        
        try {
            $data = $this->request->all();
            $templateId = $this->service->createTemplate($data);
            
            $_SESSION['success_message'] = 'Template created successfully';
            $this->redirect('master_library/trims_costing_template');
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error creating template: ' . $e->getMessage();
            $this->redirect('master_library/trims_costing_template/create');
        }
    }

    public function edit(int $id): void
    {
        $this->requirePermission('edit_trims_costing_template');
        
        $template = $this->service->getTemplateById($id);
        if (!$template) {
            $_SESSION['error_message'] = 'Template not found';
            $this->redirect('master_library/trims_costing_template');
            return;
        }

        $data = [
            'template' => $template,
            'buyers' => $this->service->getBuyers(),
            'suppliers' => $this->service->getSuppliers(),
            'trimsGroups' => $this->service->getTrimsGroups(),
            'uomOptions' => $this->service->getUOMOptions()
        ];

        $this->view('master_library/trims_costing_template/edit', $data);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $this->requirePermission('edit_trims_costing_template');
        
        try {
            $data = $this->request->all();
            $success = $this->service->updateTemplate($id, $data);
            
            if ($success) {
                $_SESSION['success_message'] = 'Template updated successfully';
            } else {
                $_SESSION['error_message'] = 'Failed to update template';
            }
            $this->redirect('master_library/trims_costing_template');
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error updating template: ' . $e->getMessage();
            $this->redirect("master_library/trims_costing_template/edit/{$id}");
        }
    }

    public function destroy(int $id): void
    {
        $this->verifyCsrf();
        $this->requirePermission('delete_trims_costing_template');
        
        try {
            $success = $this->service->deleteTemplate($id);
            
            if ($success) {
                $_SESSION['success_message'] = 'Template deleted successfully';
            } else {
                $_SESSION['error_message'] = 'Failed to delete template';
            }
        } catch (\Exception $e) {
            $_SESSION['error_message'] = 'Error deleting template: ' . $e->getMessage();
        }
        
        $this->redirect('master_library/trims_costing_template');
    }

    // API methods for AJAX operations
    public function show(): void
    {
        $this->requirePermission('view_trims_costing_template');
        
        header('Content-Type: application/json');
        
        try {
            $templateId = $_GET['id'] ?? 0;
            
            if (!$templateId) {
                throw new \Exception('Template ID is required');
            }
            
            $template = $this->service->getTemplateById($templateId);
            
            if (!$template) {
                throw new \Exception('Template not found');
            }
            
            echo json_encode([
                'success' => true,
                'data' => $template
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching template: ' . $e->getMessage()
            ]);
        }
    }

    public function getTemplatesByBuyer(): void
    {
        $this->requirePermission('view_trims_costing_template');
        
        header('Content-Type: application/json');
        
        try {
            $buyerId = $_GET['buyer_id'] ?? 0;
            
            if (!$buyerId) {
                throw new \Exception('Buyer ID is required');
            }
            
            $templates = $this->service->getTemplatesByBuyer($buyerId);
            
            echo json_encode([
                'success' => true,
                'data' => $templates
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching templates: ' . $e->getMessage()
            ]);
        }
    }

    public function copyTemplate(): void
    {
        $this->requirePermission('copy_trims_costing_template');
        
        header('Content-Type: application/json');
        
        try {
            $data = $this->getPostData();
            $sourceTemplateId = $data['source_template_id'] ?? 0;
            $newTemplateName = $data['new_template_name'] ?? '';
            $newBuyerId = $data['new_buyer_id'] ?? 0;
            
            if (!$sourceTemplateId || !$newTemplateName || !$newBuyerId) {
                throw new \Exception('All fields are required for copying template');
            }
            
            $newTemplateId = $this->service->copyTemplate($sourceTemplateId, $newTemplateName, $newBuyerId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template copied successfully',
                'template_id' => $newTemplateId
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error copying template: ' . $e->getMessage()
            ]);
        }
    }

    private function getPostData(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?: [];
    }
}
