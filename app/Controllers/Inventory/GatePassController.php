<?php

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Models\Inventory\GatePass;
use App\Models\Inventory\GatePassItem;
use App\Models\Department;
use App\Models\User;
use App\Models\Style;
use App\Models\MasterLibrary\ProductCategory;
use App\Models\MasterLibrary\BuyerBrand;

class GatePassController extends BaseController
{
    private GatePass $gatePassModel;
    private GatePassItem $gatePassItemModel;
    private Department $departmentModel;
    private User $userModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->gatePassModel = new GatePass();
        $this->gatePassItemModel = new GatePassItem();
        $this->departmentModel = new Department();
        $this->userModel = new User();
    }

    public function index()
    {
        $this->requirePermission('view_gate_passes');
        
        $gatePasses = $this->db->query("
            SELECT gp.*, d.department_name as department_name, u.username as prepared_by_name 
            FROM gate_passes gp 
            LEFT JOIN departments d ON gp.department_id = d.department_id 
            LEFT JOIN users u ON gp.prepared_by = u.user_id 
            ORDER BY gp.id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        $this->view('inventory/gate-passes/index', [
            'gatePasses' => $gatePasses
        ]);
    }

    public function create()
    {
        $this->requirePermission('view_gate_passes');
        
        $departments = $this->departmentModel->all();
        $users = $this->userModel->all();
        
        // Let's fetch basic master data for dropdowns
        $buyers = $this->db->query("SELECT buyer_id, buyer_name FROM buyers ORDER BY buyer_name ASC")->fetchAll(\PDO::FETCH_ASSOC);
        $styles = $this->db->query("SELECT style_id, style_code, style_name FROM styles ORDER BY style_code ASC")->fetchAll(\PDO::FETCH_ASSOC);
        $categories = $this->db->query("SELECT category_id, category_name FROM item_categories ORDER BY category_name ASC")->fetchAll(\PDO::FETCH_ASSOC);
        
        $this->view('inventory/gate-passes/create', [
            'departments' => $departments,
            'users' => $users,
            'buyers' => $buyers,
            'styles' => $styles,
            'categories' => $categories,
            'gatePassNo' => $this->gatePassModel->generateGatePassNo()
        ]);
    }

    public function store()
    {
        $this->verifyCsrf();
        $this->requirePermission('view_gate_passes');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . '/inventory/gate-passes');
            return;
        }

        try {
            $this->db->beginTransaction();
            
            // Generate Number
            $gatePassNo = $this->gatePassModel->generateGatePassNo();

            // Insert Gate Pass
            $gatePassData = [
                'gate_pass_no' => $gatePassNo,
                'challan_no' => $_POST['challan_no'] ?? null,
                'challan_date' => !empty($_POST['challan_date']) ? $_POST['challan_date'] : null,
                'prepared_for' => $_POST['prepared_for'] ?? null,
                'address' => $_POST['address'] ?? null,
                'bearer_by' => $_POST['bearer_by'] ?? null,
                'name_and_desig' => $_POST['name_and_desig'] ?? null,
                'department_id' => !empty($_POST['department_id']) ? $_POST['department_id'] : null,
                'basis' => $_POST['basis'] ?? null,
                'remarks' => $_POST['remarks'] ?? null,
                'returnable' => isset($_POST['returnable']) ? 1 : 0,
                'date' => $_POST['date'] ?? date('Y-m-d'),
                'time' => $_POST['time'] ?? date('H:i:s'),
                'exp_return_date' => !empty($_POST['exp_return_date']) ? $_POST['exp_return_date'] : null,
                'verified_by' => !empty($_POST['verified_by']) ? $_POST['verified_by'] : null,
                'approve_by' => !empty($_POST['approve_by']) ? $_POST['approve_by'] : null,
                'prepared_by' => $_SESSION['user_id'] ?? 1,
                'designation' => $_POST['designation'] ?? null,
                'mobile_no' => $_POST['mobile_no'] ?? null,
                'email' => $_POST['email'] ?? null,
                'supervisor' => $_POST['supervisor'] ?? null
            ];

            $gatePassId = $this->gatePassModel->create($gatePassData);

            // Insert Items
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    if (empty($item['description'])) continue; // Skip empty rows

                    $this->gatePassItemModel->create([
                        'gate_pass_id' => $gatePassId,
                        'buyer_id' => !empty($item['buyer_id']) ? $item['buyer_id'] : null,
                        'style_id' => !empty($item['style_id']) ? $item['style_id'] : null,
                        'allocation_no' => $item['allocation_no'] ?? null,
                        'item_category_id' => !empty($item['item_category_id']) ? $item['item_category_id'] : null,
                        'description' => $item['description'],
                        'color' => $item['color'] ?? null,
                        'size' => $item['size'] ?? null,
                        'unit' => $item['unit'] ?? null,
                        'quantity' => $item['quantity'] ?? 0,
                        'weight' => $item['weight'] ?? null
                    ]);
                }
            }

            $this->db->commit();
            $_SESSION['success'] = 'Gate Pass created successfully.';
            $this->redirect(BASE_URL . '/inventory/gate-passes/show/' . $gatePassId);

        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to create Gate Pass: ' . $e->getMessage();
            $this->redirect(BASE_URL . '/inventory/gate-passes/create');
        }
    }

    public function show(int $id)
    {
        $this->requirePermission('view_gate_passes');

        $gatePass = $this->gatePassModel->getWithDetails($id);
        
        if (!$gatePass) {
            $_SESSION['error'] = 'Gate Pass not found.';
            $this->redirect(BASE_URL . '/inventory/gate-passes');
            return;
        }

        $items = $this->gatePassModel->getItems($id);

        $this->view('inventory/gate-passes/show', [
            'gatePass' => $gatePass,
            'items' => $items
        ]);
    }

    public function destroy(int $id)
    {
        $this->verifyCsrf();
        $this->requirePermission('view_inventory');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . '/inventory/gate-passes');
            return;
        }

        try {
            $this->db->beginTransaction();
            $this->db->commit();
            
            $_SESSION['success'] = 'Gate Pass deleted successfully.';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to delete Gate Pass.';
        }

        $this->redirect(BASE_URL . '/inventory/gate-passes');
    }
}
