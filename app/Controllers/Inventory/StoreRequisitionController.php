<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Models\Inventory\StoreRequisition;
use App\Models\Inventory\StoreRequisitionLine;
use App\Models\Item;
use Exception;

class StoreRequisitionController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        $requisitions = $this->db->select('store_requisitions sr', [
            '[>]store_locations sl' => ['sr.location_id' => 'location_id'],
            '[>]departments d' => ['sr.department_id' => 'department_id'],
            '[>]users u_req' => ['sr.requested_by' => 'user_id'],
            '[>]users u_app' => ['sr.approved_by' => 'user_id']
        ], [
            'sr.requisition_id',
            'sr.requisition_no',
            'sr.requisition_date',
            'sr.required_date',
            'sr.status',
            'sr.remarks',
            'sl.location_name',
            'd.department_name',
            'requested_by_name' => 'u_req.username',
            'approved_by_name' => 'u_app.username'
        ], [
            'ORDER' => ['sr.requisition_date' => 'DESC', 'sr.requisition_id' => 'DESC']
        ]);

        $this->view('inventory/store_requisitions/index', [
            'requisitions' => $requisitions
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();

        $locations = $this->db->select('store_locations', '*', ['status' => 'active', 'ORDER' => 'location_name']);
        $departments = $this->db->select('departments', '*', ['ORDER' => 'department_name']);
        $items = $this->db->select('items', '*', ['status' => 'active', 'ORDER' => 'item_name']);

        $this->view('inventory/store_requisitions/create', [
            'locations' => $locations,
            'departments' => $departments,
            'items' => $items
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        $data = $this->request->all();

        try {
            $this->db->beginTransaction();

            // Generate Requisition No
            $prefix = 'SRQ-' . date('ym');
            $lastReq = $this->db->get('store_requisitions', 'requisition_no', [
                'requisition_no[~]' => $prefix . '%',
                'ORDER' => ['requisition_id' => 'DESC']
            ]);

            if ($lastReq && is_string($lastReq)) {
                $serial = (int) substr($lastReq, -4);
                $newSerial = str_pad((string)($serial + 1), 4, '0', STR_PAD_LEFT);
            } else {
                $newSerial = '0001';
            }
            $requisitionNo = "{$prefix}-{$newSerial}";

            $reqModel = new StoreRequisition();
            $reqId = $reqModel->create([
                'requisition_no' => $requisitionNo,
                'location_id' => $data['location_id'],
                'department_id' => $data['department_id'] ?? null,
                'requisition_date' => $data['requisition_date'],
                'required_date' => $data['required_date'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'status' => 'pending',
                'remarks' => $data['remarks'] ?? null,
                'requested_by' => $this->auth->user()['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);

            if (!$reqId) {
                throw new Exception("Failed to create requisition header.");
            }

            if (isset($data['items']) && is_array($data['items'])) {
                $lineModel = new StoreRequisitionLine();
                foreach ($data['items'] as $item) {
                    if (empty($item['item_id']) || empty($item['quantity_requested'])) continue;

                    $lineModel->create([
                        'requisition_id' => $reqId,
                        'item_id' => $item['item_id'],
                        'quantity_requested' => $item['quantity_requested'],
                        'remarks' => $item['remarks'] ?? null,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            $this->db->commit();
            $this->json(['success' => true, 'message' => 'Store Requisition created successfully.']);
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function show(int $id): void
    {
        $this->requireAuth();

        $requisition = $this->db->get('store_requisitions sr', [
            '[>]store_locations sl' => ['sr.location_id' => 'location_id'],
            '[>]departments d' => ['sr.department_id' => 'department_id'],
            '[>]users u_req' => ['sr.requested_by' => 'user_id'],
            '[>]users u_app' => ['sr.approved_by' => 'user_id']
        ], [
            'sr.*',
            'sl.location_name',
            'd.department_name',
            'requested_by_name' => 'u_req.username',
            'approved_by_name' => 'u_app.username'
        ], [
            'sr.requisition_id' => $id
        ]);

        if (!$requisition) {
            // handle 404
            die("Requisition not found");
        }

        $lines = $this->db->select('store_requisition_lines srl', [
            '[>]items i' => ['srl.item_id' => 'item_id']
        ], [
            'srl.*',
            'i.item_code',
            'i.item_name',
            'uom' => 'i.unit_of_measure'
        ], [
            'srl.requisition_id' => $id
        ]);

        $this->view('inventory/store_requisitions/show', [
            'requisition' => $requisition,
            'lines' => $lines
        ]);
    }
}
