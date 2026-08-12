<?php

declare(strict_types=1);

namespace App\Controllers\Commercial;

use App\Controllers\BaseController;
use App\Models\SalesContract;
use App\Models\Customer;
use Exception;

class SalesContractController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();
        
        $contracts = $this->db->query("
            SELECT sc.*, c.customer_name, c.customer_code,
                   (SELECT COUNT(*) FROM sales_contract_production_orders WHERE so_id = sc.so_id) as total_items
            FROM sales_contracts sc
            LEFT JOIN customers c ON sc.customer_id = c.customer_id
            ORDER BY sc.contract_date DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        $this->view('commercial/sales_contracts/index', ['contracts' => $contracts]);
    }

    public function create(): void
    {
        $this->requireAuth();
        
        $customers = (new Customer())->all();
        $currencies = $this->db->select('currencies', '*', ['status' => 'active', 'ORDER' => 'currency_code']);
        
        $this->view('commercial/sales_contracts/create', [
            'customers' => $customers,
            'currencies' => $currencies
        ]);
    }

    public function getOrdersByAllocation(string $number): void
    {
        $this->requireAuth();
        
        $orders = $this->db->select('production_orders po', [
            '[>]styles s' => ['po.style_id' => 'style_id']
        ], [
            'po.po_id',
            'po.po_number',
            'po.allocation_number',
            'po.quantity_ordered',
            'po.unit_price',
            'style_code' => 's.style_code',
            'style_name' => 's.style_description'
        ], [
            'po.allocation_number' => $number,
            'po.status[!]' => 'cancelled'
        ]);

        header('Content-Type: application/json');
        echo json_encode($orders);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->requireAuth();
        
        try {
            $data = $_POST;
            $items = json_decode($data['items'] ?? '[]', true);
            
            if (empty($items)) {
                throw new Exception("Please add at least one order to the contract.");
            }

            $this->db->beginTransaction();

            try {
                // 1. Save Sales Contract Header
                $contractId = $this->db->insert('sales_contracts', [
                    'contract_no' => $data['contract_no'],
                    'customer_id' => (int)$data['customer_id'],
                    'contract_date' => $data['contract_date'],
                    'valid_until' => $data['valid_until'] ?: null,
                    'shipping_terms' => $data['shipping_terms'],
                    'payment_terms' => $data['payment_terms'],
                    'currency' => $data['currency'],
                    'total_amount' => (float)$data['total_amount'],
                    'notes' => $data['notes'],
                    'created_by' => $_SESSION['user_id'] ?? 0,
                    'status' => 'pending'
                ]);

                // 2. Link Production Orders
                foreach ($items as $item) {
                    $this->db->insert('sales_contract_production_orders', [
                        'so_id' => $contractId,
                        'po_id' => $item['po_id'],
                        'contract_unit_price' => (float)$item['unit_price'],
                        'contract_quantity' => (int)$item['quantity_ordered']
                    ]);
                }

                $this->db->commit();
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

            $_SESSION['flash_success'] = "Sales Contract created successfully.";
            header('Location: ' . BASE_URL . '/commercial/sales-contracts');
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: ' . BASE_URL . '/commercial/sales-contracts/create');
        }
    }

    public function show(int $id): void
    {
        $this->requireAuth();

        $contract = $this->db->query("
            SELECT sc.*, c.customer_name, c.customer_code, c.email, c.phone, c.address
            FROM sales_contracts sc
            LEFT JOIN customers c ON sc.customer_id = c.customer_id
            WHERE sc.so_id = ?
        ", [$id])->fetch(\PDO::FETCH_ASSOC);

        if (!$contract) {
            $this->notFound();
            return;
        }

        $items = [];
        try {
            $items = $this->db->query("
                SELECT scpo.*, po.po_number, po.allocation_number, s.style_code, s.style_name
                FROM sales_contract_production_orders scpo
                LEFT JOIN production_orders po ON scpo.po_id = po.po_id
                LEFT JOIN styles s ON po.style_id = s.style_id
                WHERE scpo.so_id = ?
            ", [$id])->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $items = [];
        }

        $this->view('commercial/sales_contracts/show', [
            'contract' => $contract,
            'items'    => $items,
        ]);
    }
}
